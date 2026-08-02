<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventTransport;
use App\Models\TransportDriver;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A movement row says what it is and what it still needs — once each.
 *
 * Readiness used to be stated three times in three languages on the same row:
 * a status pill, a line of amber text about the driver, and three dots that
 * needed a tooltip to decode. Nobody scanning forty movements reads dots.
 */
class TransportRowTest extends TestCase
{
    use RefreshDatabase;

    private function movement(array $overrides = []): EventTransport
    {
        $event = Event::factory()->create();

        return EventTransport::create($overrides + [
            'event_id' => $event->id,
            'route' => 'Airport → Fairmont',
            'pickup_from' => 'Airport',
            'drop_to' => 'Fairmont',
            'leg' => 'arrival',
            'status' => 'planned',
            'depart_at' => now()->addDays(3)->setTime(10, 25),
        ]);
    }

    public function test_a_movement_with_nothing_on_it_says_what_it_needs_first(): void
    {
        $chip = $this->movement()->readinessChip();

        $this->assertFalse($chip['ready']);
        $this->assertSame('Needs a driver and a vehicle and passengers', $chip['label']);
        $this->assertSame('0 of 3 ready.', $chip['detail']);
    }

    public function test_the_driver_is_named_first_because_it_is_the_phone_call(): void
    {
        $type = VehicleType::create(['name' => 'Sedan', 'capacity' => 3]);
        $m = $this->movement(['vehicle_type_id' => $type->id, 'passengers' => 2]);

        $this->assertSame('Needs a driver', $m->readinessChip()['label']);
    }

    public function test_a_movement_that_is_set_says_so_plainly(): void
    {
        $type = VehicleType::create(['name' => 'Sedan', 'capacity' => 3]);
        $driver = TransportDriver::create(['name' => 'Sami', 'phone' => '0790000000']);

        $m = $this->movement([
            'vehicle_type_id' => $type->id,
            'driver_id' => $driver->id,
            'passengers' => 2,
        ]);

        $chip = $m->readinessChip();

        $this->assertTrue($chip['ready']);
        $this->assertSame('Ready', $chip['label']);
    }

    /** Two missing things read as a sentence, not as two grey dots. */
    public function test_more_than_one_gap_reads_as_words(): void
    {
        $driver = TransportDriver::create(['name' => 'Sami', 'phone' => '0790000000']);

        $this->assertSame(
            'Needs a vehicle and passengers',
            $this->movement(['driver_id' => $driver->id])->readinessChip()['label'],
        );
    }
}
