<?php

namespace Tests\Feature;

use App\Livewire\EventCreate;
use App\Livewire\Hub\AgendaTab;
use App\Livewire\Hub\SettingsTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaDaySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_day_count_is_inclusive(): void
    {
        $event = new Event(['starts_at' => '2026-10-19', 'ends_at' => '2026-10-21']);
        $this->assertSame(3, $event->dayCount());

        $single = new Event(['starts_at' => '2026-10-19']);
        $this->assertSame(1, $single->dayCount());
    }

    public function test_sync_orders_by_date_dedupes_and_extends_the_range(): void
    {
        $event = Event::create([
            'name' => 'Ordering Test', 'type' => 'workshop', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-28',
        ]);
        // Out-of-order + a duplicate empty day, mimicking the reported mess.
        $d27 = $event->agendaDays()->create(['date' => '2026-07-27', 'label' => 'Day One', 'sort' => 1]);
        $event->agendaDays()->create(['date' => '2026-07-26', 'label' => 'Day 1', 'sort' => 2]);
        $event->agendaDays()->create(['date' => '2026-07-28', 'label' => 'Day Two', 'sort' => 3]);
        $event->agendaDays()->create(['date' => '2026-07-28', 'label' => 'Dup', 'sort' => 4]); // empty duplicate
        $d27->sessions()->create(['event_id' => $event->id, 'title' => 'Keep', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '09:00', 'ends_at' => '10:00', 'sort' => 0]);

        // Extend the range to 26–29 and re-sync.
        $event->update(['starts_at' => '2026-07-26', 'ends_at' => '2026-07-29']);
        $event->refresh()->syncAgendaDays();

        $days = $event->agendaDays()->get();
        // Duplicate empty 28th gone; a 29th created → one day per date, 26–29.
        $this->assertSame(['2026-07-26', '2026-07-27', '2026-07-28', '2026-07-29'],
            $days->map(fn ($d) => $d->date->format('Y-m-d'))->all());
        // sort now strictly follows date.
        $this->assertSame([1, 2, 3, 4], $days->pluck('sort')->all());
    }

    public function test_custom_session_type_is_stored_as_a_slug(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        $event = Event::create([
            'name' => 'Types Test', 'type' => 'workshop', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-27',
        ]);
        $event->syncAgendaDays();
        $day = $event->agendaDays()->first();

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession', $day->id)
            ->set('title', 'Stage build')
            ->set('type', 'Art Setup')       // a custom label
            ->set('starts_at', '08:00')->set('ends_at', '10:00')
            ->call('saveSession')
            ->assertHasNoErrors();

        $this->assertSame('art_setup', $event->agendaSessions()->where('title', 'Stage build')->value('type'));
    }

    public function test_wizard_creates_agenda_days_from_date_range(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->call('chooseCategory', 'conference')
            ->set('originKind', 'commercial')
            ->set('originSource', 'deal')
            ->set('new_client', 'Range Co')
            ->set('name', 'Four Day Congress')
            ->set('starts_at', '2027-04-01')
            ->set('ends_at', '2027-04-04')
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('name', 'Four Day Congress')->firstOrFail();
        $this->assertSame(4, $event->agendaDays()->count());
        $this->assertEquals(['2027-04-01', '2027-04-02', '2027-04-03', '2027-04-04'],
            $event->agendaDays()->orderBy('sort')->pluck('date')->map->format('Y-m-d')->all());
    }

    public function test_extending_dates_in_settings_adds_days_without_touching_existing(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail(); // 2 days, custom labels

        $customLabel = $event->agendaDays()->orderBy('sort')->first()->label;

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->set('ends_at', '2026-10-22') // extend from Oct 20 → Oct 22 (adds 2 days)
            ->call('save')
            ->assertHasNoErrors();

        $event->refresh();
        $this->assertSame(4, $event->agendaDays()->count());
        // The original custom label is preserved.
        $this->assertSame($customLabel, $event->agendaDays()->orderBy('sort')->first()->label);
    }

    public function test_sync_is_capped_against_runaway_ranges(): void
    {
        $event = Event::factory()->create(['starts_at' => '2027-01-01', 'ends_at' => '2030-01-01']);
        $event->syncAgendaDays();

        $this->assertSame(60, $event->agendaDays()->count());
    }
}
