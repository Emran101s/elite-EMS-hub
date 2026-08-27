<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueExhibitionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_it_rolls_up_halls_and_booth_sales_across_events(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $event = Event::factory()->create(['venue_id' => $venue->id, 'currency' => 'USD']);
        $hall = $event->exhibitionHalls()->create(['name' => 'Hall A']);

        $exhibitor = $event->exhibitors()->create([
            'company' => 'Acme Corp', 'status' => 'paid', 'fee_cents' => 250000,
        ]);
        $hall->booths()->create(['event_id' => $event->id, 'number' => 'B1', 'price_cents' => 250000,
            'exhibitor_id' => $exhibitor->id, 'x' => 0, 'y' => 0, 'w_m' => 3, 'h_m' => 3]);
        $hall->booths()->create(['event_id' => $event->id, 'number' => 'B2', 'price_cents' => 150000,
            'x' => 0, 'y' => 0, 'w_m' => 3, 'h_m' => 3]);

        $this->actingAs($this->actor())
            ->get(route('venues.show', [$venue, 'tab' => 'exhibition']))
            ->assertOk()
            ->assertSee('Hall A')
            ->assertSee($event->name)
            ->assertSee('2 booths')
            ->assertSee('1 sold')
            ->assertSee('$2,500'); // sold booth only — the unsold B2 doesn't count
    }

    public function test_a_venue_with_no_exhibition_history_reads_empty(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $this->actingAs($this->actor())
            ->get(route('venues.show', [$venue, 'tab' => 'exhibition']))
            ->assertOk()
            ->assertSee('No exhibition halls yet');
    }

    public function test_a_hall_at_a_different_venue_is_not_shown(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $otherVenue = Venue::create(['name' => 'Dead Sea Resort', 'city' => 'Dead Sea']);
        $otherEvent = Event::factory()->create(['venue_id' => $otherVenue->id]);
        $otherEvent->exhibitionHalls()->create(['name' => 'Hall Z']);

        $this->actingAs($this->actor())
            ->get(route('venues.show', [$venue, 'tab' => 'exhibition']))
            ->assertOk()
            ->assertDontSee('Hall Z');
    }
}
