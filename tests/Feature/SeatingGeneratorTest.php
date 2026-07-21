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

        // generating no longer forces a stage — you add one only if you want it
        $this->assertFalse(collect($room->layout)->contains(fn ($e) => ($e['type'] ?? '') === 'stage'));
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

    public function test_table_designer_builds_a_ushape_from_head_and_arm_tables(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 14, 'length_m' => 18]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'ushape')
            ->set('uHeadTables', 3)
            ->set('uArmTables', 4)
            ->set('uPerTable', 3)
            ->set('tableW_cm', 180)
            ->set('tableH_cm', 60)
            ->call('designUShape')
            ->assertHasNoErrors();

        $block = collect($room->fresh()->layout)->firstWhere('mode', 'utables');
        $this->assertNotNull($block, 'a utables seatblock is created');

        // (3 head + 2 arms × 4) tables × 3 chairs = 33 chairs across 11 tables.
        $this->assertSame(3, $block['headTables']);
        $this->assertSame(4, $block['armTables']);
        $this->assertSame(3, $block['perTable']);
        $this->assertSame(33, $block['seats']);
        $this->assertSame(1.8, $block['tableW_m'], 'each table is 180cm long');
        $this->assertSame(0.6, $block['tableH_m'], 'and 60cm deep');

        $geo = EventRoom::seatChairs($block, 20);
        $this->assertCount(33, $geo['chairs'], 'every table seats its chairs');
        $this->assertCount(11, $geo['rects'], '3 head + 8 arm tables');
    }

    public function test_head_and_arm_table_counts_change_the_total(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 16, 'length_m' => 20]);

        // 2 head tables, 2 per arm, 4 chairs each → (2 + 4) × 4 = 24.
        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'ushape')
            ->set('uHeadTables', 2)->set('uArmTables', 2)->set('uPerTable', 4)
            ->call('designUShape');

        $block = collect($room->fresh()->layout)->firstWhere('mode', 'utables');
        $this->assertSame(24, $block['seats']);
        $this->assertCount(6, EventRoom::seatChairs($block, 20)['rects'], '2 head + 4 arm tables');
    }

    public function test_round_and_banquet_tables_default_to_180cm(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 20, 'length_m' => 24]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'banquet')
            ->set('seatTarget', 100)
            ->call('generateSeating')
            ->assertHasNoErrors();

        $block = collect($room->fresh()->layout)->firstWhere('arr', 'banquet');
        // The standard banquet round is 180cm, not the old 160cm.
        $geo = EventRoom::seatChairs($block, 14);
        $this->assertEqualsWithDelta(1.8 * 14, $geo['tables'][0][2], 0.5, 'banquet round renders at 180cm');
    }

    public function test_a_custom_round_diameter_flows_through(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 20, 'length_m' => 24]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'banquet')->set('seatTarget', 60)
            ->set('roundDia_cm', 150)
            ->call('generateSeating');

        $block = collect($room->fresh()->layout)->firstWhere('arr', 'banquet');
        $geo = EventRoom::seatChairs($block, 14);
        $this->assertEqualsWithDelta(1.5 * 14, $geo['tables'][0][2], 0.5);
    }


    public function test_chair_size_and_spacing_flow_into_a_generated_block(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 20, 'length_m' => 30]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'theater')->set('seatTarget', 100)
            ->set('seatSize', '0.6')        // 60cm chair
            ->set('seatGapCm', '20')        // wide 20cm gap
            ->call('generateSeating')->assertHasNoErrors();

        $block = collect($room->fresh()->layout)->firstWhere('type', 'seatblock');
        $this->assertEqualsWithDelta(0.20, $block['colGap_m'], 0.001, 'the chair gap is honoured');
        $this->assertSame(0.6, $block['seat_m']);
    }

    public function test_basics_drop_at_real_world_size(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 12, 'length_m' => 16]);
        $scale = min(960 / 12, 560 / 16);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'chair')
            ->call('addElement', 'table');

        $els = collect($c->get('elements'));
        $chair = $els->firstWhere('type', 'chair');
        $table = $els->firstWhere('type', 'table');
        $this->assertEqualsWithDelta(0.6 * $scale, $chair['w'], 1, 'chair drops at 60cm');
        $this->assertEqualsWithDelta(1.8 * $scale, $table['w'], 1, 'table long side is 180cm');
        $this->assertEqualsWithDelta(0.6 * $scale, $table['h'], 1, 'table depth is 60cm');
    }

    public function test_select_all_then_delete_selected_clears_them(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 12, 'length_m' => 16]);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'chair')->call('addElement', 'table')->call('addElement', 'round');

        $this->assertCount(3, $c->get('elements'));

        $c->call('selectAll');
        $this->assertCount(3, $c->get('selectedIds'));

        $c->call('deleteSelected');
        $this->assertCount(0, $c->get('elements'));
        $this->assertCount(0, $c->get('selectedIds'));
    }

    public function test_toggle_in_selection_deletes_only_the_chosen(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 12, 'length_m' => 16]);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'chair')->call('addElement', 'table');
        $keep = $c->get('elements')[0]['id'];
        $drop = $c->get('elements')[1]['id'];

        $c->call('toggleInSelection', $drop)->call('deleteSelected');

        $ids = collect($c->get('elements'))->pluck('id')->all();
        $this->assertSame([$keep], $ids);
    }


    public function test_placed_items_match_the_floor_scale_exactly(): void
    {
        // Room 12×16m on a 960×560 canvas → the px-per-metre scale.
        [$event, $user, $room] = $this->ctx(['width_m' => 12, 'length_m' => 16]);
        $scale = min(960 / 12, 560 / 16);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'chair')
            ->call('addElement', 'table')
            ->call('addElement', 'round');

        $els = collect($c->get('elements'));

        // Each item's box equals its real size × the same scale as the 1m grid.
        $chair = $els->firstWhere('type', 'chair');
        $this->assertEqualsWithDelta(0.6, $chair['w'] / $scale, 0.03, 'a chair is 60cm on the grid');
        $this->assertEqualsWithDelta(0.6, $chair['h'] / $scale, 0.03);

        $table = $els->firstWhere('type', 'table');
        $this->assertEqualsWithDelta(1.8, $table['w'] / $scale, 0.03, 'a table is 180cm long');
        $this->assertEqualsWithDelta(0.6, $table['h'] / $scale, 0.03, 'and 60cm deep');

        $round = $els->firstWhere('type', 'round');
        $this->assertEqualsWithDelta(1.8, $round['w'] / $scale, 0.03, 'a round table is 180cm across');

        // Resizing to a new real size keeps it to scale.
        $c->call('setSizeMeters', $chair['id'], 'both', '0.5');
        $resized = collect($c->get('elements'))->firstWhere('id', $chair['id']);
        $this->assertEqualsWithDelta(0.5, $resized['w'] / $scale, 0.03, '50cm chair stays to scale');
    }


    public function test_generating_no_longer_forces_a_default_stage(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 14, 'length_m' => 18]);

        Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'ushape')->call('designUShape');

        $types = collect($room->fresh()->layout)->pluck('type');
        $this->assertFalse($types->contains('stage'), 'no stage is auto-added');
        $this->assertTrue($types->contains('seatblock'));
    }

    public function test_a_selected_ushape_can_be_re_edited_from_the_inspector(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 16, 'length_m' => 20]);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'ushape')->set('uHeadTables', 2)->set('uArmTables', 3)->set('uPerTable', 3)
            ->call('designUShape');

        $id = collect($c->get('elements'))->firstWhere('mode', 'utables')['id'];
        $this->assertSame(24, collect($c->get('elements'))->firstWhere('id', $id)['seats']); // (2+6)*3

        // Change the counts on the placed block; capacity recomputes.
        $c->call('updateSeatblock', $id, 'armTables', 5)
            ->call('updateSeatblock', $id, 'perTable', 4);

        $block = collect($c->get('elements'))->firstWhere('id', $id);
        $this->assertSame(5, $block['armTables']);
        $this->assertSame(4, $block['perTable']);
        $this->assertSame(48, $block['seats'], '(2 head + 10 arm) × 4 chairs');
    }

    public function test_editing_a_banquet_block_updates_its_capacity(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 20, 'length_m' => 24]);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'banquet')->set('seatTarget', 80)->call('generateSeating');

        $id = collect($c->get('elements'))->firstWhere('arr', 'banquet')['id'];
        $c->call('updateSeatblock', $id, 'perTable', 12);   // 12 per table

        $block = collect($c->get('elements'))->firstWhere('id', $id);
        $this->assertSame($block['tRows'] * $block['tCols'] * 12, $block['seats']);
    }

    public function test_a_translation_booth_drops_at_two_by_two_metres(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 14, 'length_m' => 18]);
        $scale = min(960 / 14, 560 / 18);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'booth');

        $booth = collect($c->get('elements'))->firstWhere('type', 'booth');
        $this->assertNotNull($booth);
        $this->assertEqualsWithDelta(2.0, $booth['w'] / $scale, 0.05, 'a translation booth is 2m wide');
        $this->assertEqualsWithDelta(2.0, $booth['h'] / $scale, 0.05, 'and 2m deep');
    }


    public function test_an_item_can_be_named_and_the_name_shows_on_the_plan(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 12, 'length_m' => 16]);

        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->call('addElement', 'round');
        $id = $c->get('elements')[0]['id'];

        $c->call('nameElement', $id, '  VIP Round 1  ')
            ->assertSee('VIP Round 1');   // rendered on the canvas

        $this->assertSame('VIP Round 1', collect($room->fresh()->layout)->firstWhere('id', $id)['name'], 'trimmed & persisted');

        // Clearing the name removes it.
        $c->call('nameElement', $id, '');
        $this->assertNull(collect($room->fresh()->layout)->firstWhere('id', $id)['name']);

        // Overlong names are capped at 40 chars.
        $c->call('nameElement', $id, str_repeat('x', 60));
        $this->assertSame(40, mb_strlen(collect($room->fresh()->layout)->firstWhere('id', $id)['name']));
    }


    public function test_the_inspector_shows_the_editor_and_name_field_for_a_selected_block(): void
    {
        [$event, $user, $room] = $this->ctx(['width_m' => 14, 'length_m' => 18]);

        // designUShape leaves the new block selected, so the editor is already shown.
        $c = Livewire::actingAs($user)->test(RoomLayoutBuilder::class, ['event' => $event, 'room' => $room])
            ->set('seatArr', 'ushape')->call('designUShape');

        $c->assertSee('Arrangement · edit')   // the count editor
            ->assertSee('Head tables')
            ->assertSee('Chairs / table')
            ->assertSee('Name / label')          // the naming field
            ->assertSee('Total chairs');
    }

}
