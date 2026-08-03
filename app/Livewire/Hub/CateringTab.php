<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Livewire\Concerns\RoutesCostsToBudget;
use App\Models\Event;
use App\Models\EventCateringItem;
use Livewire\Attributes\Validate;
use Livewire\Component;

class CateringTab extends Component
{
    use BulkSelectable, RoutesCostsToBudget;

    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:120')]
    public string $title = '';

    #[Validate('required|string|max:20')]
    public string $type = 'coffee_break';

    #[Validate('nullable|date')]
    public string $occasion_date = '';

    #[Validate('required|in:in_house,outside')]
    public string $venue_mode = 'in_house';

    #[Validate('nullable|integer')]
    public string $room_id = '';

    #[Validate('nullable|string|max:160')]
    public string $location = '';

    #[Validate('nullable|integer|min:0')]
    public string $headcount = '';

    #[Validate('nullable|numeric|min:0')]
    public string $cost = '';

    public bool $per_person = false;

    #[Validate('nullable|integer')]
    public string $supplier_id = '';

    #[Validate('required|in:planned,confirmed,cancelled')]
    public string $status = 'planned';

    #[Validate('nullable|string|max:1000')]
    public string $notes = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'title', 'occasion_date', 'location', 'headcount', 'cost', 'supplier_id', 'notes']);
        $this->type = 'coffee_break';
        $this->venue_mode = 'in_house';
        $this->room_id = '';
        $this->per_person = true;
        $this->status = 'planned';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $c = $this->event->cateringItems()->findOrFail($id);
        $this->editingId = $c->id;
        $this->title = $c->title;
        $this->type = $c->type;
        $this->occasion_date = $c->occasion_date?->format('Y-m-d') ?? '';
        $this->venue_mode = $c->venue_mode;
        $this->room_id = $c->room_id ? (string) $c->room_id : '';
        $this->location = $c->location ?? '';
        $this->headcount = $c->headcount !== null ? (string) $c->headcount : '';
        $this->cost = $c->cost_cents ? (string) ($c->cost_cents / 100) : '';
        $this->per_person = (bool) $c->per_person;
        $this->supplier_id = $c->supplier_id ? (string) $c->supplier_id : '';
        $this->status = $c->status;
        $this->notes = $c->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'title' => $this->title,
            'type' => array_key_exists($this->type, EventCateringItem::TYPES) ? $this->type : 'other',
            'occasion_date' => $this->occasion_date !== '' ? $this->occasion_date : null,
            'venue_mode' => $this->venue_mode,
            // Never both: an in-house occasion has no free-text location, an
            // outside one has no room to point at.
            'room_id' => $this->venue_mode === 'in_house' && $this->room_id !== '' ? (int) $this->room_id : null,
            'location' => $this->venue_mode === 'outside' ? ($this->location ?: null) : null,
            'headcount' => $this->headcount !== '' ? (int) $this->headcount : null,
            'cost_cents' => (int) round((float) ($this->cost ?: 0) * 100),
            'per_person' => $this->per_person,
            'supplier_id' => $this->supplier_id !== '' ? (int) $this->supplier_id : null,
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ];

        $this->editingId
            ? $this->event->cateringItems()->findOrFail($this->editingId)->update($data)
            : $this->event->cateringItems()->create($data);

        $this->showForm = false;
        session()->flash('status', 'Food & beverage occasion saved.');
    }

    public function setStatus(int $id, string $status): void
    {
        abort_unless(in_array($status, EventCateringItem::STATUSES, true), 422);
        $this->event->cateringItems()->findOrFail($id)->update(['status' => $status]);
    }

    public function delete(int $id): void
    {
        $this->event->cateringItems()->whereKey($id)->delete();
    }

    public function deleteSelected(): void
    {
        $this->event->cateringItems()->whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    public function budgetModule(): string
    {
        return 'catering';
    }

    public function render()
    {
        $items = $this->event->cateringItems()->with(['room', 'supplier'])->get();

        // Grouped by day, because "what are we feeding people on the 9th" is
        // the question this list actually answers — not "what is item #7".
        $byDate = $items->groupBy(fn ($c) => $c->occasion_date?->toDateString() ?? '_undated');

        return view('livewire.hub.catering-tab', [
            'items' => $items,
            'byDate' => $byDate,
            'rooms' => $this->event->rooms,
            'suppliers' => \App\Models\Supplier::orderBy('name')->get(),
            'total' => $items->reject(fn ($c) => $c->status === 'cancelled')->sum(fn ($c) => $c->totalCents()),
            'covers' => $items->reject(fn ($c) => $c->status === 'cancelled')->sum('headcount'),
            'confirmed' => $items->where('status', 'confirmed')->count(),
        ]);
    }
}
