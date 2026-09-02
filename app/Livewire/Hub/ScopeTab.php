<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The Scope of Work: what the client has asked us to deliver, written here and
 * rendered by the Event Brief.
 *
 * An authoring surface, not a status board. Writing a line, revising it and
 * seeing the whole scope read back are the only three things it does.
 */
class ScopeTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $title = '';

    public string $area = 'general';

    public string $body = '';

    public bool $is_exclusion = false;

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    public function newItem(bool $exclusion = false): void
    {
        Gate::authorize('write');
        $this->resetForm();
        $this->is_exclusion = $exclusion;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('write');
        $item = $this->event->scopeItems()->findOrFail($id);

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->area = $item->area;
        $this->body = (string) $item->body;
        $this->is_exclusion = $item->is_exclusion;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'area' => ['required', 'in:'.implode(',', array_keys(Taxonomy::options('scope_area')))],
            'body' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'title' => trim($this->title),
            'area' => $this->area,
            'body' => trim($this->body) ?: null,
            'is_exclusion' => $this->is_exclusion,
        ];

        if ($this->editingId) {
            $this->event->scopeItems()->findOrFail($this->editingId)->update($data);
        } else {
            $this->event->scopeItems()->create($data + [
                'position' => (int) $this->event->scopeItems()->where('area', $this->area)->max('position') + 1,
            ]);
        }

        $this->resetForm();
        $this->showForm = false;
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        $this->event->scopeItems()->findOrFail($id)->delete();
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'body', 'is_exclusion']);
        $this->area = 'general';
    }

    public function render()
    {
        $items = $this->event->scopeItems()->get();
        $labels = Taxonomy::options('scope_area');

        return view('livewire.hub.scope-tab', [
            // In scope reads as the body of the document, grouped by area.
            // Exclusions are gathered at the end, where a scope of work puts
            // them, rather than mixed in with what we are doing.
            'groups' => $items->where('is_exclusion', false)
                ->groupBy('area')
                ->map(fn ($g, $k) => ['label' => $labels[$k] ?? ucfirst($k), 'rows' => $g]),
            'exclusions' => $items->where('is_exclusion', true),
            'areas' => $labels,
            'total' => $items->count(),
        ]);
    }
}
