<?php

namespace Tests\Feature;

use App\Livewire\Hub\AgendaTab;
use App\Livewire\Hub\SettingsTab;
use App\Livewire\Hub\VenueTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AgendaConflictTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_same_room_overlap_is_flagged(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $room = $event->rooms()->first();

        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Session A', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '11:00', 'ends_at' => '12:00', 'sort' => 50]);
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Session B', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '11:30', 'ends_at' => '12:30', 'sort' => 51]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertSee('scheduling')
            ->assertSee('double-booked with'); // room clash flagged on the card
    }

    public function test_same_speaker_overlap_is_flagged(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $rooms = $event->rooms()->take(2)->get();

        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $rooms[0]->id, 'title' => 'Talk 1', 'type' => 'keynote', 'status' => 'draft', 'starts_at' => '14:00', 'ends_at' => '15:00', 'speaker' => 'Dr. Chen', 'sort' => 60]);
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $rooms[1]->id, 'title' => 'Talk 2', 'type' => 'keynote', 'status' => 'draft', 'starts_at' => '14:30', 'ends_at' => '15:30', 'speaker' => 'Dr. Chen', 'sort' => 61]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertSee('Dr. Chen also speaks at', false);
    }

    public function test_non_overlapping_sessions_are_not_flagged(): void
    {
        [$event, $user] = $this->ctx();
        $day = $event->agendaDays()->orderBy('sort')->first();
        $room = $event->rooms()->first();

        // Wipe seeded sessions so only these two clean ones are evaluated.
        $event->agendaSessions()->delete();
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Morning', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '09:00', 'ends_at' => '10:00', 'sort' => 1]);
        $event->agendaSessions()->create(['agenda_day_id' => $day->id, 'room_id' => $room->id, 'title' => 'Afternoon', 'type' => 'panel', 'status' => 'draft', 'starts_at' => '10:00', 'ends_at' => '11:00', 'sort' => 2]);

        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->assertDontSee('scheduling conflict');
    }

    public function test_room_can_be_added_and_appears_for_the_agenda(): void
    {
        [$event, $user] = $this->ctx();
        $before = $event->rooms()->count();

        Livewire::actingAs($user)->test(VenueTab::class, ['event' => $event])
            ->call('newRoom')
            ->set('room_name', 'Innovation Lab')
            ->set('room_type', 'breakout')
            ->set('room_capacity', '80')
            ->call('saveRoom')
            ->assertHasNoErrors();

        $this->assertSame($before + 1, $event->rooms()->count());
        $room = $event->rooms()->where('name', 'Innovation Lab')->firstOrFail();
        $this->assertSame(80, $room->capacity);

        // Now selectable in the agenda room picker.
        Livewire::actingAs($user)->test(AgendaTab::class, ['event' => $event])
            ->call('newSession', $event->agendaDays()->first()->id)
            ->assertSee('Innovation Lab');
    }

    public function test_session_form_can_create_a_room_inline(): void
    {
        [$event, $user] = $this->ctx();
        $gala = \App\Models\Event::where('name', 'EY Annual Gala')->firstOrFail(); // starts with 0 rooms
        $day = $gala->agendaDays()->create(['date' => now(), 'label' => 'Day 1', 'sort' => 0]);

        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\Hub\AgendaTab::class, ['event' => $gala])
            ->call('newSession', $day->id)
            ->set('title', 'Welcome Reception')
            ->set('newRoomName', 'Grand Ballroom') // "…or type a room" — created on save
            ->call('saveSession')
            ->assertHasNoErrors();

        $room = $gala->rooms()->where('name', 'Grand Ballroom')->firstOrFail();
        $this->assertSame($room->id, $gala->agendaSessions()->where('title', 'Welcome Reception')->value('room_id'));
    }

    public function test_assigning_venue_persists(): void
    {
        [$event, $user] = $this->ctx();
        $venue = \App\Models\Venue::where('name', 'Doha Exhibition Center')->firstOrFail();

        // The event's location/venue is set in Settings (not the Venue tab anymore).
        Livewire::actingAs($user)->test(SettingsTab::class, ['event' => $event])
            ->set('venue_id', $venue->id)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame($venue->id, $event->fresh()->venue_id);
    }
}
