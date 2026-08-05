<?php

namespace Tests\Feature;

use App\Livewire\CheckInScan;
use App\Models\Event;
use App\Models\EventAgendaDay;
use App\Models\EventAgendaSession;
use App\Models\EventAttendee;
use App\Support\Badge;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The door of a room, and what a badge can say.
 *
 * A badge's QR is printed once and cannot carry a room, so the room is chosen
 * at the door: whoever is standing there scans the badge and taps the session
 * in front of them.
 */
class SessionCheckInTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $event = Event::factory()->create(['registration_open' => true]);
        $event->registrationForm();

        return $event->fresh();
    }

    private function slot(Event $event, string $title, ?string $date = null): EventAgendaSession
    {
        $day = EventAgendaDay::firstOrCreate(
            ['event_id' => $event->id, 'date' => $date ?? now()->toDateString()],
            ['label' => 'Day', 'sort' => 1],
        );

        return EventAgendaSession::create([
            'event_id' => $event->id, 'agenda_day_id' => $day->id, 'title' => $title,
            'type' => 'session', 'status' => 'confirmed', 'starts_at' => '09:00',
            'ends_at' => '10:00', 'sort' => 1,
        ]);
    }

    private function attendee(Event $event): EventAttendee
    {
        return $event->attendees()->create([
            'name' => 'Dr Layla', 'email' => 'layla@example.test', 'status' => 'registered',
        ]);
    }

    private function scan(Event $event, EventAttendee $a)
    {
        return Livewire::test(CheckInScan::class, [
            'token' => $event->checkinToken(), 'reference' => $a->checkInCode(),
        ]);
    }

    /* ── the room ── */

    public function test_the_door_offers_only_what_they_booked_today(): void
    {
        $event = $this->event();
        $today = $this->slot($event, 'This morning');
        $tomorrow = $this->slot($event, 'Tomorrow', now()->addDay()->toDateString());

        $layla = $this->attendee($event);
        $layla->sessions()->sync([$today->id, $tomorrow->id]);

        $offered = $this->scan($event, $layla)->instance()->sessionsToday();

        $this->assertSame(['This morning'], $offered->pluck('title')->all());
    }

    public function test_admitting_to_a_room_records_the_time_in_that_room(): void
    {
        $event = $this->event();
        $session = $this->slot($event, 'Workshop');
        $layla = $this->attendee($event);
        $layla->sessions()->sync([$session->id]);

        $this->scan($event, $layla)->call('admitToSession', $session->id);

        $pivot = $layla->fresh()->sessions()->first()->pivot;

        $this->assertNotNull($pivot->checked_in_at);
    }

    /** Being in a room is being in the building. */
    public function test_a_side_door_still_marks_them_present_at_the_event(): void
    {
        $event = $this->event();
        $session = $this->slot($event, 'Workshop');
        $layla = $this->attendee($event);
        $layla->sessions()->sync([$session->id]);

        $this->assertNull($layla->checked_in_at);

        $this->scan($event, $layla)->call('admitToSession', $session->id);

        $layla->refresh();

        $this->assertNotNull($layla->checked_in_at);
        $this->assertSame('checked_in', $layla->status);
    }

    public function test_admitting_twice_does_not_move_the_time(): void
    {
        $event = $this->event();
        $session = $this->slot($event, 'Workshop');
        $layla = $this->attendee($event);
        $layla->sessions()->sync([$session->id]);

        $c = $this->scan($event, $layla)->call('admitToSession', $session->id);

        $first = $layla->fresh()->sessions()->first()->pivot->checked_in_at;

        $c->call('admitToSession', $session->id);

        $this->assertSame($first, $layla->fresh()->sessions()->first()->pivot->checked_in_at);
    }

    public function test_a_room_they_never_booked_cannot_admit_them(): void
    {
        $event = $this->event();
        $booked = $this->slot($event, 'Workshop');
        $other = $this->slot($event, 'A room they are not in');

        $layla = $this->attendee($event);
        $layla->sessions()->sync([$booked->id]);

        $this->scan($event, $layla)->call('admitToSession', $other->id);

        $this->assertCount(1, $layla->fresh()->sessions);
        $this->assertNull($layla->fresh()->checked_in_at);
    }

    public function test_a_cancelled_registration_is_admitted_nowhere(): void
    {
        $event = $this->event();
        $session = $this->slot($event, 'Workshop');

        $layla = $this->attendee($event);
        $layla->sessions()->sync([$session->id]);
        $layla->update(['status' => 'cancelled']);

        $this->scan($event, $layla)->call('admitToSession', $session->id);

        $this->assertNull($layla->fresh()->sessions()->first()->pivot->checked_in_at);
    }

    /* ── what the badge can print ── */

    public function test_a_badge_prints_a_question_this_event_asked(): void
    {
        $event = $this->event();
        $event->registrationFields()->create([
            'key' => 'track', 'label' => 'Track', 'type' => 'select',
            'options' => ['A', 'B'], 'position' => 20,
        ]);
        $event->update(['badge_template' => ['lines' => ['track']]]);

        $layla = $this->attendee($event);
        $layla->update(['answers' => ['track' => 'Track B — Practice']]);

        $lines = Badge::lines($event->fresh(), $layla->fresh());

        $this->assertSame([['label' => 'Track', 'value' => 'Track B — Practice']], $lines);
    }

    /** A blank line costs a real one on a badge 90mm wide. */
    public function test_a_question_they_left_blank_prints_nothing(): void
    {
        $event = $this->event();
        $event->registrationFields()->create(['key' => 'track', 'label' => 'Track', 'type' => 'text', 'position' => 20]);
        $event->update(['badge_template' => ['lines' => ['track']]]);

        $this->assertSame([], Badge::lines($event->fresh(), $this->attendee($event)));
    }

    public function test_a_badge_can_print_the_sessions_somebody_booked(): void
    {
        $event = $this->event();
        $event->registrationFields()->create(['key' => 'sessions', 'label' => 'Sessions', 'type' => 'sessions', 'position' => 20]);
        $event->update(['badge_template' => ['lines' => ['sessions']]]);

        $session = $this->slot($event, 'Workshop — policy');
        $layla = $this->attendee($event);
        $layla->sessions()->sync([$session->id]);

        $lines = Badge::lines($event->fresh(), $layla->fresh()->load('sessions'));

        $this->assertSame('Workshop — policy', $lines[0]['value']);
    }
}
