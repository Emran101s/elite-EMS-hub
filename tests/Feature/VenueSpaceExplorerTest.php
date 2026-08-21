<?php

namespace Tests\Feature;

use App\Livewire\VenueStudio\SpaceExplorerTab;
use App\Models\User;
use App\Models\Venue;
use App\Models\VenueSpace;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueSpaceExplorerTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_a_space_can_be_added_with_capacity_by_setup(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman', 'capacity' => 900]);

        Livewire::actingAs($this->actor())->test(SpaceExplorerTab::class, ['venue' => $venue])
            ->call('newSpace')
            ->set('name', 'Main Hall')
            ->set('type', 'main_hall')
            ->set('capacity', 500)
            ->set('floor_zone', 'Ground Floor')
            ->set('width_m', 30)
            ->set('length_m', 20)
            ->set('setupCapacity.theater', '400')
            ->set('setupCapacity.banquet', '250')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false);

        $space = VenueSpace::where('name', 'Main Hall')->firstOrFail();
        $this->assertSame($venue->id, $space->venue_id);
        $this->assertSame('main_hall', $space->type);
        $this->assertSame(500, $space->capacity);
        $this->assertSame('Ground Floor', $space->floor_zone);
        $this->assertSame(600.0, $space->areaM2());
        $this->assertSame(400, $space->capacityFor('theater'));
        $this->assertSame(250, $space->capacityFor('banquet'));
        $this->assertNull($space->capacityFor('boardroom'));
    }

    public function test_a_space_can_be_edited(): void
    {
        $venue = Venue::create(['name' => 'Old Hall', 'city' => 'Amman']);
        $space = $venue->spaces()->create(['name' => 'Hall A', 'type' => 'breakout', 'capacity' => 100]);

        Livewire::actingAs($this->actor())->test(SpaceExplorerTab::class, ['venue' => $venue])
            ->call('editSpace', $space->id)
            ->assertSet('name', 'Hall A')
            ->set('name', 'Hall B')
            ->set('capacity', 250)
            ->call('save');

        $this->assertSame('Hall B', $space->fresh()->name);
        $this->assertSame(250, $space->fresh()->capacity);
    }

    public function test_deleting_a_space_leaves_its_bookings_intact(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $space = $venue->spaces()->create(['name' => 'Main Hall', 'type' => 'main_hall']);
        $event = \App\Models\Event::factory()->create(['venue_id' => $venue->id]);
        $room = $event->rooms()->create(['name' => 'Main Hall', 'venue_space_id' => $space->id]);

        Livewire::actingAs($this->actor())->test(SpaceExplorerTab::class, ['venue' => $venue])
            ->call('delete', $space->id);

        $this->assertNull(VenueSpace::find($space->id));
        $this->assertNotNull($room->fresh());
        $this->assertNull($room->fresh()->venue_space_id);
    }

    public function test_a_viewer_cannot_add_or_delete(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $viewer = User::create(['name' => 'Vic', 'email' => 'v@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(SpaceExplorerTab::class, ['venue' => $venue])
            ->call('newSpace')->set('name', 'Sneaky')->call('save')
            ->assertForbidden();

        $this->assertSame(0, VenueSpace::count());
    }
}
