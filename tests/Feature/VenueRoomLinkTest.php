<?php

namespace Tests\Feature;

use App\Livewire\Hub\VenueTab;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class VenueRoomLinkTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::create(['name' => 'Ops', 'email' => 'ops@ebh.test',
            'password' => bcrypt('x'), 'role' => 'admin']);
    }

    public function test_a_room_can_optionally_link_to_the_venues_own_space(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $space = $venue->spaces()->create(['name' => 'Main Hall', 'type' => 'main_hall']);
        $event = Event::factory()->create(['venue_id' => $venue->id]);

        Livewire::actingAs($this->actor())->test(VenueTab::class, ['event' => $event])
            ->call('newRoom')
            ->set('room_name', 'Main Hall')
            ->set('room_venue_space_id', (string) $space->id)
            ->call('saveRoom');

        $room = $event->rooms()->where('name', 'Main Hall')->firstOrFail();
        $this->assertSame($space->id, $room->venue_space_id);
        $this->assertSame($event->id, $space->fresh()->bookings->first()->event_id);
    }

    public function test_an_unlinked_room_still_saves_exactly_as_before(): void
    {
        $event = Event::factory()->create();

        Livewire::actingAs($this->actor())->test(VenueTab::class, ['event' => $event])
            ->call('newRoom')
            ->set('room_name', 'Green Room')
            ->set('room_capacity', 20)
            ->call('saveRoom');

        $room = $event->rooms()->where('name', 'Green Room')->firstOrFail();
        $this->assertNull($room->venue_space_id);
        $this->assertSame(20, $room->capacity);
    }

    public function test_editing_a_room_preloads_its_linked_space(): void
    {
        $venue = Venue::create(['name' => 'Fairmont Amman', 'city' => 'Amman']);
        $space = $venue->spaces()->create(['name' => 'Main Hall', 'type' => 'main_hall']);
        $event = Event::factory()->create(['venue_id' => $venue->id]);
        $room = $event->rooms()->create(['name' => 'Main Hall', 'venue_space_id' => $space->id]);

        Livewire::actingAs($this->actor())->test(VenueTab::class, ['event' => $event])
            ->call('editRoom', $room->id)
            ->assertSet('room_venue_space_id', (string) $space->id);
    }

    public function test_the_venue_can_be_assigned_and_cleared_from_the_venue_tab(): void
    {
        $venue = Venue::create(['name' => 'Kempinski Aqaba', 'city' => 'Aqaba']);
        $event = Event::factory()->create(['venue_id' => null]);

        $screen = Livewire::actingAs($this->actor())->test(VenueTab::class, ['event' => $event]);

        // Assign in place — no trip to Settings.
        $screen->call('setVenue', (string) $venue->id);
        $this->assertSame($venue->id, $event->fresh()->venue_id);

        // And clear it back to unlinked.
        $screen->call('setVenue', '');
        $this->assertNull($event->fresh()->venue_id);
    }

    public function test_assigning_a_non_existent_venue_is_rejected(): void
    {
        $event = Event::factory()->create(['venue_id' => null]);

        Livewire::actingAs($this->actor())->test(VenueTab::class, ['event' => $event])
            ->call('setVenue', '999999')
            ->assertHasErrors('venue_id');

        $this->assertNull($event->fresh()->venue_id);
    }
}
