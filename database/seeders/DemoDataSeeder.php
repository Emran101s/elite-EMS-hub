<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

/**
 * Idempotent demo dataset mirroring the Command Center reference mockup.
 * Safe to re-run: rows are matched by unique names/emails, not recreated.
 */
class DemoDataSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(EventAvatarSeeder::class);

        // ── Venues ──────────────────────────────────────────────────────
        $venues = collect([
            ['name' => 'Royal Convention Centre', 'city' => 'Amman', 'country' => 'Jordan', 'capacity' => 2500],
            ['name' => 'Gulf Grand Ballroom', 'city' => 'Manama', 'country' => 'Bahrain', 'capacity' => 800],
            ['name' => 'Jumeirah Learning Hub', 'city' => 'Dubai', 'country' => 'UAE', 'capacity' => 300],
            ['name' => 'Doha Exhibition Center', 'city' => 'Doha', 'country' => 'Qatar', 'capacity' => 5000],
            ['name' => 'GJU Main Campus Hall', 'city' => 'Amman', 'country' => 'Jordan', 'capacity' => 1200],
            ['name' => 'Al Faisaliah Private Suites', 'city' => 'Riyadh', 'country' => 'KSA', 'capacity' => 60],
        ])->mapWithKeys(fn (array $venue) => [
            $venue['name'] => Venue::updateOrCreate(['name' => $venue['name']], $venue),
        ]);

        // ── Projects ────────────────────────────────────────────────────
        $projects = collect([
            [
                'name' => 'Conference Season 2026',
                'status' => 'active',
                'description' => 'All large-format conferences and expos for the 2026 season.',
                'starts_on' => '2026-01-15', 'ends_on' => '2026-12-20',
                'budget_cents' => 145000000,
            ],
            [
                'name' => 'Corporate Events Portfolio',
                'status' => 'active',
                'description' => 'Galas, private dinners and corporate hospitality.',
                'starts_on' => '2026-03-01', 'ends_on' => '2026-11-30',
                'budget_cents' => 60000000,
            ],
            [
                'name' => 'Education & Training Series',
                'status' => 'active',
                'description' => 'University fairs and professional training workshops.',
                'starts_on' => '2026-02-01', 'ends_on' => '2026-10-15',
                'budget_cents' => 40000000,
            ],
        ])->mapWithKeys(fn (array $project) => [
            $project['name'] => Project::updateOrCreate(['name' => $project['name']], $project),
        ]);

        // ── Events (the six islands from the mockup) ────────────────────
        $events = collect([
            [
                'name' => 'ICFT 2026', 'type' => 'conference', 'status' => 'on_track',
                'city' => 'Amman', 'country' => 'Jordan',
                'venue' => 'Royal Convention Centre', 'project' => 'Conference Season 2026',
                'starts_at' => '2026-09-14', 'ends_at' => '2026-09-16',
                'budget_cents' => 65000000, 'progress' => 92,
            ],
            [
                'name' => 'EY Annual Gala', 'type' => 'gala', 'status' => 'in_progress',
                'city' => 'Manama', 'country' => 'Bahrain',
                'venue' => 'Gulf Grand Ballroom', 'project' => 'Corporate Events Portfolio',
                'starts_at' => '2026-08-20', 'ends_at' => '2026-08-20',
                'budget_cents' => 28000000, 'progress' => 76,
            ],
            [
                'name' => 'NDI Workshop', 'type' => 'workshop', 'status' => 'at_risk',
                'city' => 'Dubai', 'country' => 'UAE',
                'venue' => 'Jumeirah Learning Hub', 'project' => 'Education & Training Series',
                'starts_at' => '2026-07-28', 'ends_at' => '2026-07-30',
                'budget_cents' => 9000000, 'progress' => 61,
            ],
            [
                'name' => 'Tech Expo 2026', 'type' => 'exhibition', 'status' => 'behind',
                'city' => 'Doha', 'country' => 'Qatar',
                'venue' => 'Doha Exhibition Center', 'project' => 'Conference Season 2026',
                'starts_at' => '2026-10-05', 'ends_at' => '2026-10-08',
                'budget_cents' => 82000000, 'progress' => 45,
            ],
            [
                'name' => 'GJU Career Fair', 'type' => 'career_fair', 'status' => 'on_track',
                'city' => 'Amman', 'country' => 'Jordan',
                'venue' => 'GJU Main Campus Hall', 'project' => 'Education & Training Series',
                'starts_at' => '2026-09-02', 'ends_at' => '2026-09-03',
                'budget_cents' => 12000000, 'progress' => 85,
            ],
            [
                'name' => 'Private Dinner', 'type' => 'dinner', 'status' => 'on_track',
                'city' => 'Riyadh', 'country' => 'KSA',
                'venue' => 'Al Faisaliah Private Suites', 'project' => 'Corporate Events Portfolio',
                'starts_at' => '2026-08-06', 'ends_at' => '2026-08-06',
                'budget_cents' => 15000000, 'progress' => 90,
            ],
        ])->mapWithKeys(function (array $event) use ($venues, $projects) {
            $attributes = collect($event)->except(['venue', 'project'])->all();
            $attributes['venue_id'] = $venues[$event['venue']]->id;
            $attributes['project_id'] = $projects[$event['project']]->id;

            return [$event['name'] => Event::updateOrCreate(['name' => $event['name']], $attributes)];
        });

        // Give each demo event its library avatar.
        $avatarMap = [
            'ICFT 2026' => 'international-conference',
            'EY Annual Gala' => 'gala-dinner',
            'NDI Workshop' => 'workshop',
            'Tech Expo 2026' => 'exhibition',
            'GJU Career Fair' => 'exhibition',
            'Private Dinner' => 'vip-event',
        ];
        foreach ($avatarMap as $eventName => $slug) {
            $events[$eventName]->update([
                'avatar_id' => \App\Models\EventAvatar::where('slug', $slug)->value('id'),
            ]);
        }

        // ── Suppliers (top three from the mockup + supporting cast) ─────
        $suppliers = collect([
            ['name' => 'Creative Vision Co.', 'category' => 'support', 'rating' => 4.9, 'city' => 'Amman', 'country' => 'Jordan'],
            ['name' => 'Event Tech Solutions', 'category' => 'av_lighting', 'rating' => 4.8, 'city' => 'Dubai', 'country' => 'UAE'],
            ['name' => 'Royal Catering Services', 'category' => 'catering', 'rating' => 4.7, 'city' => 'Riyadh', 'country' => 'KSA'],
            ['name' => 'Falcon Stage Production', 'category' => 'production', 'rating' => 4.5, 'city' => 'Doha', 'country' => 'Qatar'],
            ['name' => 'Oasis Logistics', 'category' => 'logistics', 'rating' => 4.3, 'city' => 'Manama', 'country' => 'Bahrain'],
            ['name' => 'Petra Decor Studio', 'category' => 'decor', 'rating' => 4.6, 'city' => 'Amman', 'country' => 'Jordan'],
        ])->mapWithKeys(fn (array $supplier) => [
            $supplier['name'] => Supplier::updateOrCreate(['name' => $supplier['name']], $supplier),
        ]);

        // Attach each event to a plausible supplier mix (idempotent sync-without-detach).
        $mix = [
            'ICFT 2026' => ['Creative Vision Co.', 'Event Tech Solutions', 'Royal Catering Services', 'Oasis Logistics'],
            'EY Annual Gala' => ['Royal Catering Services', 'Petra Decor Studio', 'Event Tech Solutions'],
            'NDI Workshop' => ['Event Tech Solutions', 'Creative Vision Co.'],
            'Tech Expo 2026' => ['Falcon Stage Production', 'Event Tech Solutions', 'Oasis Logistics'],
            'GJU Career Fair' => ['Creative Vision Co.', 'Oasis Logistics'],
            'Private Dinner' => ['Royal Catering Services', 'Petra Decor Studio'],
        ];
        foreach ($mix as $eventName => $supplierNames) {
            $events[$eventName]->suppliers()->syncWithoutDetaching(
                collect($supplierNames)->map(fn (string $name) => $suppliers[$name]->id)->all()
            );
        }

        // ── Team ────────────────────────────────────────────────────────
        $team = collect([
            ['name' => 'Emran Ahmed', 'email' => 'emran.itan@elitebhub.com', 'title' => 'Super Admin'],
            ['name' => 'Layla Haddad', 'email' => 'layla.haddad@elitebhub.com', 'title' => 'Operations Manager'],
            ['name' => 'Omar Nassar', 'email' => 'omar.nassar@elitebhub.com', 'title' => 'Event Producer'],
            ['name' => 'Sara Al-Rashid', 'email' => 'sara.alrashid@elitebhub.com', 'title' => 'Finance Lead'],
            ['name' => 'Khalid Mansour', 'email' => 'khalid.mansour@elitebhub.com', 'title' => 'Logistics Coordinator'],
            ['name' => 'Dana Qasem', 'email' => 'dana.qasem@elitebhub.com', 'title' => 'Client Relations'],
        ])->mapWithKeys(fn (array $member) => [
            $member['name'] => User::updateOrCreate(
                ['email' => $member['email']],
                $member + ['password' => 'password'],
            ),
        ]);

        // ── Tasks (mockup ratio: 72 completed / 36 in progress / 20 pending) ──
        if (Task::count() === 0) {
            $eventIds = $events->pluck('id')->all();
            $assigneeIds = $team->pluck('id')->all();

            $titles = [
                'Venue payment', 'Marketing materials', 'Speaker decks', 'Hotel contract',
                'Booth production', 'Speaker confirmation', 'Payment approval', 'Transport schedule',
                'Catering tasting', 'Badge printing', 'AV rehearsal', 'Sponsor pack delivery',
                'Registration page QA', 'Security briefing', 'Floor plan sign-off', 'Press invitations',
            ];

            foreach (['completed' => 72, 'in_progress' => 36, 'pending' => 20] as $status => $count) {
                Task::factory($count)->create([
                    'status' => $status,
                    'title' => fn () => fake()->randomElement($titles).' — '.fake()->words(2, true),
                    'event_id' => fn () => fake()->randomElement($eventIds),
                    'project_id' => fn () => fake()->optional(0.5)->randomElement(Project::pluck('id')->all()),
                    'assignee_id' => fn () => fake()->randomElement($assigneeIds),
                ]);
            }
        }
    }
}
