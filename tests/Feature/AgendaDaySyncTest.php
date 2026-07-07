<?php

namespace Tests\Feature;

use App\Livewire\EventCreate;
use App\Livewire\Hub\SettingsTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Database\Seeders\EventAvatarSeeder;
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

    public function test_wizard_creates_agenda_days_from_date_range(): void
    {
        $this->seed(EventAvatarSeeder::class);
        $user = User::factory()->create();

        Livewire::actingAs($user)->test(EventCreate::class)
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
