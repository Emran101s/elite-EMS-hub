<?php

namespace Database\Seeders;

use App\Models\Client;
use App\Models\Event;
use App\Models\Supplier;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Generates randomly-named demo events with full operational data.
 * Re-runnable: `php artisan db:seed --class=RandomEventSeeder`.
 * Reads the count from EBH_RANDOM_EVENTS (default 10).
 */
class RandomEventSeeder extends Seeder
{
    use WithoutModelEvents;

    private const SCOPES = ['Global', 'Regional', 'MENA', 'Gulf', 'International', 'Annual', 'National', 'Middle East'];

    private const TOPICS = ['Fintech', 'Leadership', 'Healthcare', 'Technology', 'Investment', 'Energy',
        'Innovation', 'Digital', 'Trade', 'Education', 'Startup', 'Real Estate', 'Tourism', 'Logistics', 'AI'];

    /** format label => [event type, avatar type hint]. */
    private const FORMATS = [
        'Summit' => 'summit', 'Forum' => 'conference', 'Conference' => 'conference', 'Expo' => 'exhibition',
        'Congress' => 'conference', 'Symposium' => 'conference', 'Gala' => 'gala_dinner',
        'Workshop' => 'workshop', 'Awards' => 'awards_ceremony', 'Bootcamp' => 'training_program',
    ];

    private const CITIES = [
        ['Amman', 'Jordan'], ['Dubai', 'UAE'], ['Riyadh', 'KSA'], ['Doha', 'Qatar'],
        ['Manama', 'Bahrain'], ['Kuwait City', 'Kuwait'], ['Muscat', 'Oman'], ['Cairo', 'Egypt'],
    ];

    private const PALETTES = [
        ['#0B1F3A', '#F8FAFC', '#D4AF37', '#0F172A'], // navy + gold
        ['#10141A', '#F8FAFC', '#D4AF37', '#0F172A'], // black + gold
        ['#1D4ED8', '#F8FAFC', '#94A3B8', '#0F172A'], // blue + silver
        ['#166534', '#F8FAFC', '#0B1F3A', '#0F172A'], // green + navy
        ['#7F1D1D', '#F8FAFC', '#D4AF37', '#0F172A'], // maroon + gold
        ['#6D28D9', '#F8FAFC', '#D4AF37', '#0F172A'], // purple + gold
    ];

    private const STAGES = ['draft', 'proposal', 'confirmed', 'planning', 'planning', 'production', 'production', 'live', 'completed'];

    private const SESSION_TYPES = ['opening', 'keynote', 'panel', 'workshop', 'break', 'lunch', 'networking', 'closing'];

    private const TASK_TITLES = ['Confirm venue contract', 'Finalize agenda', 'Book keynote speaker', 'Order branding',
        'Set up registration', 'Arrange catering', 'Confirm AV supplier', 'Print badges', 'Sponsor logo pack',
        'Transport schedule', 'Security briefing', 'Press invitations', 'Marketing materials', 'Booth layout sign-off'];

    public function run(): void
    {
        $count = (int) env('EBH_RANDOM_EVENTS', 10);

        $clients = Client::all();
        $team = User::all();
        $venues = Venue::all();
        $suppliers = Supplier::all();

        for ($i = 0; $i < $count; $i++) {
            $format = array_rand(self::FORMATS);
            $type = self::FORMATS[$format];
            $name = fake()->randomElement(self::SCOPES).' '.fake()->randomElement(self::TOPICS).' '.$format
                .' '.fake()->numberBetween(2026, 2027);

            [$city, $country] = fake()->randomElement(self::CITIES);
            $start = Carbon::instance(fake()->dateTimeBetween('-1 month', '+8 months'))->startOfDay();
            $end = (clone $start)->addDays(fake()->numberBetween(0, 3));
            [$primary, $secondary, $accent, $text] = fake()->randomElement(self::PALETTES);

            $modules = ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'reports'];
            if (in_array($type, ['exhibition', 'gala_dinner', 'summit', 'awards_ceremony'], true)) {
                $modules[] = 'sponsors';
            }
            $modules = array_merge($modules, fake()->randomElements(['risks', 'approvals', 'files'], fake()->numberBetween(0, 2)));

            $event = Event::create([
                'name' => $name,
                'description' => fake()->optional(0.7)->sentence(10),
                'type' => $type,
                'status' => 'planning',
                'stage' => fake()->randomElement(self::STAGES),
                'city' => $city,
                'country' => $country,
                'timezone' => 'Asia/Amman',
                'client_id' => $clients->isNotEmpty() ? $clients->random()->id : null,
                'project_manager_id' => $team->isNotEmpty() ? $team->random()->id : null,
                'venue_id' => fake()->boolean(70) && $venues->isNotEmpty() ? $venues->random()->id : null,
                'starts_at' => $start,
                'ends_at' => $end,
                'budget_cents' => fake()->numberBetween(30, 900) * 100000,
                'progress' => fake()->numberBetween(5, 95),
                'expected_participants' => fake()->numberBetween(40, 2000),
                'primary_color' => $primary,
                'secondary_color' => $secondary,
                'accent_color' => $accent,
                'text_color' => $text,
                'enabled_modules' => array_values(array_unique($modules)),
            ]);

            if ($event->project_manager_id) {
                $event->teamMembers()->syncWithoutDetaching([$event->project_manager_id => ['role' => 'project_manager']]);
            }

            $this->seedRooms($event);
            $event->syncAgendaDays();
            $this->seedSessions($event);
            $this->seedTasks($event, $team);
            $this->seedBudget($event, $suppliers);
            $this->seedSuppliers($event, $suppliers);
            $this->seedRisks($event, $team);
        }

