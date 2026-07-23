<?php

namespace Tests\Feature;

use App\Livewire\Hub\TransportationTab;
use App\Models\Event;
use App\Models\TransportServiceType;
use App\Models\User;
use App\Models\VehicleType;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The right-edge docks. Controls moved off the page into a panel, so the two
 * things worth pinning are that its actions still work from in there, and that
 * the primary action did not disappear behind a collapsed spine.
 */
class DockTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User} */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        VehicleType::ensureSeeded();
        TransportServiceType::ensureSeeded();

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    /** The rail's summary and export only render once there is something to show. */
    private function movement(Event $event, int $vehicles = 1): void
    {
        $event->transport()->create([
            'type' => 'shuttle',
            'vehicle_type_id' => VehicleType::where('name', 'Regular Van')->value('id'),
            'vehicles' => $vehicles,
            'route' => 'Airport → Hotel',
            'depart_at' => '2026-10-17 14:20',
            'capacity' => 7 * $vehicles,
            'status' => 'ordered',
        ]);
    }

    public function test_controls_are_docked_and_their_actions_still_work(): void
    {
        [$event, $user] = $this->ctx();
        $this->movement($event);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->assertSee('Transport Controls')
            ->assertSee('Vehicles required', false);

        // The dock keeps the rail inside this component, so wire:click still
        // reaches it — no cross-component event plumbing to go stale.
        $c->call('newItem')->assertSet('showForm', true);
    }

    public function test_the_primary_action_is_on_the_page_not_only_in_the_dock(): void
    {
        [$event, $user] = $this->ctx();
        $this->movement($event);

        $html = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])->html();

        // Once in the always-visible action bar, once mirrored in the dock.
        $this->assertSame(2, substr_count($html, '＋ Add Movement'),
            'the Add button must stay on the page as well as in the dock');

        // The exports are reachable without opening anything: an Export control in
        // the action bar, offering each document by name.
        $this->assertStringContainsString('↧ Export', $html);
        foreach (['Daily Schedule', 'Vehicle Manifest', 'Driver Trip Sheets', 'VIP Transfer Sheets'] as $doc) {
            $this->assertStringContainsString($doc, $html, "{$doc} is offered in the export menu");
        }
    }

    public function test_both_docks_stack_without_overlapping(): void
    {
        [$event, $user] = $this->ctx();

        $html = $this->actingAs($user)->get(route('events.hub', $event).'?tab=transportation')
            ->assertOk()->getContent();

        $this->assertStringContainsString("dock.toggle('controls')", $html);
        $this->assertStringContainsString("dock.toggle('documents')", $html);

        // Spines are offset by a full spine height, so they cannot sit on top
        // of each other however many a tab registers.
        preg_match_all('/top: calc\(50% \+ (\d+)px - 52px\)/', $html, $m);
        $offsets = array_map('intval', $m[1]);

        $this->assertSame([0, 104], $offsets);
        $this->assertSame(count($offsets), count(array_unique($offsets)));
    }

    public function test_the_docked_summary_reflects_real_numbers(): void
    {
        [$event, $user] = $this->ctx();
        $this->movement($event, 2);

        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event]);

        // The fleet count the supplier is given, rendered inside the dock.
        $this->assertSame(2, $c->viewData('fleet')['Regular Van']['vehicles']);
        $c->assertSee('Regular Van');
    }
}
