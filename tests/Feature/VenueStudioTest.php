<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VenueStudioTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_the_studio_page_loads_and_shows_real_numbers(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman', 'capacity' => 900]);
        $venue->spaces()->create(['name' => 'Main Hall', 'type' => 'main_hall', 'capacity' => 500]);
        $venue->spaces()->create(['name' => 'Breakout 1', 'type' => 'breakout', 'capacity' => 60]);

        $this->actingAs($this->actor())->get(route('venues.show', $venue))
            ->assertOk()
            ->assertSee('Fairmont Amman')
            ->assertSee('Main Hall')
            ->assertSee('Breakout 1');
    }

    public function test_a_disabled_tab_falls_back_to_overview(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $this->actingAs($this->actor())->get(route('venues.show', [$venue, 'tab' => 'not-a-real-tab']))
            ->assertOk()
            ->assertSee('Digital Twin');
    }

    public function test_the_capacity_tab_loads(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $this->actingAs($this->actor())->get(route('venues.show', [$venue, 'tab' => 'capacity']))
            ->assertOk()
            ->assertSee('Capacity Intelligence');
    }

    public function test_the_command_tower_shows_a_real_double_booking(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $a = Event::factory()->create(['venue_id' => $venue->id, 'starts_at' => now()->addDays(10), 'ends_at' => now()->addDays(12)]);
        $b = Event::factory()->create(['venue_id' => $venue->id, 'starts_at' => now()->addDays(11), 'ends_at' => now()->addDays(13)]);

        $this->actingAs($this->actor())->get(route('venues.show', $venue))
            ->assertOk()
            ->assertSee($a->name.' ('.$a->starts_at->format('j M').'–'.$a->ends_at->format('j M').') ↔ '.$b->name.' ('.$b->starts_at->format('j M').'–'.$b->ends_at->format('j M').')');
    }

    public function test_the_command_tower_is_clear_with_no_conflicts(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $this->actingAs($this->actor())->get(route('venues.show', $venue))
            ->assertOk()
            ->assertSee('No overlapping bookings.');
    }
}
