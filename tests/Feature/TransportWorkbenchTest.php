<?php

namespace Tests\Feature;

use App\Livewire\Hub\TransportationTab;
use App\Livewire\TransportDispatch;
use App\Livewire\TransportLive;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Transport workbench as a person actually works it.
 *
 * Status used to move only inside the edit form, so Planned → Ordered →
 * Confirmed meant opening every run. Live owns the show-day steps; the list
 * now advances the planning ones. Empty Live/Dispatch boards also needed a
 * way back to the List rather than a dead end. docs/19 §3.2 B4.
 */
class TransportWorkbenchTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-07-27 09:00:00');

        $this->user = User::factory()->create(['role' => 'manager']);
        $this->event = Event::create([
            'name' => 'Workbench Summit', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);

        VehicleType::create(['name' => 'Van', 'capacity' => 7, 'is_active' => true, 'position' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function movement(string $status = 'planned'): EventTransport
    {
        return $this->event->transport()->create([
            'type' => 'van', 'leg' => 'arrival', 'vehicles' => 1,
            'route' => 'QAIA → Hotel', 'pickup_from' => 'Queen Alia Airport', 'drop_to' => 'Fairmont',
            'status' => $status, 'depart_at' => '2026-07-27 11:00:00',
        ]);
    }

    public function test_the_list_advances_planning_status_without_opening_the_form(): void
    {
        $m = $this->movement('planned');

        Livewire::actingAs($this->user)->test(TransportationTab::class, ['event' => $this->event])
            ->assertSee('Mark Ordered')
            ->call('advanceStatus', $m->id);

        $this->assertSame('ordered', $m->fresh()->status);

        Livewire::actingAs($this->user)->test(TransportationTab::class, ['event' => $this->event])
            ->assertSee('Mark Confirmed')
            ->call('advanceStatus', $m->id);

        $this->assertSame('confirmed', $m->fresh()->status);
    }

    public function test_a_confirmed_run_does_not_offer_a_planning_step(): void
    {
        $m = $this->movement('confirmed');

        $this->assertNull($m->nextPlanningStatus());

        Livewire::actingAs($this->user)->test(TransportationTab::class, ['event' => $this->event])
            ->assertDontSee('Mark Ordered')
            ->assertDontSee('Mark Confirmed')
            ->call('advanceStatus', $m->id);

        $this->assertSame('confirmed', $m->fresh()->status, 'Live owns the next step; the list must not invent one');
    }

    public function test_planned_status_is_visible_on_the_row(): void
    {
        $this->movement('planned');

        Livewire::actingAs($this->user)->test(TransportationTab::class, ['event' => $this->event])
            ->assertSee('Planned');
    }

    public function test_not_ready_count_is_visible_in_transport_control(): void
    {
        $this->movement('planned');

        Livewire::actingAs($this->user)->test(TransportationTab::class, ['event' => $this->event])
            ->assertSee('Not ready')
            ->assertSee('Missing driver, vehicle or passengers');
    }

    public function test_an_empty_live_board_offers_the_list(): void
    {
        Livewire::actingAs($this->user)->test(TransportLive::class, ['event' => $this->event])
            ->assertSee('Nothing moving on this day')
            ->assertSee('Open the List');
    }

    public function test_an_empty_dispatch_board_offers_the_list(): void
    {
        Livewire::actingAs($this->user)->test(TransportDispatch::class, ['event' => $this->event])
            ->assertSee('Nothing scheduled on this day')
            ->assertSee('Open the List');
    }
}
