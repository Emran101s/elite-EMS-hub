<?php

namespace Tests\Feature;

use App\Livewire\ArrivalsDesk;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The desk on the day.
 *
 * Scanning works when somebody has a badge. The desk is for the rest of it —
 * the one who left theirs at the hotel, the one whose name is spelled
 * differently to the list. That is most of the first hour.
 */
class ArrivalsDeskTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::factory()->create();
    }

    private function person(Event $event, string $name, array $extra = [])
    {
        return $event->attendees()->create($extra + [
            'name' => $name,
            'email' => str($name)->slug().'@example.test',
            'status' => 'registered',
        ]);
    }

    private function desk(Event $event)
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'coordinator']))
            ->test(ArrivalsDesk::class, ['event' => $event]);
    }

    public function test_nothing_is_listed_until_somebody_types(): void
    {
        $event = $this->event();
        $this->person($event, 'Layla Haddad');

        $c = $this->desk($event);

        $this->assertCount(0, $c->viewData('matches'), 'six hundred names is not an answer to "is Layla here"');

        $this->assertCount(1, $c->set('q', 'la')->viewData('matches'));
    }

    public function test_somebody_is_found_by_name_email_or_organisation(): void
    {
        $event = $this->event();
        $this->person($event, 'Layla Haddad', ['organization' => 'Ministry of Health']);

        $this->assertCount(1, $this->desk($event)->set('q', 'haddad')->viewData('matches'));
        $this->assertCount(1, $this->desk($event)->set('q', 'ministry')->viewData('matches'));
        $this->assertCount(1, $this->desk($event)->set('q', 'layla-haddad@')->viewData('matches'));
        $this->assertCount(0, $this->desk($event)->set('q', 'nobody')->viewData('matches'));
    }

    public function test_admitting_marks_them_present(): void
    {
        $event = $this->event();
        $layla = $this->person($event, 'Layla Haddad');

        $this->desk($event)->set('q', 'layla')->call('admit', $layla->id)
            ->assertSet('justAdmitted', $layla->id);

        $layla->refresh();

        $this->assertSame('checked_in', $layla->status);
        $this->assertNotNull($layla->checked_in_at);
    }

    /** A desk that cannot undo is a desk that argues. */
    public function test_admitting_the_wrong_person_can_be_undone(): void
    {
        $event = $this->event();
        $layla = $this->person($event, 'Layla Haddad');

        $c = $this->desk($event)->set('q', 'layla')->call('admit', $layla->id);

        $c->call('undo', $layla->id)->assertSet('justAdmitted', null);

        $layla->refresh();

        $this->assertSame('registered', $layla->status);
        $this->assertNull($layla->checked_in_at);
    }

    public function test_a_cancelled_registration_is_not_admitted(): void
    {
        $event = $this->event();
        $layla = $this->person($event, 'Layla Haddad', ['status' => 'cancelled']);

        $this->desk($event)->set('q', 'layla')->call('admit', $layla->id);

        $this->assertNull($layla->fresh()->checked_in_at);
    }

    public function test_the_counts_say_where_the_door_is_up_to(): void
    {
        $event = $this->event();
        $a = $this->person($event, 'Layla Haddad');
        $this->person($event, 'Omar Nassar');
        $this->person($event, 'Someone Cancelled', ['status' => 'cancelled']);

        $c = $this->desk($event)->call('admit', $a->id);

        $this->assertSame(2, $c->viewData('expected'), 'a cancellation is not expected');
        $this->assertSame(1, $c->viewData('arrived'));
        $this->assertSame(1, $c->viewData('toCome'));
        $this->assertSame(50, $c->viewData('pct'));
    }

    public function test_admitting_twice_does_not_move_the_time(): void
    {
        $event = $this->event();
        $layla = $this->person($event, 'Layla Haddad');

        $c = $this->desk($event)->call('admit', $layla->id);
        $first = $layla->fresh()->checked_in_at;

        $c->call('admit', $layla->id);

        $this->assertEquals($first, $layla->fresh()->checked_in_at);
    }
}
