<?php

namespace Tests\Feature;

use App\Livewire\PublicRegistration;
use App\Models\Event;
use App\Models\EventAttendee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The one page in the platform a stranger is meant to reach.
 *
 * Attendees could only be typed in or imported from a spreadsheet, which meant
 * somebody was retyping a form somebody else had already filled in.
 *
 * Because it is public and unauthenticated, most of what is tested here is
 * what it REFUSES: a closed event, a full one, a second attempt from the same
 * address, and one visitor filling the list on their own.
 */
class PublicRegistrationTest extends TestCase
{
    use RefreshDatabase;

    private function openEvent(array $attrs = []): Event
    {
        return Event::factory()->create($attrs + [
            'name' => 'Arab Investment Summit',
            'stage' => 'planning',
            'registration_open' => true,
        ])->fresh();
    }

    private function fillIn(Event $event, string $email = 'someone@example.org'): Testable
    {
        return Livewire::test(PublicRegistration::class, ['token' => $event->registrationToken()])
            ->set('name', 'Layla Haddad')
            ->set('email', $email)
            ->set('organization', 'Jordan Investment Commission');
    }

    public function test_the_page_is_reached_by_token_and_needs_no_sign_in(): void
    {
        $event = $this->openEvent();

        $this->get(route('register.show', $event->registrationToken()))
            ->assertOk()
            ->assertSee('Arab Investment Summit')
            ->assertSee('Register for this event');
    }

    public function test_a_token_that_matches_nothing_is_a_404_rather_than_a_hint(): void
    {
        $this->get(route('register.show', 'not-a-real-token'))->assertNotFound();
    }

    public function test_the_token_is_not_the_event_id(): void
    {
        $event = $this->openEvent();

        $this->assertNotSame((string) $event->id, $event->registrationToken());
        $this->assertGreaterThan(16, strlen($event->registrationToken()));

        // The id must not open the page either.
        $this->get(route('register.show', (string) $event->id))->assertNotFound();
    }

    public function test_registering_creates_an_attendee_and_returns_their_reference(): void
    {
        $event = $this->openEvent();

        $this->fillIn($event)->call('register')->assertHasNoErrors();

        $attendee = $event->attendees()->firstOrFail();

        $this->assertSame('Layla Haddad', $attendee->name);
        $this->assertSame('registered', $attendee->status);
        $this->assertSame('Jordan Investment Commission', $attendee->organization);
        $this->assertTrue(
            $attendee->is(EventAttendee::findByReference($event->id, $attendee->reference())),
            'the reference on the badge finds the person again'
        );
    }

    /** The same person filling the form twice is one attendee, not two badges. */
    public function test_the_same_address_updates_the_earlier_registration(): void
    {
        $event = $this->openEvent();

        $this->fillIn($event, 'layla@example.org')->call('register');
        $this->fillIn($event, 'layla@example.org')->set('job_title', 'Head of IR')->call('register');

        $this->assertSame(1, $event->attendees()->count());
        $this->assertSame('Head of IR', $event->attendees()->first()->job_title);
    }

    public function test_a_closed_event_shows_no_form_and_refuses_a_submission_anyway(): void
    {
        $event = $this->openEvent(['registration_open' => false]);

        $this->get(route('register.show', $event->registrationToken()))
            ->assertOk()
            ->assertSee('Registration is closed')
            ->assertDontSee('Register for this event');

        // The page may have been open since before it closed.
        $this->fillIn($event)->call('register')->assertHasErrors('name');
        $this->assertSame(0, $event->attendees()->count());
    }

    public function test_a_full_event_says_so_and_stops_taking_names(): void
    {
        $event = $this->openEvent(['registration_capacity' => 1]);
        $this->fillIn($event, 'first@example.org')->call('register');

        $this->assertTrue($event->fresh()->registrationIsFull());

        $this->get(route('register.show', $event->registrationToken()))
            ->assertOk()
            ->assertSee('Fully booked');

        $this->fillIn($event, 'second@example.org')->call('register')->assertHasErrors('name');
        $this->assertSame(1, $event->attendees()->count());
    }

    /** Somebody who withdrew does not hold a place against the cap. */
    public function test_a_cancelled_registration_frees_its_place(): void
    {
        $event = $this->openEvent(['registration_capacity' => 1]);
        $this->fillIn($event, 'first@example.org')->call('register');

        $event->attendees()->first()->update(['status' => 'cancelled']);

        $this->assertFalse($event->fresh()->registrationIsFull());
        $this->fillIn($event, 'second@example.org')->call('register')->assertHasNoErrors();
        $this->assertSame(2, $event->attendees()->count());
    }

    /**
     * A public form with no session behind it. One visitor should not be able
     * to fill the list on their own.
     */
    public function test_one_visitor_cannot_fill_the_whole_list(): void
    {
        $event = $this->openEvent();
        RateLimiter::clear('register:'.$event->id.':127.0.0.1');

        foreach (range(1, 5) as $i) {
            $this->fillIn($event, "guest{$i}@example.org")->call('register')->assertHasNoErrors();
        }

        $this->fillIn($event, 'guest6@example.org')->call('register')->assertHasErrors('name');
        $this->assertSame(5, $event->attendees()->count());
    }

    public function test_a_registration_needs_a_name_and_a_real_address(): void
    {
        $event = $this->openEvent();

        Livewire::test(PublicRegistration::class, ['token' => $event->registrationToken()])
            ->set('name', '')
            ->set('email', 'not-an-address')
            ->call('register')
            ->assertHasErrors(['name', 'email']);

        $this->assertSame(0, $event->attendees()->count());
    }

    public function test_an_archived_event_stops_taking_registrations(): void
    {
        $event = $this->openEvent();
        $event->update(['archived_at' => now()]);

        $this->assertFalse($event->fresh()->registrationIsLive());
        $this->fillIn($event)->call('register')->assertHasErrors('name');
    }
}
