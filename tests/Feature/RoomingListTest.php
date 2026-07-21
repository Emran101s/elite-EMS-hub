<?php

namespace Tests\Feature;

use App\Livewire\Hub\AccommodationTab;
use App\Models\Event;
use App\Models\EventRoomBlock;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomingListTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User} */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    private function block(Event $event, int $rooms = 50): EventRoomBlock
    {
        return $event->roomBlocks()->create([
            'hotel' => 'Fairmont Amman',
            'rooms_count' => $rooms,
            'rate_cents' => 12000,
            'check_in' => '2026-10-18',
            'check_out' => '2026-10-22',
            'status' => 'booked',
        ]);
    }

    public function test_a_block_starts_empty_and_fills_one_guest_at_a_time(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 3);

        $this->assertSame(0, $block->filled());
        $this->assertSame(3, $block->remaining());
        $this->assertFalse($block->isFull());

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event]);

        foreach (['Dana Haddad', 'Omar Nasser', 'Lina Kaddoura'] as $name) {
            $c->set("newGuest.{$block->id}", $name)->call('addRoom', $block->id);
        }

        $block->refresh();
        $this->assertSame(3, $block->filled());
        $this->assertSame(0, $block->remaining());
        $this->assertTrue($block->isFull());
        $this->assertSame(100, $block->fillPct());

        // Rooms inherit the block's hotel and dates so the list is consistent.
        $first = $block->rooms()->first();
        $this->assertSame('Fairmont Amman', $first->hotel);
        $this->assertSame('2026-10-18', $first->check_in->format('Y-m-d'));
    }

    public function test_a_full_block_refuses_another_guest(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 1);

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id)
            ->set("newGuest.{$block->id}", 'One Too Many')->call('addRoom', $block->id);

        $c->assertHasErrors("newGuest.{$block->id}");
        $this->assertSame(1, $block->fresh()->rooms()->count());
    }

    public function test_guest_details_save_inline(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id);

        $room = $block->rooms()->firstOrFail();
        $c->call('updateRoom', $room->id, 'guest_email', 'dana@example.com')
            ->call('updateRoom', $room->id, 'sharing_with', 'Omar Nasser');

        $room->refresh();
        $this->assertSame('dana@example.com', $room->guest_email);
        $this->assertSame('Omar Nasser', $room->sharing_with);

        // Fields outside the allow-list are ignored rather than blindly written.
        $c->call('updateRoom', $room->id, 'rate_cents', '999999');
        $this->assertSame(12000, $room->fresh()->rate_cents);
    }

    public function test_a_guest_can_have_their_own_check_in_and_check_out(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id);

        $room = $block->rooms()->firstOrFail();
        $this->assertSame('2026-10-18', $room->check_in->format('Y-m-d'), 'inherits the block to start');

        // A speaker arrives a day early and stays on.
        $c->call('updateRoom', $room->id, 'check_in', '2026-10-17')
            ->call('updateRoom', $room->id, 'check_out', '2026-10-23');

        $room->refresh();
        $this->assertSame('2026-10-17', $room->check_in->format('Y-m-d'));
        $this->assertSame('2026-10-23', $room->check_out->format('Y-m-d'));

        // The block's own dates are untouched by a per-guest change.
        $this->assertSame('2026-10-18', $block->fresh()->check_in->format('Y-m-d'));
    }

    public function test_a_malformed_date_clears_rather_than_crashing(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id);

        $room = $block->rooms()->firstOrFail();
        $c->call('updateRoom', $room->id, 'check_in', 'not-a-date')->assertOk();

        $this->assertNull($room->fresh()->check_in);
    }

    public function test_deleting_a_block_takes_its_rooming_list_with_it(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id)
            ->call('delete', $block->id);

        $this->assertNull(EventRoomBlock::find($block->id));
        $this->assertSame(0, $event->accommodations()->where('block_id', $block->id)->count());
    }

    public function test_the_rooming_list_pdf_carries_the_guests_but_never_the_money(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 4);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id);

        // Render the view directly — asserting on the HTML the PDF is made from,
        // without paying for a headless Chrome run.
        $html = view('events.rooming-list-pdf', [
            'event' => $event,
            'block' => $block->fresh(),
            'rooms' => $block->rooms()->get(),
            'css' => '',
        ])->render();

        $this->assertStringContainsString('Dana Haddad', $html);
        $this->assertStringContainsString('Fairmont Amman', $html);
        $this->assertStringContainsString('To be advised', $html);  // the 3 unnamed rooms

        // The whole point of this document: no rate, no total, no currency.
        $this->assertStringNotContainsString('120', $html, 'the nightly rate must not appear');
        $this->assertStringNotContainsString($event->currency, $html);
        foreach (['Rate', 'Cost', 'Total', 'rate_cents'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html, "“{$forbidden}” must not appear on a rooming list");
        }
    }

    public function test_a_pre_block_booking_converts_into_a_block(): void
    {
        [$event, $user] = $this->ctx();

        // The shape group bookings had before blocks existed.
        $legacy = $event->accommodations()->create([
            'hotel' => 'St Regis Amman', 'guest' => 'World Assembly Delegate',
            'room_type' => 'Twin', 'rooms' => 70, 'rate_cents' => 9000,
            'check_in' => '2026-10-18', 'check_out' => '2026-10-21', 'status' => 'booked',
        ]);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->call('convertToBlock', $legacy->id);

        $block = $event->roomBlocks()->where('hotel', 'St Regis Amman')->firstOrFail();
        $this->assertSame(70, $block->rooms_count);
        $this->assertSame(9000, $block->rate_cents);
        $this->assertSame(0, $block->filled(), 'the group label is not a guest');
        $this->assertStringContainsString('World Assembly Delegate', $block->notes);

        // The old row is gone, so the rooms are not counted twice.
        $this->assertNull($legacy->fresh());
    }

    public function test_a_guest_name_links_to_an_existing_attendee_and_pulls_their_contact(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);

        $attendee = $event->attendees()->create([
            'name' => 'Dana Haddad', 'email' => 'dana@icft.org', 'phone' => '+962 79 555 0111',
            'ticket_type' => 'Speaker', 'status' => 'registered',
        ]);

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'dana haddad')   // case should not matter
            ->call('addRoom', $block->id);

        $room = $block->rooms()->firstOrFail();
        $this->assertSame($attendee->id, $room->attendee_id);
        $this->assertSame('dana@icft.org', $room->guest_email);
        $this->assertSame('+962 79 555 0111', $room->guest_phone);
        $this->assertSame('Dana Haddad', $room->guest, 'takes the attendee spelling, not the typed one');
    }

    public function test_an_unknown_guest_becomes_an_attendee_so_both_lists_agree(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);
        $before = $event->attendees()->count();

        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Brand New Person')
            ->call('addRoom', $block->id);

        $this->assertSame($before + 1, $event->attendees()->count());
        $attendee = $event->attendees()->where('name', 'Brand New Person')->firstOrFail();
        $this->assertSame($attendee->id, $block->rooms()->first()->attendee_id);

        // Adding the same person again reuses the attendee rather than duplicating.
        Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Brand New Person')
            ->call('addRoom', $block->id);

        $this->assertSame(1, $event->attendees()->where('name', 'Brand New Person')->count());
    }

    public function test_editing_the_guest_on_the_rooming_list_updates_the_attendee(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id);

        $room = $block->rooms()->firstOrFail();
        $c->call('updateRoom', $room->id, 'guest_email', 'dana@new.org');

        $this->assertSame('dana@new.org', $room->attendee->fresh()->email,
            'the attendee record is the one source of contact details');
    }

    public function test_room_occupancy_category_and_times_are_recorded(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 5);
        $block->update(['room_type' => 'Deluxe', 'occupancy' => 'double']);

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id);

        $room = $block->rooms()->firstOrFail();
        $this->assertSame('double', $room->occupancy, 'inherits the block default');
        $this->assertSame('Deluxe', $room->room_type);

        // This guest wants a suite, arrives late and leaves early.
        $c->call('updateRoom', $room->id, 'occupancy', 'single')
            ->call('updateRoom', $room->id, 'room_type', 'Junior Suite')
            ->call('updateRoom', $room->id, 'arrival_time', '23:45')
            ->call('updateRoom', $room->id, 'departure_time', '06:15');

        $room->refresh();
        $this->assertSame('single', $room->occupancy);
        $this->assertSame('Single', $room->occupancyLabel());
        $this->assertSame('Junior Suite', $room->room_type);
        $this->assertSame('23:45', $room->arrival_time);
        $this->assertSame('06:15', $room->departure_time);

        // A junk time is rejected rather than stored.
        $c->call('updateRoom', $room->id, 'arrival_time', '9pm');
        $this->assertNull($room->fresh()->arrival_time);
    }

    public function test_the_rooming_list_carries_no_flight_details(): void
    {
        [$event, $user] = $this->ctx();
        $block = $this->block($event, 3);

        $c = Livewire::actingAs($user)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')->call('addRoom', $block->id);

        $room = $block->rooms()->firstOrFail();

        // Flights belong to Transportation, where a movement owns the flight it
        // meets. The rooming list must not accept them, even if asked directly.
        $c->call('updateRoom', $room->id, 'arrival_note', 'RJ 512')
            ->call('updateRoom', $room->id, 'departure_note', 'RJ 513');

        $room->refresh();
        $this->assertNull($room->arrival_note);
        $this->assertNull($room->departure_note);

        // And nothing flight-shaped reaches the document the hotel receives.
        $html = view('events.rooming-list-pdf', [
            'event' => $event, 'block' => $block->fresh(),
            'rooms' => $block->rooms()->get(), 'css' => '',
        ])->render();

        foreach (['Flight', 'flight'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $html);
        }
    }

    public function test_a_viewer_cannot_add_guests(): void
    {
        [$event] = $this->ctx();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'v@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);
        $block = $this->block($event, 5);

        Livewire::actingAs($viewer)->test(AccommodationTab::class, ['event' => $event])
            ->set("newGuest.{$block->id}", 'Dana Haddad')
            ->call('addRoom', $block->id)
            ->assertForbidden();

        $this->assertSame(0, $block->rooms()->count());
    }
}
