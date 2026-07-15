<?php

namespace Tests\Feature;

use App\Livewire\Hub\AgendaTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaControlTest extends TestCase
{
    use RefreshDatabase;

    private function make(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $day = $event->agendaDays()->create(['date' => now(), 'label' => 'Day 1', 'sort' => 0]);
        $session = $event->agendaSessions()->create([
            'agenda_day_id' => $day->id, 'room_id' => null, 'title' => 'Test Panel',
            'type' => 'panel', 'format' => 'in_person', 'status' => 'confirmed',
            'starts_at' => '10:00', 'ends_at' => '11:00',
        ]);

        return [$event, $user, $session];
    }

    public function test_toggle_flag(): void
    {
        [$event, $user, $s] = $this->make();
        $this->assertFalse((bool) $s->flagged);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])->call('toggleFlag', $s->id);
        $this->assertTrue((bool) $s->fresh()->flagged);
    }

    public function test_move_session_reschedules_and_reassigns_room(): void
    {
        [$event, $user, $s] = $this->make();
        $room = $event->rooms()->create(['name' => 'Hall X', 'type' => 'main_hall']);

        // 842 min snaps to 840 (14:00); duration (60m) preserved; dropped on Hall X.
        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('moveSession', $s->id, 842, $room->id);

        $s->refresh();
        $this->assertSame('14:00', substr($s->starts_at, 0, 5));
        $this->assertSame('15:00', substr($s->ends_at, 0, 5));
        $this->assertSame($room->id, $s->room_id);
    }

    public function test_move_clamps_within_the_day(): void
    {
        [$event, $user, $s] = $this->make();
        // Way past midnight → clamped so the 60m session still ends by 24:00.
        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('moveSession', $s->id, 2000, null);

        $s->refresh();
        $this->assertSame('23:00', substr($s->starts_at, 0, 5));
        $this->assertNull($s->room_id);
    }

    public function test_delete_session(): void
    {
        [$event, $user, $s] = $this->make();
        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])->call('deleteSession', $s->id);
        $this->assertDatabaseMissing('event_agenda_sessions', ['id' => $s->id]);
    }
}
