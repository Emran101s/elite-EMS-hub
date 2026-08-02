<?php

namespace App\Livewire;

use App\Models\RegistrationField;
use App\Models\RegistrationTemplate;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The registration forms you keep.
 *
 * Making a form editable per event immediately means rebuilding the same
 * conference form by hand every time you run a conference. These are those
 * forms, kept — and an event starts from a copy it then owns.
 */
#[Layout('components.layouts.app', [
    'title' => 'Registration templates',
    'subtitle' => 'Question sets you build once. An event starts from a copy and owns it after.',
])]
class RegistrationTemplates extends Component
{
    /** The template open in the editor; 0 is a new one, null is none. */
    public ?int $editingId = null;

    public string $name = '';
    public string $note = '';

    /** @var list<array<string,mixed>> */
    public array $fields = [];

    public function mount(): void
    {
        if (RegistrationTemplate::count() === 0) {
            RegistrationTemplate::seedDefaults();
        }
    }

    public function newTemplate(): void
    {
        Gate::authorize('write');

        $this->reset(['name', 'note', 'fields']);
        $this->editingId = 0;
        $this->fields = [];
    }

    public function edit(int $id): void
    {
        Gate::authorize('write');

        $t = RegistrationTemplate::findOrFail($id);

        $this->editingId = $t->id;
        $this->name = $t->name;
        $this->note = (string) $t->note;
        $this->fields = $t->questions()->all();
    }

    public function cancel(): void
    {
        $this->reset(['editingId', 'name', 'note', 'fields']);
    }

    public function addQuestion(): void
    {
        $this->fields[] = [
            'key' => '', 'label' => '', 'type' => 'text', 'required' => false,
            'options' => null, 'help' => null, 'placeholder' => null, 'maps_to' => null,
        ];
    }

    public function removeQuestion(int $i): void
    {
        unset($this->fields[$i]);
        $this->fields = array_values($this->fields);
    }

    public function moveQuestion(int $i, int $by): void
    {
        if (! isset($this->fields[$i], $this->fields[$i + $by])) {
            return;
        }

        [$this->fields[$i], $this->fields[$i + $by]] = [$this->fields[$i + $by], $this->fields[$i]];
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'name' => 'required|string|max:120',
            'note' => 'nullable|string|max:200',
            'fields.*.label' => 'required|string|max:160',
            'fields.*.type' => 'required|in:'.implode(',', array_keys(RegistrationField::TYPES)),
        ], [], ['fields.*.label' => 'question']);

        $fields = [];
        $usedKeys = [];

        foreach ($this->fields as $f) {
            $label = trim($f['label'] ?? '');

            if ($label === '') {
                continue;
            }

            // A key is derived once and kept: an event applying this template
            // files answers under it, and a template that renames its keys
            // every save would make two events disagree about the same
            // question.
            $key = trim((string) ($f['key'] ?? '')) ?: \Illuminate\Support\Str::of($label)->slug('_')->limit(40, '')->toString();

            while (in_array($key, $usedKeys, true)) {
                $key .= '_2';
            }

            $options = is_array($f['options'] ?? null)
                ? $f['options']
                : collect(preg_split('/\r?\n/', (string) ($f['options'] ?? '')))
                    ->map(fn ($o) => trim($o))->filter()->values()->all();

            $fields[] = [
                'key' => $key,
                'label' => $label,
                'type' => $f['type'] ?? 'text',
                'required' => (bool) ($f['required'] ?? false),
                'options' => $options ?: null,
                'help' => trim((string) ($f['help'] ?? '')) ?: null,
                'placeholder' => trim((string) ($f['placeholder'] ?? '')) ?: null,
                'maps_to' => ($f['maps_to'] ?? null) ?: null,
            ];

            $usedKeys[] = $key;
        }

        $data = ['name' => trim($this->name), 'note' => trim($this->note) ?: null, 'fields' => $fields];

        if ($this->editingId) {
            RegistrationTemplate::findOrFail($this->editingId)->update($data);
        } else {
            RegistrationTemplate::create($data + ['position' => (int) RegistrationTemplate::max('position') + 1]);
        }

        $this->cancel();
    }

    public function destroy(int $id): void
    {
        Gate::authorize('write');

        RegistrationTemplate::findOrFail($id)->delete();
        $this->cancel();
    }

    /** A copy to start the next one from. */
    public function duplicate(int $id): void
    {
        Gate::authorize('write');

        $t = RegistrationTemplate::findOrFail($id);

        $copy = $t->replicate();
        $copy->name = $t->name.' (copy)';
        $copy->position = (int) RegistrationTemplate::max('position') + 1;
        $copy->save();

        $this->edit($copy->id);
    }

    public function render()
    {
        return view('livewire.registration-templates', [
            'templates' => RegistrationTemplate::orderBy('position')->orderBy('name')->get(),
            'types' => RegistrationField::TYPES,
            'columns' => RegistrationField::CORE_COLUMNS,
        ]);
    }
}
