<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueCapacityIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_a_busy_space_reads_high_demand(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $space = $venue->spaces()->create(['name' => 'Main Hall', 'type' => 'main_hall']);

        // Ten separate 8-day events inside the 90-day window — 80 booked
        // days out of 90, comfortably over the 85% "High demand" threshold.
        for ($i = 0; $i < 10; $i++) {
            $event = Event::factory()->create([
                'venue_id' => $venue->id,
                'starts_at' => now()->subDays(45)->addDays($i * 9),
                'ends_at' => now()->subDays(45)->addDays($i * 9 + 7),
            ]);
            $event->rooms()->create(['name' => 'Main Hall', 'venue_space_id' => $space->id]);
        }

        $this->actingAs($this->actor())
            ->get(route('venues.show', [$venue, 'tab' => 'capacity']))
            ->assertOk()
            ->assertSee('High demand');
    }

    public function test_an_unbooked_space_reads_underused(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $venue->spaces()->create(['name' => 'Storage Room', 'type' => 'breakout']);

        $this->actingAs($this->actor())
            ->get(route('venues.show', [$venue, 'tab' => 'capacity']))
            ->assertOk()
            ->assertSee('Underused')
            ->assertSee('0 of 90 days booked');
    }

    public function test_a_booking_outside_the_venue_studio_link_does_not_count(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $space = $venue->spaces()->create(['name' => 'Main Hall', 'type' => 'main_hall']);

        // A room booked at this venue but never linked to the space — the
        // space's own utilization must stay at zero.
        $event = Event::factory()->create(['venue_id' => $venue->id, 'starts_at' => now(), 'ends_at' => now()->addDays(3)]);
        $event->rooms()->create(['name' => 'Main Hall']);

        $this->actingAs($this->actor())
            ->get(route('venues.show', [$venue, 'tab' => 'capacity']))
            ->assertOk()
            ->assertSee('0 of 90 days booked');
    }
}
