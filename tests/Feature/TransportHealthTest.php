<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\TransportDriver;
use App\Models\TransportVehicle;
use App\Services\EventHealthService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Transport signals inside the Event Health Score: staffed movements and guest
 * coverage lift the score, issues and a full pool drag it — and events that
 * don't use the module are never punished for it.
 */
class TransportHealthTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::create([
            'name' => 'Transport Health Summit', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);
    }

    private function breakdown(Event $event): array
    {
        return app(EventHealthService::class)->breakdown($event->fresh()->load(EventHealthService::RELATIONS));
    }

    public function test_transport_component_is_null_when_the_module_is_unused(): void
    {
        $this->assertNull($this->breakdown($this->event())['components']['transport']);
    }

    public function test_fully_staffed_movements_score_full_marks(): void
    {
        $event = $this->event();
        $driver = TransportDriver::create(['name' => 'Khaled Mansour', 'phone' => '+962 79 555 0111']);
        $vehicle = TransportVehicle::create(['label' => 'Coach 1', 'plate' => 'AMM 1234', 'capacity' => 40]);

        $move = $event->transport()->create([
            'type' => 'coach', 'route' => 'Airport → Hotel', 'status' => 'confirmed',
            'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id, 'passengers' => 12,
        ]);

        $this->assertSame(100, $this->breakdown($event)['components']['transport']);

        // Losing the driver drops a third of the movement's readiness.
        $move->update(['driver_id' => null]);
        $this->assertSame(67, $this->breakdown($event)['components']['transport']);
    }

    public function test_issues_burn_the_score_and_cancelled_movements_are_ignored(): void
    {
        $event = $this->event();
        $event->transport()->create(['type' => 'van', 'route' => 'Run A', 'status' => 'issue', 'passengers' => 4]);
        $event->transport()->create(['type' => 'van', 'route' => 'Run B', 'status' => 'cancelled', 'passengers' => 4]);

        // Only the issue movement counts: flat 20.
        $this->assertSame(20, $this->breakdown($event)['components']['transport']);
    }

    public function test_guest_pool_coverage_blends_into_the_score(): void
    {
        $event = $this->event();
        $driver = TransportDriver::create(['name' => 'Samir Odeh', 'phone' => '+962 79 555 0222']);
        $vehicle = TransportVehicle::create(['label' => 'Sedan 2', 'plate' => 'AMM 5678', 'capacity' => 3]);
        $move = $event->transport()->create([
            'type' => 'sedan', 'route' => 'VIP transfer', 'status' => 'confirmed',
            'driver_id' => $driver->id, 'vehicle_id' => $vehicle->id,
        ]);

        // One guest on the vehicle, one still in the pool → staffing 100, coverage 50.
        $event->transferGuests()->create(['name' => 'Guest Aboard', 'category' => 'delegate', 'transport_id' => $move->id]);
        $event->transferGuests()->create(['name' => 'Guest Waiting', 'category' => 'delegate']);

        $this->assertSame(85, $this->breakdown($event)['components']['transport']);
    }

    public function test_ai_summary_surfaces_transport_signals(): void
    {
        $event = $this->event();
        $event->transport()->create([
            'type' => 'van', 'route' => 'Hotel → Venue', 'status' => 'issue',
            'issue_note' => 'Bus stuck at checkpoint', 'passengers' => 8,
        ]);
        $event->transport()->create(['type' => 'vip', 'route' => 'Royal pickup', 'status' => 'planned', 'is_vip' => true]);
        $event->transferGuests()->create(['name' => 'Pool Guest', 'category' => 'delegate']);

        $attention = implode(' | ', app(EventHealthService::class)
            ->aiSummary($event->fresh()->load(EventHealthService::RELATIONS))['attention']);

        $this->assertStringContainsString('Bus stuck at checkpoint', $attention);
        $this->assertStringContainsString('VIP transfer not ready', $attention);
        $this->assertStringContainsString('still unassigned in the transport pool', $attention);
    }
}
