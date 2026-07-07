<?php

namespace Tests\Feature;

use App\Livewire\Hub\SettingsTab;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_settings_form_loads_current_values(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->assertSet('name', 'ICFT 2026')
            ->assertSet('expected_participants', (string) $event->expected_participants)
            ->assertSet('venue_id', $event->venue_id);
    }

    public function test_saving_updates_participants_pm_venue_and_budget(): void
    {
        [$event, $user] = $this->ctx();
        $pm = User::where('name', 'Omar Nassar')->firstOrFail();
        $venue = Venue::where('name', 'Doha Exhibition Center')->firstOrFail();

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->set('expected_participants', '850')
            ->set('project_manager_id', $pm->id)
            ->set('venue_id', $venue->id)
            ->set('budget', '999000')
            ->set('city', 'Aqaba')
            ->call('save')
            ->assertHasNoErrors();

        $event->refresh();
        $this->assertSame(850, $event->expected_participants);
        $this->assertSame($pm->id, $event->project_manager_id);
        $this->assertSame($venue->id, $event->venue_id);
        $this->assertSame(99900000, $event->budget_cents);
        $this->assertSame('Aqaba', $event->city);
        // PM also joined the team roster.
        $this->assertTrue($event->teamMembers()->whereKey($pm->id)->wherePivot('role', 'project_manager')->exists());
    }

    public function test_module_toggle_persists_and_gates_tabs(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->call('toggleModule', 'sponsors') // ICFT legacy = all on; turn sponsors off
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNotContains('sponsors', $event->fresh()->enabled_modules);
    }

    public function test_archive_from_settings(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->call('archive')
            ->assertRedirect(route('events.index'));

        $this->assertNotNull($event->fresh()->archived_at);
    }
}
