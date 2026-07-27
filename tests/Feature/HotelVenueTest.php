<?php

namespace Tests\Feature;

use App\Livewire\Hub\AccommodationTab;
use App\Livewire\VenuesManager;
use App\Models\Event;
use App\Models\EventRoomBlock;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Hotels are venues.
 *
 * The hotel used to be a free-text string in three separate tables, so the
 * same hotel was typed three times and matched nowhere. It is a venue now —
 * Venues already holds a name, a type, a city, an address and a contact, which
 * is exactly what a hotel needs.
 *
 * The string column stays alongside the link on purpose. It is what the rooming
 * list PDF was printed with, and a name typed before any of this existed must
 * still read.
 */
class HotelVenueTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        return [
            Event::factory()->create(['stage' => 'planning'])->fresh(),
            // Room blocks are gated on 'write'.
            User::factory()->create(['role' => 'super_admin']),
        ];
    }

    public function test_picking_a_hotel_from_the_directory_links_and_names_the_block(): void
    {
        [$event, $user] = $this->ctx();
        $hotel = Venue::factory()->create(['name' => 'Fairmont Amman', 'type' => 'Hotel', 'city' => 'Amman']);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('newBlock')
            ->set('venue_id', $hotel->id)
            ->set('rooms_count', 40)
            ->call('save')
            ->assertHasNoErrors();

        $block = $event->roomBlocks()->firstOrFail();

        $this->assertSame($hotel->id, $block->venue_id);
        $this->assertSame('Fairmont Amman', $block->hotel, 'the name is written too — documents print from it');
        $this->assertSame($hotel->id, $block->venue->id);
    }

    /** A hotel that is not in the directory yet must not block the work. */
    public function test_a_hotel_can_still_be_typed_when_it_is_not_in_the_directory(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('newBlock')
            ->set('hotel', 'InterContinental Amman')
            ->set('rooms_count', 12)
            ->call('save')
            ->assertHasNoErrors();

        $block = $event->roomBlocks()->firstOrFail();

        $this->assertSame('InterContinental Amman', $block->hotel);
        $this->assertNull($block->venue_id);
    }

    public function test_a_block_needs_a_hotel_one_way_or_the_other(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('newBlock')
            ->set('hotel', '')
            ->set('rooms_count', 10)
            ->call('save')
            ->assertHasErrors('hotel');

        $this->assertSame(0, $event->roomBlocks()->count());
    }

    /**
     * A venue is deleted with nullOnDelete, so the block keeps its name rather
     * than turning into a blank row. The name is the whole reason it is stored.
     */
    public function test_deleting_a_hotel_leaves_the_block_with_the_name_it_was_made_with(): void
    {
        [$event, $user] = $this->ctx();
        $hotel = Venue::factory()->create(['name' => 'Fairmont Amman', 'type' => 'Hotel']);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('newBlock')->set('venue_id', $hotel->id)->set('rooms_count', 40)->call('save');

        $block = $event->roomBlocks()->firstOrFail();
        Livewire::actingAs($user)->test(VenuesManager::class)->call('delete', $hotel->id);

        $block->refresh();
        $this->assertNull($block->venue_id);
        $this->assertSame('Fairmont Amman', $block->hotel, 'the block still says where it is');
    }

    /** Opening the form again must not carry the last block's hotel over. */
    public function test_a_new_block_does_not_inherit_the_hotel_of_the_one_before_it(): void
    {
        [$event, $user] = $this->ctx();
        $hotel = Venue::factory()->create(['name' => 'Fairmont Amman', 'type' => 'Hotel']);

        $screen = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('newBlock')->set('venue_id', $hotel->id)->set('rooms_count', 40)->call('save');

        $screen->call('edit', $event->roomBlocks()->firstOrFail()->id)
            ->assertSet('venue_id', $hotel->id)
            ->call('newBlock')
            ->assertSet('venue_id', null)
            ->assertSet('hotel', '');
    }

    public function test_a_hotel_is_a_venue_that_says_hotel_however_the_type_is_worded(): void
    {
        $plain = Venue::factory()->create(['name' => 'A', 'type' => 'Hotel']);
        $reworded = Venue::factory()->create(['name' => 'B', 'type' => 'Hotel / Resort']);
        $hall = Venue::factory()->create(['name' => 'C', 'type' => 'Exhibition Hall']);

        $hotels = Venue::hotels()->pluck('id');

        $this->assertTrue($hotels->contains($plain->id));
        $this->assertTrue($reworded->isHotel(), 'the type is editable, so this matches on the word');
        $this->assertFalse($hotels->contains($hall->id));
    }

    public function test_the_venues_screen_can_show_just_the_hotels(): void
    {
        $user = User::factory()->create(['role' => 'super_admin']);
        Venue::factory()->create(['name' => 'Fairmont Amman', 'type' => 'Hotel']);
        Venue::factory()->create(['name' => 'Royal Convention Centre', 'type' => 'Conference Centre']);

        Livewire::actingAs($user)->test(VenuesManager::class)
            ->assertSee('Fairmont Amman')
            ->assertSee('Royal Convention Centre')
            ->set('filter', 'hotels')
            ->assertSee('Fairmont Amman')
            ->assertDontSee('Royal Convention Centre');
    }

    /**
     * The backfill matched on the exact name and nothing else. A fuzzy match
     * would silently attach a block to the wrong hotel, which is worse than
     * leaving it unlinked — an unlinked block still shows its name.
     */
    public function test_a_name_that_does_not_match_a_venue_exactly_is_left_alone(): void
    {
        $event = Event::factory()->create(['stage' => 'planning']);
        Venue::factory()->create(['name' => 'The St Regis Amman', 'type' => 'Hotel']);

        $block = EventRoomBlock::create([
            'event_id' => $event->id, 'hotel' => 'St. regis', 'rooms_count' => 5, 'status' => 'held',
        ]);

        $this->assertNull($block->venue_id);
        $this->assertSame('St. regis', $block->hotel, 'unlinked, but it still says where it is');
    }
}
