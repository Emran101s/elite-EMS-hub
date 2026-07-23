<?php

namespace Tests\Feature;

use App\Livewire\TransportDispatch;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\TransportDriver;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The dispatch board. Its whole reason to exist is catching a double-booked
 * resource, so that is what the tests hammer — plus that a drag reassigns and
 * that reassignment resolves the clash it was drawn to show.
 */
class TransportDispatchTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create(['role' => 'super_admin']);
        $this->event = Event::create([
            'name' => 'Dispatch Summit', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
        VehicleType::create(['name' => 'Van', 'capacity' => 7, 'is_active' => true, 'position' => 1]);
    }

    private function movement(string $depart, string $arrive, array $extra = []): EventTransport
    {
        // array_merge, not +, so $extra actually overrides the defaults.
        return $this->event->transport()->create(array_merge([
            'type' => 'van', 'leg' => 'arrival', 'vehicles' => 1, 'route' => 'A → B',
            'status' => 'confirmed', 'depart_at' => $depart, 'arrive_at' => $arrive,
        ], $extra));
    }

    private function board()
    {
        return Livewire::actingAs($this->user)->test(TransportDispatch::class, ['event' => $this->event]);
    }

    // ── conflict detection (the model) ──────────────────────────

    public function test_two_overlapping_runs_on_one_driver_clash(): void
    {
        $driver = TransportDriver::create(['name' => 'Khaled Mansour']);
        $a = $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $driver->id]);
        $b = $this->movement('2026-07-27 08:30', '2026-07-27 09:30', ['driver_id' => $driver->id]);

        $conflicts = EventTransport::conflicts($this->event->transport()->get(), 'driver');
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $conflicts);
    }

    public function test_back_to_back_runs_do_not_clash(): void
    {
        $driver = TransportDriver::create(['name' => 'Khaled Mansour']);
        $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $driver->id]);
        $this->movement('2026-07-27 09:00', '2026-07-27 10:00', ['driver_id' => $driver->id]);

        $this->assertSame([], EventTransport::conflicts($this->event->transport()->get(), 'driver'),
            'touching at the boundary is not an overlap');
    }

    public function test_the_same_times_on_different_drivers_are_fine(): void
    {
        $a = TransportDriver::create(['name' => 'One']);
        $b = TransportDriver::create(['name' => 'Two']);
        $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $a->id]);
        $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $b->id]);

        $this->assertSame([], EventTransport::conflicts($this->event->transport()->get(), 'driver'));
    }

    public function test_a_run_with_no_end_falls_back_to_an_hour(): void
    {
        // Only a departure — the board still reserves a sensible window.
        $run = $this->event->transport()->create([
            'type' => 'van', 'leg' => 'arrival', 'vehicles' => 1, 'route' => 'A → B',
            'status' => 'confirmed', 'depart_at' => '2026-07-27 08:00',
        ]);

        $this->assertSame('09:00', $run->estimatedEnd()->format('H:i'));
    }

    public function test_a_cancelled_run_never_clashes(): void
    {
        $driver = TransportDriver::create(['name' => 'Khaled Mansour']);
        $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $driver->id]);
        $this->movement('2026-07-27 08:30', '2026-07-27 09:30', ['driver_id' => $driver->id, 'status' => 'cancelled']);

        $this->assertSame([], EventTransport::conflicts($this->event->transport()->get(), 'driver'),
            'a cancelled run is not really booked');
    }

    // ── the board & reassignment ────────────────────────────────

    public function test_the_board_lanes_only_the_resources_actually_used_that_day(): void
    {
        $used = TransportDriver::create(['name' => 'Working Today']);
        TransportDriver::create(['name' => 'Idle Today']);
        $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $used->id]);
        $this->movement('2026-07-27 10:00', '2026-07-27 11:00');   // unassigned

        $board = $this->board()->instance()->board();

        $this->assertCount(1, $board['lanes'], 'only the driver with a run gets a lane');
        $this->assertSame('Working Today', $board['lanes']->first()['label']);
        $this->assertCount(1, $board['unassigned']);
    }

    public function test_dragging_a_run_onto_a_driver_assigns_it_and_reassigning_clears_the_clash(): void
    {
        $khaled = TransportDriver::create(['name' => 'Khaled']);
        $rami = TransportDriver::create(['name' => 'Rami']);

        $a = $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $khaled->id]);
        $b = $this->movement('2026-07-27 08:30', '2026-07-27 09:30', ['driver_id' => $khaled->id]);

        $c = $this->board();
        $this->assertCount(2, $c->instance()->board()['conflictIds'], 'both clash on Khaled');

        // Drag B onto Rami — the clash is gone.
        $c->call('reassign', $b->id, (string) $rami->id);
        $this->assertSame($rami->id, $b->fresh()->driver_id);
        $this->assertSame([], $c->instance()->board()['conflictIds'], 'no two runs share a driver now');
    }

    public function test_a_run_dropped_on_the_unassigned_lane_loses_its_driver(): void
    {
        $khaled = TransportDriver::create(['name' => 'Khaled']);
        $run = $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $khaled->id]);

        $this->board()->call('reassign', $run->id, 'none');
        $this->assertNull($run->fresh()->driver_id);
    }

    public function test_grouping_by_vehicle_reassigns_the_vehicle_not_the_driver(): void
    {
        $van = TransportVehicle::create(['plate_no' => 'PLT 1']);
        $run = $this->movement('2026-07-27 08:00', '2026-07-27 09:00');

        $this->board()->call('setGroupBy', 'vehicle')->call('reassign', $run->id, (string) $van->id);

        $this->assertSame($van->id, $run->fresh()->vehicle_id);
        $this->assertNull($run->fresh()->driver_id, 'driver untouched when dispatching by vehicle');
    }

    public function test_a_read_only_user_cannot_reassign(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $driver = TransportDriver::create(['name' => 'Khaled']);
        $run = $this->movement('2026-07-27 08:00', '2026-07-27 09:00');

        Livewire::actingAs($viewer)->test(TransportDispatch::class, ['event' => $this->event])
            ->call('reassign', $run->id, (string) $driver->id)
            ->assertForbidden();

        $this->assertNull($run->fresh()->driver_id);
    }

    public function test_the_board_page_renders(): void
    {
        $driver = TransportDriver::create(['name' => 'Khaled Mansour']);
        $this->movement('2026-07-27 08:00', '2026-07-27 09:00', ['driver_id' => $driver->id]);
        $this->movement('2026-07-27 08:30', '2026-07-27 09:30', ['driver_id' => $driver->id]);

        $this->actingAs($this->user)
            ->get(route('events.transport.dispatch', $this->event))
            ->assertOk()
            ->assertSee('Dispatch Board')
            ->assertSee('Double-booked')
            ->assertSee('2 conflicts');
    }
}
