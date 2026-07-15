<?php

namespace Tests\Feature;

use App\Livewire\RoomLayoutBuilder;
use App\Models\Event;
use App\Models\EventRoom;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class SeatingGeneratorTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(array $roomAttrs = []): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $room = $event->rooms()->create(array_merge(['name' => 'Grand Hall', 'type' => 'main_hall'], $roomAttrs));

        return [$event, $user, $room];
    }

    public function test_theater_generates_a_block_of_individual_chairs(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 20, 'length_m' => 30]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'theater')->set('seatTarget', '600')->set('seatSize', '0.6')
            ->set('seatComfort', 'standard')->set('seatAisles', '2')->set('seatLabels', true)
            ->call('generateSeating')->assertHasNoErrors();

        $room->refresh();
        $block = collect($room->layout)->firstWhere('type', 'seatblock');
        $this->assertNotNull($block);
        $this->assertSame('theater', $block['arr']);
        $this->assertSame(2, $block['aisles']);
        $this->assertGreaterThan(0, $block['seats']);

        // geometry renders one chair per seat, plus a label per row
        $geo = EventRoom::seatChairs($block, 15.0);
        $this->assertSame($block['seats'], $geo['capacity']);
        $this->assertCount($block['seats'], $geo['chairs']);
        $this->assertCount($block['rows'], $geo['labels']);
        // every chair carries a seat number like "A1"
        $this->assertSame('A1', $geo['chairs'][0][2]);
        $this->assertTrue(collect($geo['chairs'])->every(fn ($c) => $c[2] !== ''));

        // a stage was auto-added to face
        $this->assertTrue(collect($room->layout)->contains(fn ($e) => ($e['type'] ?? '') === 'stage'));
    }

    public function test_boardroom_and_ushape_build_tables(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 12, 'length_m' => 16]);
        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room]);

        $c->set('seatArr', 'boardroom')->set('seatTarget', '24')->call('generateSeating')->assertHasNoErrors();
        $board = collect($room->fresh()->layout)->firstWhere('arr', 'boardroom');
        $this->assertNotNull($board);
        $geo = EventRoom::seatChairs($board, 12.0);
        $this->assertCount($board['seats'], $geo['chairs']);
        $this->assertNotEmpty($geo['rects']); // a table was drawn

        $c->set('seatArr', 'ushape')->set('seatTarget', '30')->call('generateSeating')->assertHasNoErrors();
        $u = collect($room->fresh()->layout)->firstWhere('arr', 'ushape');
        $this->assertNotNull($u);
        $this->assertCount(3, EventRoom::seatChairs($u, 12.0)['rects']); // three U bands
    }

    public function test_hollow_square_chevron_and_circle(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 20, 'length_m' => 26]);
        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room]);

        // hollow square — 4 table bands, chairs on all sides
        $c->set('seatArr', 'hollowsquare')->set('seatTarget', '40')->call('generateSeating')->assertHasNoErrors();
        $hs = collect($room->fresh()->layout)->firstWhere('arr', 'hollowsquare');
        $this->assertCount(4, EventRoom::seatChairs($hs, 10.0)['rects']);

        // herringbone (chevron) — angled rows, one chair per seat
        $c->set('seatArr', 'chevron')->set('seatTarget', '200')->call('generateSeating')->assertHasNoErrors();
        $ch = collect($room->fresh()->layout)->firstWhere('arr', 'chevron');
        $this->assertSame(12, $ch['angle']);
        $this->assertCount($ch['seats'], EventRoom::seatChairs($ch, 12.0)['chairs']);

        // circle — a ring of chairs
        $c->set('seatArr', 'circle')->set('seatTarget', '30')->call('generateSeating')->assertHasNoErrors();
        $ci = collect($room->fresh()->layout)->firstWhere('arr', 'circle');
        $this->assertSame($ci['seats'], EventRoom::seatChairs($ci, 12.0)['capacity']);
        $this->assertGreaterThan(0, $ci['seats']);
    }

    public function test_fill_room_ignores_target(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 15, 'length_m' => 20]);
        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'theater')->set('seatFill', true)->set('seatTarget', '')
            ->call('generateSeating')->assertHasNoErrors();

        $block = collect($room->fresh()->layout)->firstWhere('type', 'seatblock');
        $this->assertGreaterThan(200, $block['seats']); // packed the room
    }

    public function test_banquet_builds_round_tables(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 25, 'length_m' => 30]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'banquet')->set('seatTarget', '200')->set('tableSeats', '10')
            ->call('generateSeating')->assertHasNoErrors();

        $block = collect($room->fresh()->layout)->firstWhere('type', 'seatblock');
        $geo = EventRoom::seatChairs($block, 12.0);
        $this->assertSame($block['tRows'] * $block['tCols'], count($geo['tables']));
        $this->assertCount($block['seats'], $geo['chairs']);
    }

    public function test_requires_room_dimensions(): void
    {
        [$event, $user, $room] = $this->ctx(); // no width/length

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'theater')->set('seatTarget', '100')
            ->call('generateSeating')->assertHasErrors('seatTarget');
    }
}
