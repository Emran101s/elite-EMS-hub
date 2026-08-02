<?php

namespace Tests\Feature;

use App\Livewire\Hub\AttendeesTab;
use App\Livewire\PublicRegistration;
use App\Models\Event;
use App\Models\EventAgendaDay;
use App\Models\EventAgendaSession;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Picking sessions at registration.
 *
 * An answer is a string on a person: it cannot be counted against a room's
 * capacity, and it cannot be handed to whoever is standing at the door. A seat
 * is a row, so both of those become possible.
 */
class RegistrationSessionsTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        $event = Event::factory()->create(['registration_open' => true]);
        $event->registrationForm();
        $event->registrationFields()->create([
            'key' => 'sessions', 'label' => 'Which sessions will you attend?',
            'type' => 'sessions', 'position' => 20,
        ]);

        return $event->fresh();
    }

    private function slot(Event $event, string $title, ?int $capacity = null): EventAgendaSession
    {
        $day = EventAgendaDay::firstOrCreate(
            ['event_id' => $event->id, 'date' => now()->addMonth()->toDateString()],
            ['label' => 'Day one', 'sort' => 1],
        );

        return EventAgendaSession::create([
            'event_id' => $event->id, 'agenda_day_id' => $day->id,
            'title' => $title, 'type' => 'session', 'status' => 'confirmed',
            'starts_at' => '09:00', 'ends_at' => '10:00', 'capacity' => $capacity, 'sort' => 1,
        ]);
    }

    private function register(Event $event, string $email, array $sessionIds)
    {
        return Livewire::test(PublicRegistration::class, ['token' => $event->registrationToken()])
            ->set('form.name', 'Dr Layla')
            ->set('form.email', $email)
            ->set('form.sessions', $sessionIds)
            ->call('register');
    }

    /* ── booking a seat ── */

    public function test_picking_sessions_books_a_seat_in_each(): void
    {
        $event = $this->event();
        $a = $this->slot($event, 'Opening plenary');
        $b = $this->slot($event, 'Workshop — policy');

        $this->register($event, 'layla@example.test', [$a->id, $b->id])->assertHasNoErrors();

        $attendee = $event->attendees()->firstOrFail();

        $this->assertSame(['Opening plenary', 'Workshop — policy'],
            $attendee->sessions()->orderBy('title')->pluck('title')->all());

        $this->assertSame(1, $a->fresh()->bookedCount());
    }

    /** The agenda is read live: a session added today is offered today. */
    public function test_the_question_offers_whatever_is_on_the_agenda_now(): void
    {
        $event = $this->event();
        $this->slot($event, 'Opening plenary');

        $field = $event->registrationFields()->where('key', 'sessions')->firstOrFail();
        $this->assertCount(1, $field->sessionChoices());

        $this->slot($event, 'Added later');

        $this->assertCount(2, $field->fresh()->sessionChoices());
    }

    /* ── capacity ── */

    public function test_a_full_session_refuses_another_booking(): void
    {
        $event = $this->event();
        $small = $this->slot($event, 'Roundtable', capacity: 1);

        $this->register($event, 'first@example.test', [$small->id])->assertHasNoErrors();

        $this->assertTrue($small->fresh()->isFull());

        $this->register($event, 'second@example.test', [$small->id])
            ->assertHasErrors('form.sessions');

        $this->assertSame(1, $small->fresh()->bookedCount());
    }

    /** Re-registering must not read as a new booking against a full session. */
    public function test_somebody_already_in_the_room_keeps_their_seat(): void
    {
        $event = $this->event();
        $small = $this->slot($event, 'Roundtable', capacity: 1);

        $this->register($event, 'layla@example.test', [$small->id])->assertHasNoErrors();

        $this->register($event, 'layla@example.test', [$small->id])->assertHasNoErrors();

        $this->assertSame(1, $small->fresh()->bookedCount());
    }

    public function test_a_session_with_no_capacity_is_never_full(): void
    {
        $event = $this->event();
        $open = $this->slot($event, 'Plenary');

        foreach (['a', 'b', 'c'] as $who) {
            $this->register($event, $who.'@example.test', [$open->id])->assertHasNoErrors();
        }

        $this->assertFalse($open->fresh()->isFull());
        $this->assertSame(3, $open->fresh()->bookedCount());
        $this->assertNull($open->fresh()->seatsLeft());
    }

    /** Changing your mind is what a second visit to the form means. */
    public function test_registering_again_replaces_the_seats(): void
    {
        $event = $this->event();
        $a = $this->slot($event, 'Opening plenary');
        $b = $this->slot($event, 'Workshop — policy');

        $this->register($event, 'layla@example.test', [$a->id]);
        $this->register($event, 'layla@example.test', [$b->id]);

        $attendee = $event->attendees()->firstOrFail();

        $this->assertSame(['Workshop — policy'], $attendee->sessions()->pluck('title')->all());
        $this->assertSame(0, $a->fresh()->bookedCount());
    }

    /* ── the spreadsheet ── */

    public function test_an_import_books_the_sessions_it_names(): void
    {
        $event = $this->event();
        $a = $this->slot($event, 'Opening plenary');
        $this->slot($event, 'Workshop — policy');

        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', UploadedFile::fake()->createWithContent('a.csv', implode("\n", [
                'Full name,Email address,Which sessions will you attend?',
                '"Dana Haddad",dana@icft.org,"Opening plenary, Workshop — policy"',
            ])))
            ->call('import')
            ->assertHasNoErrors();

        $dana = $event->attendees()->where('email', 'dana@icft.org')->firstOrFail();

        $this->assertCount(2, $dana->sessions);
        $this->assertSame(1, $a->fresh()->bookedCount());
    }

    /** A title that matches nothing is skipped, not invented. */
    public function test_an_import_ignores_a_session_that_does_not_exist(): void
    {
        $event = $this->event();
        $this->slot($event, 'Opening plenary');

        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', UploadedFile::fake()->createWithContent('a.csv', implode("\n", [
                'Full name,Email address,Which sessions will you attend?',
                '"Dana Haddad",dana@icft.org,"Opening plenary, A session nobody scheduled"',
            ])))
            ->call('import');

        $this->assertSame(['Opening plenary'],
            $event->attendees()->firstOrFail()->sessions()->pluck('title')->all());
    }

    /** An import that does not mention sessions must not empty them. */
    public function test_a_sheet_without_the_column_leaves_the_seats_alone(): void
    {
        $event = $this->event();
        $a = $this->slot($event, 'Opening plenary');

        $this->register($event, 'dana@icft.org', [$a->id]);

        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(AttendeesTab::class, ['event' => $event])
            ->set('importFile', UploadedFile::fake()->createWithContent('a.csv', implode("\n", [
                'Full name,Email address,Organisation',
                'Dana Haddad,dana@icft.org,Ministry of Culture',
            ])))
            ->call('import');

        $dana = $event->attendees()->where('email', 'dana@icft.org')->firstOrFail();

        $this->assertSame('Ministry of Culture', $dana->organization);
        $this->assertCount(1, $dana->sessions, 'the seat survived a sheet that said nothing about it');
    }
}
