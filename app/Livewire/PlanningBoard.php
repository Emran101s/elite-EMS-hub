<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\PlanItem;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every deliverable in the book, across every event.
 *
 * Each event has its own Plan Studio, which answers "what does this event's
 * plan look like". This answers the question no single event can: where the
 * planning work actually is this week, and what is waiting on somebody.
 *
 * It is a board rather than a list because a deliverable's gate is the thing
 * you are managing — an item sitting in Need Approval is a person, not a date.
 * Nothing is re-derived: the gates, the colours, overdue and progress all come
 * from PlanItem, so the board and the studio cannot disagree.
 */
#[Layout('components.layouts.app', [
    'title' => 'Planning board',
    'subtitle' => 'Every deliverable across the book — what is moving, what is waiting, and what is late.',
])]
class PlanningBoard extends Component
{
    /** An event id, or 0 for the whole book. */
    #[Url(as: 'event')]
    public int $eventId = 0;

    /** A user id, 0 for everybody, -1 for the unassigned. */
    #[Url(as: 'owner')]
    public int $ownerId = 0;

    #[Url(as: 'q')]
    public string $q = '';

    /** Show only what is late or unassigned — the two ways work goes missing. */
    #[Url(as: 'attention')]
    public bool $attentionOnly = false;

    public function setEvent(int $id): void
    {
        $this->eventId = $id;
    }

    public function setOwner(int $id): void
    {
        $this->ownerId = $id;
    }

    public function toggleAttention(): void
    {
        $this->attentionOnly = ! $this->attentionOnly;
    }

    /**
     * Move a deliverable to another gate.
     *
     * The board writes the gate and nothing else. Approval carries a signature
     * and a date, and neither belongs to a drag — that stays in the studio,
     * where the person approving can see what they are approving.
     */
    public function moveTo(int $itemId, string $status): void
    {
        Gate::authorize('manage-events');

        if (! array_key_exists($status, PlanItem::STATUSES)) {
            return;
        }

        $item = PlanItem::findOrFail($itemId);

        // Approving is a decision with a signature on it, taken in the studio.
        if (in_array($status, PlanItem::SIGNED, true) && ! $item->isSigned()) {
            $item->update(['status' => $status, 'approved_by' => auth()->id(), 'approved_at' => now()]);

            return;
        }

        $item->update(['status' => $status]);
    }

    private function items(): Collection
    {
        return PlanItem::query()
            ->with(['event', 'track', 'owners', 'subtasks'])
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->when($this->eventId > 0, fn ($q) => $q->where('event_id', $this->eventId))
            ->get()
            ->filter(function (PlanItem $i) {
                if ($this->ownerId > 0 && ! $i->owners->contains('id', $this->ownerId)) {
                    return false;
                }
                if ($this->ownerId === -1 && $i->owners->isNotEmpty()) {
                    return false;
                }
                if ($this->attentionOnly && ! ($i->isOverdue() || ($i->owners->isEmpty() && $i->isOpen()))) {
                    return false;
                }
                if ($this->q === '') {
                    return true;
                }

                $hay = mb_strtolower(implode(' ', array_filter([
                    $i->title, $i->description, $i->event?->name, $i->track?->name,
                ])));

                return str_contains($hay, mb_strtolower(trim($this->q)));
            })
            ->values();
    }

    public function render()
    {
        $items = $this->items();

        // The board's order inside a lane: late first, then by date, then the
        // undated. What is late is what you are looking for.
        $ordered = $items->sortBy(fn (PlanItem $i) => [
            $i->isOverdue() ? 0 : 1,
            $i->due_on?->timestamp ?? PHP_INT_MAX,
        ])->values();

        $lanes = collect(PlanItem::STATUSES)
            ->map(fn (array $meta, string $key) => [
                'key' => $key,
                'label' => $meta[0],
                'hex' => $meta[1],
                'items' => $ordered->where('status', $key)->values(),
            ])
            // Cancelled is drawn only when something is cancelled: an empty
            // lane is a column you have to read to dismiss.
            ->reject(fn (array $l) => $l['key'] === 'cancelled' && $l['items']->isEmpty())
            ->values();

        // Counted across the book, not the filtered view.
        $all = PlanItem::with(['owners', 'event'])
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))->get();

        $open = $all->filter->isOpen();
        $overdue = $all->filter->isOverdue();
        $waiting = $all->where('status', 'needs_approval');
        $thisWeek = $open->filter(fn (PlanItem $i) => $i->due_on
            && $i->due_on->betweenIncluded(now()->startOfDay(), now()->addDays(7)->endOfDay()));
        $unowned = $open->filter(fn (PlanItem $i) => $i->owners->isEmpty());

        return view('livewire.planning-board', [
            'lanes' => $lanes,
            'items' => $items,
            'events' => Event::whereNull('archived_at')
                ->whereHas('planItems')->orderBy('name')->get(['id', 'name']),
            'people' => User::whereHas('planItems')->orderBy('name')->get(['id', 'name']),
            'figures' => [
                ['label' => 'Overdue', 'value' => (string) $overdue->count(),
                    'icon' => 'bell', 'tone' => $overdue->isEmpty() ? 'green' : 'red',
                    'note' => 'Past their due date'],
                ['label' => 'Next 7 days', 'value' => (string) $thisWeek->count(),
                    'icon' => 'calendar', 'tone' => 'gold', 'note' => 'Due within the week'],
                ['label' => 'Need approval', 'value' => (string) $waiting->count(),
                    'icon' => 'check', 'tone' => $waiting->isEmpty() ? 'green' : 'violet',
                    'note' => 'Waiting on a person'],
                ['label' => 'Unassigned', 'value' => (string) $unowned->count(),
                    'icon' => 'users', 'tone' => $unowned->isEmpty() ? 'green' : 'gold',
                    'note' => 'Open, with nobody on it'],
                ['label' => 'Open', 'value' => (string) $open->count(),
                    'icon' => 'grid', 'tone' => 'navy',
                    'note' => $all->count().' across the book'],
            ],
        ]);
    }
}
