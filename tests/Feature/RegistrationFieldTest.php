<?php

namespace Tests\Feature;

use App\Livewire\Hub\RegistrationForm;
use App\Livewire\PublicRegistration;
use App\Models\Event;
use App\Models\RegistrationField;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * A registration form is a list of questions, not a set of columns.
 *
 * Every event asked the same seven things because those were the columns on
 * the attendee table, so an event needing a passport number or a workshop
 * track sent the desk back to a spreadsheet.
 */
class RegistrationFieldTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::factory()->create([
            'registration_open' => true,
            'registration_capacity' => null,
        ]);
    }

    private function form(Event $event, ?User $user = null)
    {
        return Livewire::actingAs($user ?? User::factory()->create(['role' => 'manager']))
            ->test(RegistrationForm::class, ['event' => $event]);
    }

    /* ── the form every event starts with ── */

    public function test_an_event_starts_with_the_form_it_always_had(): void
    {
        $event = $this->event();

        $keys = $event->registrationForm()->pluck('key')->all();

        $this->assertSame(
            ['name', 'email', 'phone', 'organization', 'job_title', 'ticket_type', 'dietary'],
            $keys,
        );

        // …and each of those still fills the column it always filled.
        $this->assertSame('email', $event->registrationFields()->where('key', 'email')->value('maps_to'));
    }

    /* ── asking this event's own question ── */

    public function test_a_question_can_be_added_and_its_answer_is_kept(): void
    {
        $event = $this->event();

        $this->form($event)
            ->call('newField')
            ->set('label', 'Which workshop track?')
            ->set('type', 'select')
            ->set('optionsText', "Track A\nTrack B")
            ->set('required', true)
            ->call('save');

        $field = $event->registrationFields()->where('label', 'Which workshop track?')->firstOrFail();

        $this->assertSame('which_workshop_track', $field->key);
        $this->assertNull($field->maps_to, 'a question of its own is kept as an answer');
        $this->assertSame(['Track A', 'Track B'], $field->options);

        // And a registrant can answer it.
        Livewire::test(PublicRegistration::class, ['token' => $event->registrationToken()])
            ->set('form.name', 'Dr Layla')
            ->set('form.email', 'layla@example.test')
            ->set('form.which_workshop_track', 'Track B')
            ->call('register');

        $attendee = $event->attendees()->firstOrFail();

        $this->assertSame('Dr Layla', $attendee->name);
        $this->assertSame('Track B', $attendee->answer('which_workshop_track'));
    }

    public function test_a_required_question_has_to_be_answered(): void
    {
        $event = $this->event();

        $this->form($event)->call('newField')
            ->set('label', 'Passport number')->set('type', 'text')->set('required', true)->call('save');

        Livewire::test(PublicRegistration::class, ['token' => $event->registrationToken()])
            ->set('form.name', 'Dr Layla')
            ->set('form.email', 'layla@example.test')
            ->call('register')
            ->assertHasErrors('form.passport_number');

        $this->assertSame(0, $event->attendees()->count());
    }

    /** A choice has to be one of the choices — otherwise the list is decoration. */
    public function test_an_answer_outside_the_offered_choices_is_refused(): void
    {
        $event = $this->event();

        $this->form($event)->call('newField')
            ->set('label', 'Track')->set('type', 'select')->set('optionsText', "A\nB")->call('save');

        Livewire::test(PublicRegistration::class, ['token' => $event->registrationToken()])
            ->set('form.name', 'Dr Layla')->set('form.email', 'layla@example.test')
            ->set('form.track', 'Z')
            ->call('register')
            ->assertHasErrors('form.track');
    }

    public function test_several_answers_come_back_as_a_list(): void
    {
        $event = $this->event();

        $this->form($event)->call('newField')
            ->set('label', 'Days attending')->set('type', 'multiselect')
            ->set('optionsText', "Monday\nTuesday\nWednesday")->call('save');

        Livewire::test(PublicRegistration::class, ['token' => $event->registrationToken()])
            ->set('form.name', 'Dr Layla')->set('form.email', 'layla@example.test')
            ->set('form.days_attending', ['Monday', 'Wednesday'])
            ->call('register');

        $this->assertSame('Monday, Wednesday', $event->attendees()->firstOrFail()->answer('days_attending'));
    }

    /* ── the rules that keep it safe ── */

    public function test_name_and_email_cannot_be_taken_off(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $email = $event->registrationFields()->where('key', 'email')->firstOrFail();

        $this->form($event)->call('remove', $email->id)->assertHasErrors('label');

        $this->assertTrue($event->registrationFields()->where('key', 'email')->exists());
    }

    public function test_a_question_nobody_needs_can_be_taken_off(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $dietary = $event->registrationFields()->where('key', 'dietary')->firstOrFail();

        $this->form($event)->call('remove', $dietary->id);

        $this->assertFalse($event->registrationFields()->where('key', 'dietary')->exists());
        $this->assertNotContains('dietary', $event->fresh()->registrationForm()->pluck('key')->all());
    }

    /** Renaming the question must never orphan the answers already filed. */
    public function test_renaming_a_question_leaves_its_key_alone(): void
    {
        $event = $this->event();

        $this->form($event)->call('newField')->set('label', 'Track')->set('type', 'text')->call('save');

        $field = $event->registrationFields()->where('key', 'track')->firstOrFail();

        $this->form($event)->call('edit', $field->id)
            ->set('label', 'Which track will you join?')->call('save');

        $field->refresh();

        $this->assertSame('track', $field->key);
        $this->assertSame('Which track will you join?', $field->label);
    }

    public function test_a_list_with_no_choices_is_refused(): void
    {
        $event = $this->event();

        $this->form($event)->call('newField')
            ->set('label', 'Track')->set('type', 'select')->set('optionsText', '  ')
            ->call('save')
            ->assertHasErrors('optionsText');
    }

    public function test_two_questions_cannot_fill_the_same_column(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $taken = $this->form($event)->call('newField')->viewData('taken');

        $this->assertContains('email', $taken);
        $this->assertContains('name', $taken);
    }

    public function test_questions_can_be_reordered(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $fields = $event->registrationFields()->get();
        $second = $fields[1];

        $this->form($event)->call('move', $second->id, -1);

        $this->assertSame('email', $event->fresh()->registrationFields()->first()->key);
    }

    public function test_a_viewer_cannot_change_the_form(): void
    {
        $event = $this->event();

        $this->form($event, User::factory()->create(['role' => 'viewer']))
            ->call('newField')->assertForbidden();
    }

    public function test_every_type_declares_what_it_validates(): void
    {
        foreach (array_keys(RegistrationField::TYPES) as $type) {
            $rules = (new RegistrationField(['type' => $type, 'required' => false]))->rules();

            $this->assertContains('nullable', $rules, $type.' must say it is optional');
            $this->assertGreaterThan(1, count($rules), $type.' must validate its shape');
        }
    }
}