        $this->command?->info("Seeded {$count} random events.");
    }

    private function seedRooms(Event $event): void
    {
        $pool = [['Main Hall', 'main_hall', 1200], ['Breakout A', 'breakout', 120], ['Breakout B', 'breakout', 120],
            ['Exhibition Floor', 'exhibition', 800], ['VIP Lounge', 'vip', 40], ['Registration', 'registration', null]];
        foreach (fake()->randomElements($pool, fake()->numberBetween(2, 4)) as [$n, $t, $cap]) {
            $event->rooms()->create(['name' => $n, 'type' => $t, 'capacity' => $cap]);
        }
    }

    private function seedSessions(Event $event): void
    {
        $rooms = $event->rooms()->pluck('id');
        foreach ($event->agendaDays as $day) {
            $clock = 9 * 60; // 09:00
            foreach (range(1, fake()->numberBetween(3, 5)) as $s) {
                $len = fake()->randomElement([30, 45, 60, 90]);
                $type = fake()->randomElement(self::SESSION_TYPES);
                $event->agendaSessions()->create([
                    'agenda_day_id' => $day->id,
                    'room_id' => $rooms->isNotEmpty() ? $rooms->random() : null,
                    'title' => ucfirst($type).': '.fake()->catchPhrase(),
                    'type' => $type,
                    'status' => fake()->randomElement(['draft', 'confirmed', 'confirmed', 'final', 'waiting_speaker']),
                    'starts_at' => sprintf('%02d:%02d', intdiv($clock, 60), $clock % 60),
                    'ends_at' => sprintf('%02d:%02d', intdiv($clock + $len, 60), ($clock + $len) % 60),
                    'speaker' => fake()->optional(0.6)->name(),
                    'sort' => $s,
                ]);
                $clock += $len + fake()->randomElement([0, 15, 30]);
            }
        }
    }

    private function seedTasks(Event $event, $team): void
    {
        foreach (fake()->randomElements(self::TASK_TITLES, fake()->numberBetween(8, 14)) as $title) {
            $event->tasks()->create([
                'title' => $title,
                'status' => fake()->randomElement(['pending', 'pending', 'in_progress', 'in_progress', 'completed', 'completed', 'completed']),
                'priority' => fake()->randomElement(['low', 'normal', 'normal', 'high', 'urgent']),
                'due_on' => fake()->dateTimeBetween('-1 week', '+2 months'),
                'assignee_id' => $team->isNotEmpty() ? $team->random()->id : null,
            ]);
        }
    }

    private function seedBudget(Event $event, $suppliers): void
    {
        $cats = fake()->randomElements(['venue', 'catering', 'av', 'production', 'branding', 'transportation'], fake()->numberBetween(3, 5));
        foreach ($cats as $cat) {
            $est = fake()->numberBetween(20, 300) * 100000;
            $event->budgetItems()->create([
                'category' => $cat,
                'description' => ucfirst($cat).' — '.fake()->words(2, true),
                'estimated_cents' => $est,
                'actual_cents' => fake()->boolean(60) ? (int) ($est * fake()->randomFloat(2, 0.5, 1.15)) : 0,
                'supplier_id' => fake()->boolean(50) && $suppliers->isNotEmpty() ? $suppliers->random()->id : null,
                'payment_status' => fake()->randomElement(['pending', 'partial', 'paid']),
                'invoice_number' => fake()->optional(0.5)->numerify('INV-2026-###'),
            ]);
        }
    }

    private function seedSuppliers(Event $event, $suppliers): void
    {
        if ($suppliers->isEmpty()) {
            return;
        }
        foreach ($suppliers->random(min(fake()->numberBetween(2, 4), $suppliers->count())) as $supplier) {
            $event->suppliers()->syncWithoutDetaching([$supplier->id => [
                'status' => fake()->randomElement(['requested', 'quoted', 'approved', 'contracted', 'in_production', 'delivered', 'issue']),
            ]]);
        }
    }

    private function seedRisks(Event $event, $team): void
    {
        $pool = [['Speaker availability conflict', 'speaker'], ['Budget overrun risk', 'budget'],
            ['Venue contract pending', 'venue'], ['Supplier delivery delay', 'supplier'], ['Weather contingency', 'weather']];
        foreach (fake()->randomElements($pool, fake()->numberBetween(0, 3)) as [$title, $cat]) {
            $event->risks()->create([
                'title' => $title,
                'category' => $cat,
                'probability' => fake()->numberBetween(1, 5),
                'impact' => fake()->numberBetween(1, 5),
                'status' => fake()->randomElement(['open', 'open', 'monitoring', 'mitigated', 'escalated']),
                'owner_id' => $team->isNotEmpty() ? $team->random()->id : null,
                'mitigation' => fake()->optional(0.7)->sentence(8),
            ]);
        }
    }
}
