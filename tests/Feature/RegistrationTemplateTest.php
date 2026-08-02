<?php

namespace Tests\Feature;

use App\Livewire\Hub\RegistrationForm;
use App\Livewire\RegistrationTemplates;
use App\Models\Event;
use App\Models\RegistrationTemplate;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Forms you keep, and events that start from a copy.
 *
 * Making the form editable per event immediately means rebuilding the same
 * conference form by hand every time you run a conference. A template is that
 * form, kept — and applying one copies it, so editing next year's never
 * rewrites what last year's delegates were asked.
 */
class RegistrationTemplateTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::factory()->create(['registration_open' => true]);
    }

    private function template(array $fields, string $name = 'Conference'): RegistrationTemplate
    {
        return RegistrationTemplate::create(['name' => $name, 'fields' => $fields]);
    }

    /* ── applying one ── */

    public function test_a_template_adds_its_questions_to_an_event(): void
    {
        $event = $this->event();

        $added = $this->template([
            ['key' => 'track', 'label' => 'Which track?', 'type' => 'select', 'options' => ['A', 'B'], 'required' => true],
            ['key' => 'flight', 'label' => 'Arrival flight', 'type' => 'text'],
        ])->applyTo($event);

        $this->assertSame(2, $added);

        $form = $event->fresh()->registrationForm();

        $this->assertContains('track', $form->pluck('key')->all());
        $this->assertContains('flight', $form->pluck('key')->all());

        // …and the seven it always had are still in front of them.
        $this->assertSame('name', $form->first()->key);
    }

    /** A template is copied, not linked: editing it later changes nothing. */
    public function test_editing_the_template_afterwards_does_not_touch_the_event(): void
    {
        $event = $this->event();
        $template = $this->template([['key' => 'track', 'label' => 'Which track?', 'type' => 'text']]);

        $template->applyTo($event);

        $template->update(['fields' => [['key' => 'track', 'label' => 'COMPLETELY DIFFERENT', 'type' => 'text']]]);

        $this->assertSame('Which track?',
            $event->fresh()->registrationFields()->where('key', 'track')->value('label'));
    }

    /** An event that already asks something keeps its own wording. */
    public function test_a_question_the_event_already_asks_is_left_alone(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $event->registrationFields()->where('key', 'phone')->update(['label' => 'Mobile (WhatsApp)']);

        $added = $this->template([
            ['key' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'maps_to' => 'phone'],
            ['key' => 'track', 'label' => 'Which track?', 'type' => 'text'],
        ])->applyTo($event);

        $this->assertSame(1, $added, 'only the one it did not already ask');
        $this->assertSame('Mobile (WhatsApp)',
            $event->fresh()->registrationFields()->where('key', 'phone')->value('label'));
    }

    /** Two questions filling one column would overwrite each other silently. */
    public function test_a_second_question_cannot_claim_a_column_already_filled(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $this->template([
            ['key' => 'work_email', 'label' => 'Work email', 'type' => 'email', 'maps_to' => 'email'],
        ])->applyTo($event);

        $field = $event->fresh()->registrationFields()->where('key', 'work_email')->firstOrFail();

        $this->assertNull($field->maps_to, 'the column was taken, so this is kept as an answer instead');
    }

    /* ── replacing ── */

    public function test_replacing_clears_the_form_but_never_name_and_email(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $this->template([
            ['key' => 'track', 'label' => 'Which track?', 'type' => 'text'],
        ])->applyTo($event, replace: true);

        $keys = $event->fresh()->registrationForm()->pluck('key')->all();

        $this->assertSame(['name', 'email', 'track'], $keys);
    }

    public function test_replacing_is_not_what_happens_by_default(): void
    {
        $event = $this->event();
        $event->registrationForm();

        $this->template([['key' => 'track', 'label' => 'Which track?', 'type' => 'text']])->applyTo($event);

        $this->assertContains('dietary', $event->fresh()->registrationForm()->pluck('key')->all());
    }

    /* ── taking one from an event ── */

    public function test_a_template_can_be_taken_from_an_event_you_like(): void
    {
        $event = $this->event();
        $event->registrationForm();
        $event->registrationFields()->create([
            'key' => 'track', 'label' => 'Which track?', 'type' => 'select',
            'options' => ['A', 'B'], 'position' => 9,
        ]);

        $template = RegistrationTemplate::fromEvent($event, 'Like the summit');

        $this->assertSame('Like the summit', $template->name);
        $this->assertContains('track', $template->questions()->pluck('key')->all());
        $this->assertSame(['A', 'B'], $template->questions()->firstWhere('key', 'track')['options']);
    }

    /* ── the screen ── */

    public function test_the_library_seeds_three_to_start_from(): void
    {
        $c = Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(RegistrationTemplates::class);

        $names = $c->viewData('templates')->pluck('name');

        $this->assertCount(3, $names);
        $this->assertContains('Conference & Summit', $names->all());
    }

    public function test_a_template_can_be_written_and_kept(): void
    {
        Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(RegistrationTemplates::class)
            ->call('newTemplate')
            ->set('name', 'Press day')
            ->call('addQuestion')
            ->set('fields.0.label', 'Outlet')
            ->set('fields.0.type', 'text')
            ->call('addQuestion')
            ->set('fields.1.label', 'Do you need a camera position?')
            ->set('fields.1.type', 'select')
            ->set('fields.1.options', "Yes\nNo")
            ->call('save');

        $t = RegistrationTemplate::where('name', 'Press day')->firstOrFail();

        $this->assertCount(2, $t->questions());
        $this->assertSame('outlet', $t->questions()->first()['key']);
        $this->assertSame(['Yes', 'No'], $t->questions()->last()['options']);
    }

    public function test_applying_from_the_event_screen_reports_what_it_did(): void
    {
        $event = $this->event();
        $template = $this->template([['key' => 'track', 'label' => 'Which track?', 'type' => 'text']]);

        $c = Livewire::actingAs(User::factory()->create(['role' => 'manager']))
            ->test(RegistrationForm::class, ['event' => $event])
            ->set('templateId', $template->id)
            ->call('applyTemplate');

        $c->assertSet('templateFlash', fn (string $m) => str_contains($m, '1 question added'));
        $this->assertContains('track', $event->fresh()->registrationForm()->pluck('key')->all());

        // Applying the same one twice adds nothing, and says so.
        $c->set('templateId', $template->id)->call('applyTemplate');

        $c->assertSet('templateFlash', fn (string $m) => str_contains($m, 'already asks everything'));
    }

    public function test_a_viewer_cannot_apply_or_edit_templates(): void
    {
        $event = $this->event();
        $template = $this->template([['key' => 'track', 'label' => 'Which track?', 'type' => 'text']]);
        $viewer = User::factory()->create(['role' => 'viewer']);

        Livewire::actingAs($viewer)->test(RegistrationForm::class, ['event' => $event])
            ->set('templateId', $template->id)->call('applyTemplate')->assertForbidden();

        Livewire::actingAs($viewer)->test(RegistrationTemplates::class)
            ->call('newTemplate')->assertForbidden();
    }
}
