<?php

namespace Tests\Feature;

use App\Livewire\RoomLayoutBuilder;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RoomLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::has('rooms')->firstOrFail();

        return [$event, $event->rooms()->first(), User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    public function test_add_move_and_remove_seating_elements(): void
    {
        [$event, $room, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'round')
            ->call('addElement', 'classroom');

        $room->refresh();
        $this->assertCount(2, $room->layout);
        $this->assertSame('round', $room->layout[0]['type']);
        $this->assertSame(8, $room->layout[0]['seats']); // preset

        $id = $room->layout[0]['id'];
        $c->call('moveElement', $id, 200, 150);
        $this->assertSame(200, $event->rooms()->find($room->id)->layout[0]['x']);

        $c->call('changeSeats', $id, 2);
        $this->assertSame(10, $event->rooms()->find($room->id)->layout[0]['seats']);

        $c->call('rotate', $id);
        $this->assertSame(45, $event->rooms()->find($room->id)->layout[0]['rot']);

        $c->call('removeElement', $id);
        $this->assertCount(1, $event->rooms()->find($room->id)->layout);
    }

    public function test_seat_total_reflects_placed_elements(): void
    {
        [$event, $room, $user] = $this->ctx();

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'round')     // 8
            ->call('addElement', 'banquet')   // 10
            ->assertSeeInOrder(['18', 'seats']);

        $this->assertSame(18, $room->fresh()->seatCount());
    }

    public function test_layout_pdf_downloads(): void
    {
        [$event, $room, $user] = $this->ctx();
        $room->update(['layout' => [['id' => 'a1', 'type' => 'round', 'x' => 300, 'y' => 200, 'rot' => 0, 'seats' => 8, 'w' => 96, 'h' => 96]]]);

        $response = $this->actingAs($user)->get(route('events.room-layout.pdf', [$event, $room]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_resize_element_updates_dimensions(): void
    {
        [$event, $room, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'round');
        $id = $room->fresh()->layout[0]['id'];

        $c->call('resizeElement', $id, 'both', 20);
        $el = $room->fresh()->layout[0];
        $this->assertSame(116, $el['w']); // 96 + 20
        $this->assertSame(116, $el['h']);

        // width-only on a rectangular element
        $c->call('resizeElement', $id, 'w', -10);
        $this->assertSame(106, $room->fresh()->layout[0]['w']);
        $this->assertSame(116, $room->fresh()->layout[0]['h']);
    }

    public function test_rotation_absolute_and_relative(): void
    {
        [$event, $room, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'table');
        $id = $room->fresh()->layout[0]['id'];

        $c->call('setRotation', $id, 137);
        $this->assertSame(137, $room->fresh()->layout[0]['rot']);

        $c->call('rotateBy', $id, 250); // 137 + 250 = 387 → 27
        $this->assertSame(27, $room->fresh()->layout[0]['rot']);

        $c->call('rotateBy', $id, -30); // 27 - 30 = -3 → 357
        $this->assertSame(357, $room->fresh()->layout[0]['rot']);
    }

    public function test_size_in_metres_converts_with_scale(): void
    {
        [$event, $room, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('width_m', '20')->set('length_m', '14')
            ->call('addElement', 'round');
        $id = $room->fresh()->layout[0]['id'];

        // scale = min(960/20, 560/14) = min(48, 40) = 40 px/m → 2 m = 80 px
        $c->call('setSizeMeters', $id, 'both', 2);
        $this->assertSame(80, $room->fresh()->layout[0]['w']);
        $this->assertSame(80, $room->fresh()->layout[0]['h']);

        // without dimensions the metre setter is a no-op
        $c->set('width_m', '')->set('length_m', '')->call('setSizeMeters', $id, 'both', 5);
        $this->assertSame(80, $room->fresh()->layout[0]['w']);
    }

    public function test_room_dimensions_persist(): void
    {
        [$event, $room, $user] = $this->ctx();

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('width_m', '20')
            ->set('length_m', '12.5');

        $room->refresh();
        $this->assertSame(20.0, $room->width_m);
        $this->assertSame(12.5, $room->length_m);
    }

    public function test_equipment_pdf_downloads(): void
    {
        [$event, $room, $user] = $this->ctx();
        $room->update(['requirements' => [
            ['name' => 'Projector', 'cost_cents' => 9000, 'qty' => 2, 'days' => 2, 'status' => 'confirmed', 'notes' => '4K'],
        ]]);

        $response = $this->actingAs($user)->get(route('events.room-equipment.pdf', [$event, $room]));

        $response->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('content-type'));
    }

    public function test_staging_elements_have_no_seats(): void
    {
        [$event, $room, $user] = $this->ctx();

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'stage')
            ->call('addElement', 'screen');

        $room->refresh();
        $this->assertSame('stage', $room->layout[0]['type']);
        $this->assertSame(0, $room->seatCount());
    }

    public function test_requirement_can_be_added_then_edited_in_place(): void
    {
        [$event, $room, $user] = $this->ctx();

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('reqName', 'Main LED Screen')
            ->set('reqCost', '6670')
            ->set('reqQty', '1')
            ->set('reqDays', '3')
            ->call('addRequirement');

        $req = $room->fresh()->requirements[0];
        $this->assertSame('Main LED Screen', $req['name']);
        $this->assertSame(667000, $req['cost_cents']);
        $id = $req['id'];

        // Editing loads the existing line into the form rather than adding a new one.
        $c->call('editRequirement', $id)
            ->assertSet('reqName', 'Main LED Screen')
            ->assertSet('reqCost', '6670')
            ->assertSet('reqDays', '3')
            ->set('reqName', 'Main LED Wall')
            ->set('reqCost', '5500')
            ->call('addRequirement');

        $room->refresh();
        $this->assertCount(1, $room->requirements, 'editing must update in place, not append a second line');
        $this->assertSame('Main LED Wall', $room->requirements[0]['name']);
        $this->assertSame(550000, $room->requirements[0]['cost_cents']);
        $this->assertSame($id, $room->requirements[0]['id'], 'the id is preserved across an edit');
    }

    public function test_editing_requirement_preserves_its_status(): void
    {
        [$event, $room, $user] = $this->ctx();
        $room->update(['requirements' => [
            ['id' => 'req1', 'name' => 'Projector', 'cost_cents' => 9000, 'qty' => 1, 'days' => 1, 'status' => 'confirmed'],
        ]]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('editRequirement', 'req1')
            ->set('reqCost', '95')
            ->call('addRequirement');

        $this->assertSame('confirmed', $room->fresh()->requirements[0]['status']);
    }

    public function test_cancel_edit_requirement_discards_the_form(): void
    {
        [$event, $room, $user] = $this->ctx();
        $room->update(['requirements' => [
            ['id' => 'req1', 'name' => 'Projector', 'cost_cents' => 9000, 'qty' => 1, 'days' => 1, 'status' => 'needed'],
        ]]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('editRequirement', 'req1')
            ->set('reqName', 'Something else entirely')
            ->call('cancelEditRequirement')
            ->assertSet('reqName', '')
            ->assertSet('editingReqId', null);

        $this->assertSame('Projector', $room->fresh()->requirements[0]['name']);
    }

    public function test_removing_the_line_being_edited_clears_the_form(): void
    {
        [$event, $room, $user] = $this->ctx();
        $room->update(['requirements' => [
            ['id' => 'req1', 'name' => 'Projector', 'cost_cents' => 9000, 'qty' => 1, 'days' => 1, 'status' => 'needed'],
        ]]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('editRequirement', 'req1')
            ->call('removeRequirement', 'req1')
            ->assertSet('editingReqId', null)
            ->assertSet('reqName', '');
    }

    public function test_builder_rejects_foreign_room(): void
    {
        [$event, , $user] = $this->ctx();
        $otherRoom = Event::has('rooms')->where('id', '!=', $event->id)->firstOrFail()->rooms()->first();

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $otherRoom])
            ->assertStatus(404);
    }
}
