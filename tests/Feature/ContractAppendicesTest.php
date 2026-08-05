<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventContract;
use App\Models\EventRoom;
use App\Support\ContractAppendices;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContractAppendicesTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::query()->firstOrFail();
        $contract = EventContract::forEvent($event);

        return [$event, $contract];
    }

    /**
     * layout and equipment are both array-cast columns on EventRoom; pulling
     * the venue appendix used to dump them straight into a string-implode,
     * which only ever worked because most rooms leave both empty (an empty
     * array is falsy, so it got filtered out before implode() ever saw it).
     * A room with real equipment or a real seating layout crashed it.
     */
    public function test_pulling_the_venue_appendix_does_not_crash_on_a_rooms_equipment_or_layout(): void
    {
        [$event, $contract] = $this->ctx();

        $event->rooms()->delete();
        $event->rooms()->create([
            'name' => 'Main Plenary',
            'type' => 'main_hall',
            'capacity' => 60,
            'requirements' => [
                ['id' => 'r1', 'name' => 'Main LED Screen', 'cost_cents' => 667000, 'qty' => 1, 'days' => 1, 'status' => 'needed'],
                ['id' => 'r2', 'name' => 'Interpretation booth', 'cost_cents' => 0, 'qty' => 1, 'days' => 1, 'status' => 'needed'],
            ],
            'layout' => [
                ['id' => 'el1', 'type' => 'round', 'x' => 100, 'y' => 100, 'rot' => 0, 'seats' => 8, 'w' => 96, 'h' => 96],
            ],
        ]);

        [$blocks, $summary] = ContractAppendices::pull('venue', $event->fresh(), $contract);

        $this->assertNotEmpty($blocks);
        $this->assertSame('1 rooms', $summary);

        $text = $blocks[0]['items'][0]['t_en'];
        $this->assertStringContainsString('60 persons', $text);
        $this->assertStringContainsString('Main Hall', $text);
        $this->assertStringContainsString('Main LED Screen', $text);
        $this->assertStringContainsString('Interpretation booth', $text);
    }

    public function test_pulling_the_venue_appendix_handles_a_room_with_no_equipment_or_layout(): void
    {
        [$event, $contract] = $this->ctx();

        $event->rooms()->create(['name' => 'Breakout', 'type' => 'breakout', 'capacity' => 20]);

        [$blocks] = ContractAppendices::pull('venue', $event, $contract);

        $this->assertNotEmpty($blocks);
    }

    public function test_the_price_list_is_offered_as_an_appendix(): void
    {
        $this->assertArrayHasKey('pricing', ContractAppendices::LIBRARY);
    }

    public function test_pulling_the_price_list_appendix_shows_sell_prices_grouped_by_category(): void
    {
        [$event, $contract] = $this->ctx();
        $event->invoiceItems()->delete();

        $event->invoiceItems()->create([
            'name' => 'Bed & breakfast, single', 'category' => 'Accommodation', 'unit' => 'room_night',
            'cost_cents' => 12000, 'sell_cents' => 17000, 'currency' => 'USD', 'active' => true,
        ]);
        $event->invoiceItems()->create([
            'name' => 'Interpretation booth', 'category' => 'Interpretation', 'unit' => 'day',
            'cost_cents' => 50000, 'sell_cents' => 70000, 'currency' => 'USD', 'active' => true,
        ]);
        $event->invoiceItems()->create([
            'name' => 'Retired item', 'category' => 'Old', 'unit' => 'item',
            'cost_cents' => 100, 'sell_cents' => 200, 'currency' => 'USD', 'active' => false,
        ]);

        [$blocks, $summary] = ContractAppendices::pull('pricing', $event->fresh(), $contract);

        $this->assertSame('2 items', $summary, 'the retired item must not be offered to a client');

        $flat = collect($blocks)->pluck('items')->flatten(1);
        $accommodation = $flat->firstWhere('l_en', 'Bed & breakfast, single');
        $this->assertStringContainsString('170.00', $accommodation['t_en']);
        $this->assertStringNotContainsString('120.00', json_encode($blocks), 'internal cost must never appear in a client-facing appendix');
        $this->assertStringNotContainsString('Retired item', json_encode($blocks));
    }

    public function test_pulling_the_price_list_appendix_handles_no_active_items(): void
    {
        [$event, $contract] = $this->ctx();
        $event->invoiceItems()->update(['active' => false]);

        [$blocks, $summary] = ContractAppendices::pull('pricing', $event->fresh(), $contract);

        $this->assertSame([], $blocks);
        $this->assertSame('The price list has no active items yet', $summary);
    }
}
