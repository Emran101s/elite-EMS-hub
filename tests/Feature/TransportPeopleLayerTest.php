<?php

namespace Tests\Feature;

use App\Livewire\Hub\TransportationTab;
use App\Livewire\TransportSettings;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\Supplier;
use App\Models\TransportDriver;
use App\Models\TransportVehicle;
use App\Models\User;
use App\Models\VehicleType;
use App\Support\WhatsApp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The people layer: drivers, specific vehicles and suppliers as records rather
 * than free text — and the things that only become possible once they are.
 */
class TransportPeopleLayerTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create([
            'name' => 'People Layer Summit', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    // ── driver hours ────────────────────────────────────────────

    public function test_a_driver_working_past_midnight_is_flagged_as_overloaded(): void
    {
        $event = $this->event();
        $driver = TransportDriver::create(['name' => 'Khaled Mansour', 'phone' => '+962 79 555 0111']);

        // 06:00 airport run through to a 19:30 gala drop — 13h 30m on duty.
        $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'QAIA → Hotel', 'leg' => 'arrival',
            'status' => 'confirmed', 'driver_id' => $driver->id,
            'depart_at' => '2026-07-27 06:00', 'arrive_at' => '2026-07-27 07:00']);
        $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Hotel → Gala', 'leg' => 'other',
            'status' => 'confirmed', 'driver_id' => $driver->id,
            'depart_at' => '2026-07-27 18:30', 'arrive_at' => '2026-07-27 19:30']);

        $this->assertSame(13 * 60 + 30, $driver->dutyMinutes('2026-07-27'));
        $this->assertSame('13h 30m', TransportDriver::readableMinutes($driver->dutyMinutes('2026-07-27')));
        $this->assertTrue($driver->isOverloadedOn('2026-07-27'));

        // A different day is a different shift.
        $this->assertSame(0, $driver->dutyMinutes('2026-07-28'));
        $this->assertFalse($driver->isOverloadedOn('2026-07-28'));
    }

    public function test_duty_hours_never_go_negative_on_an_arrival_run(): void
    {
        $event = $this->event();
        $driver = TransportDriver::create(['name' => 'Khaled Mansour']);

        // On an arrival, arrive_at is the flight's LANDING — which is routinely
        // before the car's own departure. Naively "last minus first" goes negative.
        $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'QAIA → Hotel',
            'leg' => 'arrival', 'status' => 'confirmed', 'driver_id' => $driver->id,
            'depart_at' => '2026-07-27 15:00', 'arrive_at' => '2026-07-27 14:30']);

        $minutes = $driver->dutyMinutes('2026-07-27');
        $this->assertGreaterThanOrEqual(0, $minutes, 'duty time is never negative');
        $this->assertSame(30, $minutes, 'earliest to latest, whichever field they came from');
        $this->assertFalse($driver->isOverloadedOn('2026-07-27'));
    }

    public function test_a_cancelled_run_does_not_count_against_a_drivers_hours(): void
    {
        $event = $this->event();
        $driver = TransportDriver::create(['name' => 'Rami Odeh']);

        $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Morning', 'leg' => 'arrival',
            'status' => 'confirmed', 'driver_id' => $driver->id,
            'depart_at' => '2026-07-27 09:00', 'arrive_at' => '2026-07-27 10:00']);
        $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Late, called off', 'leg' => 'other',
            'status' => 'cancelled', 'driver_id' => $driver->id,
            'depart_at' => '2026-07-27 23:00', 'arrive_at' => '2026-07-28 00:30']);

        $this->assertSame(60, $driver->dutyMinutes('2026-07-27'), 'the cancelled run is not work');
        $this->assertFalse($driver->isOverloadedOn('2026-07-27'));
    }

    // ── deletion semantics ──────────────────────────────────────

    public function test_deleting_a_driver_or_vehicle_leaves_the_trip_standing(): void
    {
        $event = $this->event();
        $driver = TransportDriver::create(['name' => 'Temporary Hire']);
        $car = TransportVehicle::create(['plate_no' => 'PLT 4471', 'model' => 'Mercedes V-Class']);

        $trip = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'QAIA → Hotel',
            'leg' => 'arrival', 'status' => 'confirmed', 'driver_id' => $driver->id, 'vehicle_id' => $car->id]);

        $driver->delete();
        $car->delete();

        // The trip is the record; the assignment was only a plan.
        $this->assertNotNull($trip->fresh(), 'the trip survives');
        $this->assertNull($trip->fresh()->driver_id);
        $this->assertNull($trip->fresh()->vehicle_id);
        $this->assertSame('QAIA → Hotel', $trip->fresh()->route);
    }

    // ── readiness ───────────────────────────────────────────────

    public function test_readiness_is_computed_and_names_what_is_missing(): void
    {
        $event = $this->event();
        $van = VehicleType::create(['name' => 'Van', 'capacity' => 7, 'is_active' => true, 'position' => 1]);
        $driver = TransportDriver::create(['name' => 'Khaled Mansour']);

        $trip = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'QAIA → Hotel',
            'leg' => 'arrival', 'status' => 'planned', 'vehicle_type_id' => $van->id]);

        $r = $trip->readiness();
        $this->assertTrue($r['vehicle']);
        $this->assertFalse($r['driver']);
        $this->assertFalse($r['passengers']);
        $this->assertSame(1, $r['score']);
        $this->assertSame(['driver', 'passengers'], $r['missing']);
        $this->assertFalse($trip->isReady());

        $trip->update(['driver_id' => $driver->id]);
        $trip->manifest()->create(['event_id' => $event->id, 'name' => 'Layla Odeh', 'position' => 1]);

        $this->assertTrue($trip->fresh()->isReady());
        $this->assertSame(3, $trip->fresh()->readiness()['score']);
    }

    // ── VIP promotion ───────────────────────────────────────────

    public function test_a_vip_passenger_promotes_the_whole_trip(): void
    {
        $event = $this->event();
        $trip = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'QAIA → Hotel',
            'leg' => 'arrival', 'status' => 'confirmed']);

        $this->assertFalse($trip->isPriority(), 'an ordinary run is not priority');

        $trip->manifest()->create(['event_id' => $event->id, 'name' => 'Ordinary Delegate',
            'category' => 'delegate', 'position' => 1]);
        $this->assertFalse($trip->fresh()->isPriority());

        // Nobody should have to remember to tick a box after dropping a VIP on a van.
        $trip->manifest()->create(['event_id' => $event->id, 'name' => 'Dr Al-Rashid',
            'category' => 'vip', 'position' => 2]);

        $fresh = $trip->fresh();
        $this->assertTrue($fresh->isPriority());
        $this->assertStringContainsString('Dr Al-Rashid', $fresh->priorityReason());

        // And it can still be set by hand for a run with no names on it yet.
        $empty = $event->transport()->create(['type' => 'sedan', 'vehicles' => 1, 'route' => 'Chairman car',
            'leg' => 'other', 'status' => 'planned', 'is_vip' => true]);
        $this->assertTrue($empty->isPriority());
        $this->assertSame('Marked as a VIP movement', $empty->priorityReason());
    }

    // ── statuses ────────────────────────────────────────────────

    public function test_the_seven_statuses_each_carry_a_label_and_a_colour(): void
    {
        $this->assertCount(7, EventTransport::STATUSES);

        foreach (EventTransport::STATUSES as $status) {
            $this->assertArrayHasKey($status, EventTransport::STATUS_META, "{$status} has meta");
            $tone = EventTransport::STATUS_META[$status]['tone'];
            $this->assertArrayHasKey($tone, EventTransport::STATUS_CLASSES, "{$tone} has classes");
        }

        $trip = $this->event()->transport()->create(['type' => 'van', 'vehicles' => 1,
            'route' => 'X', 'leg' => 'other', 'status' => 'in_progress']);

        $this->assertSame('In progress', $trip->statusLabel());
        $this->assertStringContainsString('sky', $trip->statusClass());
        $this->assertFalse($trip->isSettled());
        $this->assertTrue($trip->fill(['status' => 'completed'])->isSettled());
    }

    // ── supplier ────────────────────────────────────────────────

    public function test_a_trip_reports_its_supplier_from_the_link_or_the_old_free_text(): void
    {
        $event = $this->event();
        $supplier = Supplier::create(['name' => 'Elite Fleet Co', 'category' => 'transport']);

        $linked = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'A', 'leg' => 'other',
            'status' => 'planned', 'supplier_id' => $supplier->id]);
        $legacy = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'B', 'leg' => 'other',
            'status' => 'planned', 'provider' => 'Some Older Vendor']);

        $this->assertSame('Elite Fleet Co', $linked->supplierName());
        $this->assertSame('Some Older Vendor', $legacy->supplierName(), 'the string still works until it is retired');
    }

    // ── WhatsApp ────────────────────────────────────────────────

    public function test_whatsapp_links_are_built_without_any_integration(): void
    {
        $event = $this->event();
        $driver = TransportDriver::create(['name' => 'Khaled Mansour', 'phone' => '+962 79 555 0111']);

        $trip = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'QAIA → Fairmont',
            'leg' => 'arrival', 'status' => 'confirmed', 'driver_id' => $driver->id,
            'pickup_from' => 'Queen Alia Airport', 'drop_to' => 'Fairmont Amman',
            'depart_at' => '2026-07-27 14:00', 'flight_no' => 'RJ 100']);
        $trip->manifest()->create(['event_id' => $event->id, 'name' => 'Layla Odeh', 'position' => 1]);

        $link = WhatsApp::toDriver($trip->fresh());
        $this->assertStringStartsWith('https://wa.me/962795550111?text=', $link);

        $text = rawurldecode(explode('?text=', $link)[1]);
        $this->assertStringContainsString('Car #1', $text);
        $this->assertStringContainsString('Queen Alia Airport', $text);
        $this->assertStringContainsString('RJ 100', $text);
        $this->assertStringContainsString('14:00', $text);

        // No number, no link — rather than a broken one.
        $noPhone = TransportDriver::create(['name' => 'Unreachable']);
        $trip->update(['driver_id' => $noPhone->id, 'driver_contact' => null]);
        $this->assertNull(WhatsApp::toDriver($trip->fresh()));
    }

    public function test_a_delay_moves_the_departure_without_losing_the_original(): void
    {
        $trip = $this->event()->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'X',
            'leg' => 'arrival', 'status' => 'confirmed', 'depart_at' => '2026-07-27 14:00']);

        $this->assertSame('14:00', $trip->effectiveDeparture()->format('H:i'));

        $trip->update(['delayed_to' => '2026-07-27 16:45']);
        $trip = $trip->fresh();

        $this->assertSame('16:45', $trip->effectiveDeparture()->format('H:i'), 'the delay wins');
        $this->assertSame('14:00', $trip->depart_at->format('H:i'), 'the plan is still on the record');
    }

    // ── the catalogue screen ────────────────────────────────────

    public function test_drivers_and_vehicles_can_be_managed_from_settings(): void
    {
        $user = $this->admin();
        $supplier = Supplier::create(['name' => 'Elite Fleet Co', 'category' => 'transport']);

        $c = Livewire::actingAs($user)->test(TransportSettings::class)
            ->set('newDriver', 'Khaled Mansour')
            ->set('newDriverPhone', '+962 79 555 0111')
            ->set('newDriverSupplier', $supplier->id)
            ->call('addDriver');

        $driver = TransportDriver::firstOrFail();
        $this->assertSame('Khaled Mansour', $driver->name);
        $this->assertSame($supplier->id, $driver->supplier_id);
        $this->assertTrue($driver->is_active);
        $this->assertSame('', $c->instance()->newDriver, 'the box clears');

        $c->call('toggleDriver', $driver->id);
        $this->assertFalse($driver->fresh()->is_active);
        $this->assertSame(0, TransportDriver::active()->count());

        // A car needs something to recognise it by.
        $c->set('newPlate', '')->set('newModel', '')->call('addFleetVehicle')->assertHasErrors('newPlate');
        $this->assertSame(0, TransportVehicle::count());

        $c->set('newPlate', 'PLT 4471')->set('newModel', 'Mercedes V-Class')->call('addFleetVehicle');
        $this->assertSame('Mercedes V-Class · PLT 4471', TransportVehicle::firstOrFail()->label());
    }

    public function test_a_movement_can_be_given_a_driver_a_car_and_a_supplier(): void
    {
        $user = $this->admin();
        $event = $this->event();
        $van = VehicleType::create(['name' => 'Van', 'capacity' => 7, 'is_active' => true, 'position' => 1]);
        $driver = TransportDriver::create(['name' => 'Khaled Mansour', 'is_active' => true]);
        $car = TransportVehicle::create(['plate_no' => 'PLT 4471', 'is_active' => true]);
        $supplier = Supplier::create(['name' => 'Elite Fleet Co', 'category' => 'transport']);

        Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])
            ->call('newItem')
            ->set('leg', 'arrival')
            ->set('vehicle_type_id', $van->id)
            ->set('driver_id', $driver->id)
            ->set('vehicle_id', $car->id)
            ->set('supplier_id', $supplier->id)
            ->set('is_vip', true)
            ->set('pickup_from', 'Queen Alia Airport')
            ->set('drop_to', 'Fairmont Amman')
            ->call('save')
            ->assertHasNoErrors();

        $trip = $event->transport()->firstOrFail();
        $this->assertSame($driver->id, $trip->driver_id);
        $this->assertSame($car->id, $trip->vehicle_id);
        $this->assertSame($supplier->id, $trip->supplier_id);
        $this->assertTrue($trip->is_vip);
        $this->assertTrue($trip->isPriority());

        // And editing loads them back rather than silently clearing them.
        $c = Livewire::actingAs($user)->test(TransportationTab::class, ['event' => $event])->call('edit', $trip->id);
        $this->assertSame($driver->id, $c->instance()->driver_id);
        $this->assertTrue($c->instance()->is_vip);
    }

    public function test_a_vehicle_knows_when_it_is_already_committed(): void
    {
        $event = $this->event();
        $car = TransportVehicle::create(['plate_no' => 'PLT 4471']);

        $booked = $event->transport()->create(['type' => 'van', 'vehicles' => 1, 'route' => 'Morning run',
            'leg' => 'arrival', 'status' => 'confirmed', 'vehicle_id' => $car->id,
            'depart_at' => '2026-07-27 09:00', 'arrive_at' => '2026-07-27 10:00']);

        $this->assertTrue($car->isBusyAt('2026-07-27 09:30', '2026-07-27 09:45'), 'overlapping');
        $this->assertTrue($car->isBusyAt('2026-07-27 08:30', '2026-07-27 09:15'), 'straddling the start');
        $this->assertFalse($car->isBusyAt('2026-07-27 11:00', '2026-07-27 12:00'), 'clear afterwards');

        // Editing that very trip must not report a clash with itself.
        $this->assertFalse($car->isBusyAt('2026-07-27 09:30', '2026-07-27 09:45', $booked->id));
    }
}
