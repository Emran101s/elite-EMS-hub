<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\RegistrationField;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The questions this event's registration form asks.
 *
 * Every event used to ask the same seven things, because those were the
 * columns on the attendee table. An event needing a passport number, a
 * workshop track or an arrival flight had nowhere to put the answer, so the
 * desk went back to a spreadsheet — the thing the public form exists to
 * replace.
 *
 * Two rules hold this together, and both are enforced here rather than left to
 * whoever edits a form on a Friday:
 *
 *  - Name and email cannot be removed. They are what identifies a person,
 *    what the badge prints and what stops the same person registering twice.
 *  - A question's key never changes. Renaming the label is free; the key is
 *    what every answer already given is filed under.
 */
class RegistrationForm extends Component
{
    public Event $event;

    /** The field being edited; 0 is a new one, null is none. */
    public ?int $editingId = null;

    public string $label = '';
    public string $type = 'text';
    public bool $required = false;
    public string $optionsText = '';
    public string $help = '';
    public string $placeholder = '';
    public ?string $maps_to = null;

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->event->registrationForm();   // seeded on first open
    }

    public function newField(): void
    {
        Gate::authorize('write');

        $this->reset(['label', 'optionsText', 'help', 'placeholder', 'maps_to', 'required']);
        $this->editingId = 0;
        $this->type = 'text';
    }

    public function edit(int $id): void
    {
        Gate::authorize('write');

        $f = $this->event->registrationFields()->findOrFail($id);

        $this->editingId = $f->id;
        $this->label = $f->label;
        $this->type = $f->type;
        $this->required = $f->required;
        $this->optionsText = implode("\n", $f->options ?? []);
        $this->help = (string) $f->help;
        $this->placeholder = (string) $f->placeholder;
        $this->maps_to = $f->maps_to;
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'label', 'optionsText', 'help', 'placeholder', 'maps_to', 'required']);
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'label' => 'required|string|max:160',
            'type' => 'required|in:'.implode(',', array_keys(RegistrationField::TYPES)),
            'help' => 'nullable|string|max:200',
            'placeholder' => 'nullable|string|max:120',
            'maps_to' => 'nullable|in:'.implode(',', array_keys(RegistrationField::CORE_COLUMNS)),
        ]);

        $options = collect(preg_split('/\r?\n/', $this->optionsText))
            ->map(fn ($o) => trim($o))->filter()->values()->all();

        // A list with nothing in it is not a list; it would render as a select
        // nobody can answer.
        if (in_array($this->type, ['select', 'multiselect'], true) && $options === []) {
            $this->addError('optionsText', 'Give this question at least one choice, one per line.');

            return;
        }

        $fields = [
            'label' => trim($this->label),
            'type' => $this->type,
            'required' => $this->required,
            'options' => $options ?: null,
            'help' => trim($this->help) ?: null,
            'placeholder' => trim($this->placeholder) ?: null,
        ];

        if ($this->editingId) {
            // The key and what it fills stay put: answers are already filed
            // under both, and moving either orphans them.
            $this->event->registrationFields()->findOrFail($this->editingId)->update($fields);
        } else {
            $this->event->registrationFields()->create($fields + [
                'key' => RegistrationField::keyFor($this->event, $this->label),
                'maps_to' => $this->maps_to ?: null,
                'position' => (int) $this->event->registrationFields()->max('position') + 1,
            ]);
        }

        $this->cancel();
    }

    /**
     * Take a question off the form.
     *
     * Answers already given are kept — they are on the attendee, and deleting
     * the question does not unask it of the people who answered.
     */
    public function remove(int $id): void
    {
        Gate::authorize('write');

        $field = $this->event->registrationFields()->findOrFail($id);

        if ($field->isLocked()) {
            $this->addError('label', 'Name and email cannot be removed — they are how a person is identified.');

            return;
        }

        $field->delete();
        $this->cancel();
    }

    public function move(int $id, int $by): void
    {
        Gate::authorize('write');

        $fields = $this->event->registrationFields()->get()->values();
        $at = $fields->search(fn (RegistrationField $f) => $f->id === $id);

        if ($at === false || ! isset($fields[$at + $by])) {
            return;
        }

        $other = $fields[$at + $by];
        $mine = $fields[$at];

        [$a, $b] = [$mine->position, $other->position];

        if ($a === $b) {
            [$a, $b] = [$a, $b + $by];
        }

        $mine->update(['position' => $b]);
        $other->update(['position' => $a]);
    }

    public function render()
    {
        return view('livewire.hub.registration-form', [
            'fields' => $this->event->registrationFields()->get(),
            'types' => RegistrationField::TYPES,
            'columns' => RegistrationField::CORE_COLUMNS,
            'taken' => $this->event->registrationFields()->whereNotNull('maps_to')
                ->where('id', '!=', $this->editingId ?: 0)->pluck('maps_to')->all(),
        ]);
    }
}
