<?php

namespace Tests\Feature;

use App\Livewire\VenueStudio\OverviewTab;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Services\VenueCommandHeader;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueReadinessModeTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_the_overview_tab_defaults_to_occupancy_mode(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        Livewire::actingAs($this->actor())->test(OverviewTab::class, ['venue' => $venue])
            ->assertSet('mode', 'occupancy');
    }

    public function test_switching_to_readiness_mode_colors_by_documentation_completeness(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $venue->spaces()->create(['name' => 'Missing Capacity', 'type' => 'breakout']);
        $venue->spaces()->create(['name' => 'Missing Dimensions', 'type' => 'breakout',
            'capacity_by_setup' => ['theater' => 100]]);
        $venue->spaces()->create(['name' => 'Never Booked', 'type' => 'breakout',
            'capacity_by_setup' => ['theater' => 100], 'width_m' => 10, 'length_m' => 8]);

        $fullyBooked = $venue->spaces()->create(['name' => 'Fully Booked', 'type' => 'breakout',
            'capacity_by_setup' => ['theater' => 100], 'width_m' => 10, 'length_m' => 8]);
        $event = Event::factory()->create(['venue_id' => $venue->id, 'starts_at' => now(), 'ends_at' => now()->addDays(2)]);
        $event->rooms()->create(['name' => 'Fully Booked', 'venue_space_id' => $fullyBooked->id]);

        Livewire::actingAs($this->actor())->test(OverviewTab::class, ['venue' => $venue])
            ->call('setMode', 'readiness')
            ->assertSee('Missing capacity data')
            ->assertSee('Missing dimensions')
            ->assertSee('Never booked')
            ->assertSee('Fully documented');
    }

    public function test_isfullydocumented_requires_dimensions_as_well_as_capacity(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);

        $capacityOnly = $venue->spaces()->create(['name' => 'A', 'type' => 'breakout',
            'capacity_by_setup' => ['theater' => 100]]);
        $this->assertFalse($capacityOnly->isFullyDocumented());

        $complete = $venue->spaces()->create(['name' => 'B', 'type' => 'breakout',
            'capacity_by_setup' => ['theater' => 100], 'width_m' => 10, 'length_m' => 8]);
        $this->assertTrue($complete->isFullyDocumented());
    }

    public function test_health_and_readiness_now_agree_on_the_same_undocumented_count(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $venue->spaces()->create(['name' => 'A', 'type' => 'breakout',
            'capacity_by_setup' => ['theater' => 100]]); // capacity set, no dimensions

        $header = app(VenueCommandHeader::class)->for($venue->fresh());

        $this->assertFalse($header['readiness']['gates'][1]['met'], 'Capacity documented gate should be unmet without dimensions');
        $this->assertLessThan(100, $header['health']['score'], 'health score should be penalized for the same gap');
    }
}
