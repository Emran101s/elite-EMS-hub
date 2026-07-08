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
            ->assertSeeInOrder(['18', 'seats placed']);

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

    public function test_builder_rejects_foreign_room(): void
    {
        [$event, , $user] = $this->ctx();
        $otherRoom = Event::has('rooms')->where('id', '!=', $event->id)->firstOrFail()->rooms()->first();

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $otherRoom])
            ->assertStatus(404);
    }
}
