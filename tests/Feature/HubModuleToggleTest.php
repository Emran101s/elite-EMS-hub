<?php

namespace Tests\Feature;

use App\Livewire\EventCreate;
use App\Livewire\Hub\SettingsTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class HubModuleToggleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User} */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_turning_a_module_on_persists_without_saving_the_whole_form(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['tasks', 'venue']]);

        $this->assertFalse($event->fresh()->moduleEnabled('attendees'));

        // Toggle only — no call to save(). This is the exact flow that used to
        // discard the change, leaving the module missing from the hub.
        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->call('toggleModule', 'attendees');

        $this->assertTrue($event->fresh()->moduleEnabled('attendees'),
            'a module switched on must survive without pressing Save');
    }

    public function test_turning_a_module_off_persists_too(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['tasks', 'venue', 'attendees']]);

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->call('toggleModule', 'attendees');

        $this->assertFalse($event->fresh()->moduleEnabled('attendees'));
    }

    public function test_toggling_keeps_the_hub_nav_order_stable(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['venue', 'tasks']]);

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->call('toggleModule', 'brief');

        // Stored in HUB_MODULES order, not the order they happened to be clicked.
        $stored = $event->fresh()->enabled_modules;
        $expected = array_values(array_intersect(array_keys(Event::HUB_MODULES), $stored));
        $this->assertSame($expected, $stored);
    }

    public function test_an_unknown_module_key_is_ignored(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['tasks']]);

        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->call('toggleModule', 'not_a_module');

        $this->assertSame(['tasks'], $event->fresh()->enabled_modules);
    }

    public function test_a_viewer_cannot_toggle_modules(): void
    {
        [$event] = $this->ctx();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'v@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);
        $event->update(['enabled_modules' => ['tasks']]);

        Livewire::actingAs($viewer)->test(SettingsTab::class, ['event' => $event])
            ->call('toggleModule', 'attendees')
            ->assertForbidden();

        $this->assertSame(['tasks'], $event->fresh()->enabled_modules);
    }

    public function test_every_event_type_offers_attendees_from_the_start(): void
    {
        // Every event has people attending — no template should omit it.
        foreach (EventCreate::TEMPLATES as $key => [$label, $type, $icon, $modules]) {
            $this->assertContains('attendees', $modules,
                "the {$label} template must pre-enable attendees");
        }
    }

    public function test_every_module_is_offered_for_toggling_on_every_event(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['tasks']]);

        $offered = Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->viewData('hubModules');

        // The panel lists the full catalogue, not just what is currently on —
        // otherwise a module switched off could never be switched back.
        $this->assertSame(array_keys(Event::HUB_MODULES), array_keys($offered));
    }
}
