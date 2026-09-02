<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\User;
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

    public string $type = 'general';

    public string $body = '';

    public string $quantity = '';

    public ?int $owner_id = null;

    public bool $is_exclusion = false;

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    /**
     * $presetType comes from a group card's own "+ Add to this type" button —
     * starting the form already scoped to the type you were just reading, so
     * adding a second line to the same one is a one-click affair rather than
     * a trip back through a generic picker.
     */
    public function newItem(bool $exclusion = false, ?string $presetType = null): void
    {
        Gate::authorize('write');
        $this->resetForm();
        $this->is_exclusion = $exclusion;

        if ($presetType && array_key_exists($presetType, Taxonomy::options('scope_type'))) {
            $this->type = $presetType;
        }

        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('write');
        $item = $this->event->scopeItems()->findOrFail($id);

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->type = $item->type;
        $this->body = (string) $item->body;
        $this->quantity = (string) $item->quantity;
        $this->owner_id = $item->owner_id;
        $this->is_exclusion = $item->is_exclusion;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'type' => ['required', 'in:'.implode(',', array_keys(Taxonomy::options('scope_type')))],
            'body' => ['nullable', 'string', 'max:2000'],
            'quantity' => ['nullable', 'string', 'max:60'],
            'owner_id' => ['nullable', 'exists:users,id'],
        ]);

        $data = [
            'title' => trim($this->title),
            'type' => $this->type,
            'body' => trim($this->body) ?: null,
            'quantity' => trim($this->quantity) ?: null,
            'owner_id' => $this->owner_id,
            'is_exclusion' => $this->is_exclusion,
        ];

        if ($this->editingId) {
            $this->event->scopeItems()->findOrFail($this->editingId)->update($data);
        } else {
            $this->event->scopeItems()->create($data + [
                'position' => (int) $this->event->scopeItems()->where('type', $this->type)->max('position') + 1,
            ]);
        }

        $wasEdit = (bool) $this->editingId;
        $savedTitle = $data['title'];

        $this->resetForm();
        $this->showForm = false;

        // A modal that closes is not, on its own, confirmation that anything
        // happened — the row it affected may be off-screen or hard to spot
        // among a dozen others. This is a browser event so the toast can
        // dismiss itself on a timer without a round trip back to the server.
        $this->dispatch('scope-item-saved', title: $savedTitle, wasEdit: $wasEdit);
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        $item = $this->event->scopeItems()->findOrFail($id);
        $title = $item->title;
        $item->delete();

        $this->dispatch('scope-item-deleted', title: $title);
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'body', 'quantity', 'owner_id', 'is_exclusion']);
        $this->type = 'general';
    }

    public function render()
    {
        $items = $this->event->scopeItems()->with('owner')->get();
        $labels = Taxonomy::options('scope_type');

        return view('livewire.hub.scope-tab', [
            // In scope reads as the body of the document, grouped by type.
            // Exclusions are gathered at the end, where a scope of work puts
            // them, rather than mixed in with what we are doing.
            'groups' => $items->where('is_exclusion', false)
                ->groupBy('type')
                ->map(fn ($g, $k) => ['label' => $labels[$k] ?? ucfirst($k), 'rows' => $g]),
            'exclusions' => $items->where('is_exclusion', true),
            'types' => $labels,
            'users' => User::orderBy('name')->get(),
            'total' => $items->count(),
        ]);
    }
}
