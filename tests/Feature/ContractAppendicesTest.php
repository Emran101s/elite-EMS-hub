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
}
