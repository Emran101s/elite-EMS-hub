<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventScopeItem;
use App\Models\User;
use App\Support\ScopeStatus;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The Delivery Scope register.
 *
 * Reads deliverables, groups them by workstream, and asks ScopeStatus what
 * state each is in. It never writes a status — see that class for why.
 */
class ScopeTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** Filter the register to one person's accountability. */
    public ?int $ownerFilter = null;

    public string $title = '';

    public string $workstream = 'delivery';

    public string $definition_of_done = '';

    public string $out_of_scope = '';

    public ?int $owner_id = null;

    public int $offset_days = -14;

    public string $source_type = '';

    public ?int $source_id = null;

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    public function newItem(): void
    {
        Gate::authorize('write');
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        Gate::authorize('write');
        $item = $this->event->scopeItems()->findOrFail($id);

        $this->editingId = $item->id;
        $this->title = $item->title;
        $this->workstream = $item->workstream;
        $this->definition_of_done = (string) $item->definition_of_done;
        $this->out_of_scope = (string) $item->out_of_scope;
        $this->owner_id = $item->owner_id;
        $this->offset_days = $item->offset_days;
        $this->source_type = (string) $item->source_type;
        $this->source_id = $item->source_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'workstream' => ['required', 'in:'.implode(',', array_keys(Taxonomy::options('scope_workstream')))],
            'definition_of_done' => ['nullable', 'string', 'max:400'],
            'out_of_scope' => ['nullable', 'string', 'max:400'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'offset_days' => ['required', 'integer', 'between:-720,720'],
            'source_type' => ['nullable', 'in:'.implode(',', array_keys(ScopeStatus::SOURCES))],
            'source_id' => ['nullable', 'integer'],
        ]);

        $data = [
            'title' => trim($this->title),
            'workstream' => $this->workstream,
            'definition_of_done' => trim($this->definition_of_done) ?: null,
            'out_of_scope' => trim($this->out_of_scope) ?: null,
            'owner_id' => $this->owner_id,
            'offset_days' => $this->offset_days,
            'source_type' => $this->source_type ?: null,
            // A source_id only means something for the sources that need one.
            'source_id' => $this->source_type === 'task' ? $this->source_id : null,
        ];

        if ($this->editingId) {
            $this->event->scopeItems()->findOrFail($this->editingId)->update($data);
        } else {
            $this->event->scopeItems()->create($data + [
                'position' => (int) $this->event->scopeItems()
                    ->where('workstream', $this->workstream)->max('position') + 1,
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

    public function filterOwner(?int $id): void
    {
        $this->ownerFilter = $this->ownerFilter === $id ? null : $id;
    }

    private function resetForm(): void
    {
        $this->reset(['editingId', 'title', 'definition_of_done', 'out_of_scope',
            'owner_id', 'source_type', 'source_id']);
        $this->workstream = 'delivery';
        $this->offset_days = -14;
    }

    public function render()
    {
        // One query for the register, one for the sources each row reads. The
        // status lookups run against already-loaded relations, so a scope of
        // sixty deliverables does not cost sixty round trips.
        $this->event->loadMissing([
            'suppliers', 'agendaSessions', 'speakers', 'venue', 'roomBlocks', 'approvals',
        ]);

        $items = $this->event->scopeItems()->with('owner')->get();

        // Hand every row the event we already loaded. Without this each item
        // lazily fetches its own Event — and then that instance's suppliers,
        // sessions and speakers — so a scope of thirty deliverables cost
        // thirty round trips through the same relations. The N+1 guard in
        // DeliveryScopeTest measured 69 queries before this line existed.
        $items->each(fn (EventScopeItem $i) => $i->setRelation('event', $this->event));

        // Captured before the filter is applied: the chip row has to keep
        // offering every owner, or picking one would leave you no way back.
        $owners = $items->pluck('owner')->filter()->unique('id')->sortBy('name')->values();

        if ($this->ownerFilter) {
            $items = $items->where('owner_id', $this->ownerFilter);
        }

        $labels = Taxonomy::options('scope_workstream');

        $rows = $items->map(fn (EventScopeItem $i) => [
            'model' => $i,
            'status' => ScopeStatus::for($i),
        ]);

        return view('livewire.hub.scope-tab', [
            'groups' => $rows->groupBy(fn ($r) => $r['model']->workstream)
                ->map(fn ($g, $k) => ['label' => $labels[$k] ?? ucfirst($k), 'rows' => $g]),
            'workstreams' => $labels,
            'sources' => ScopeStatus::SOURCES,
            'users' => User::orderBy('name')->get(),
            // Owners with something on their plate, for the filter row.
            'owners' => $owners,
            'summary' => [
                'total' => $rows->count(),
                'met' => $rows->where('status.state', ScopeStatus::MET)->count(),
                'unowned' => $rows->filter(fn ($r) => ! $r['model']->owner_id)->count(),
                'overdue' => $rows->filter(fn ($r) => $r['model']->isOverdue())->count(),
            ],
        ]);
    }
}
