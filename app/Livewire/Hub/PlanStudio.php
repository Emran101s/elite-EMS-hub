<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\PlanItem;
use App\Models\PlanSubtask;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;

class PlanStudio extends Component
{
    public Event $event;

    /** board | timeline | list | gallery */
    public string $view = 'board';

    // ── filters ──
    public string $search = '';

    public ?int $filterTrack = null;

    public ?int $filterOwner = null;

    // ── track palette for new tracks ──
    private const PALETTE = ['#8B5CF6', '#3B82F6', '#06B6D4', '#EC4899', '#D4AF37', '#10B981', '#F59E0B', '#0EA5E9'];

    // ── drawer / item form ──
    public ?int $selectedId = null;

    public string $title = '';

    public string $description = '';

    public string $priority = 'medium';

    public ?int $formTrackId = null;

    public string $start_on = '';

    public string $due_on = '';

    public ?int $progress_override = null;

    public array $owner_ids = [];

    public string $newSubtask = '';

    /** Fields the drawer autosaves as you edit. */
    private const FORM_FIELDS = ['title', 'description', 'priority', 'formTrackId', 'start_on', 'due_on', 'progress_override'];

    // ── track management ──
    public bool $showTracks = false;

    public string $newTrackName = '';

    public ?int $editingTrackId = null;

    public string $trackEditName = '';

    public function mount(): void
    {
        $this->event->ensurePlanTracks();
    }

    // ── Views ─────────────────────────────────────────────────
    public function setView(string $view): void
    {
        if (in_array($view, ['board', 'timeline', 'list', 'gallery'], true)) {
            $this->view = $view;
        }
    }

    // ── Items ─────────────────────────────────────────────────
    public function addItem(?int $trackId = null, string $status = 'todo'): void
    {
        Gate::authorize('write');
        $item = $this->event->planItems()->create([
            'track_id' => $trackId ?: $this->event->planTracks()->value('id'),
            'title' => 'Untitled item',
            'status' => array_key_exists($status, PlanItem::STATUSES) ? $status : 'todo',
            'priority' => 'medium',
            'position' => (int) $this->event->planItems()->max('position') + 1,
        ]);
        $this->openItem($item->id);
    }

    public function openItem(int $id): void
    {
        $item = $this->event->planItems()->with('owners')->findOrFail($id);
        $this->selectedId = $item->id;
        $this->title = $item->title;
        $this->description = $item->description ?? '';
        $this->priority = array_key_exists($item->priority, PlanItem::PRIORITIES) ? $item->priority : 'medium';
        $this->formTrackId = $item->track_id;
        $this->start_on = $item->start_on?->format('Y-m-d') ?? '';
        $this->due_on = $item->due_on?->format('Y-m-d') ?? '';
        $this->progress_override = $item->progress_override;
        $this->owner_ids = $item->owners->pluck('id')->all();
        $this->newSubtask = '';
        $this->resetValidation();
    }

    public function closeDrawer(): void
    {
        $this->reset(['selectedId', 'title', 'description', 'start_on', 'due_on', 'owner_ids', 'progress_override', 'newSubtask']);
    }

    public function updated(string $name): void
    {
        if (! $this->selectedId || ! in_array($name, self::FORM_FIELDS, true)) {
            return;
        }

        $this->validate([
            'title' => ['required', 'string', 'max:200'],
            'priority' => ['required', Rule::in(array_keys(PlanItem::PRIORITIES))],
            'start_on' => ['nullable', 'date'],
            'due_on' => ['nullable', 'date', 'after_or_equal:start_on'],
            'progress_override' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);

        $this->event->planItems()->whereKey($this->selectedId)->update([
            'title' => $this->title,
            'description' => $this->description ?: null,
            'priority' => $this->priority,
            'track_id' => $this->formTrackId,
            'start_on' => $this->start_on ?: null,
            'due_on' => $this->due_on ?: null,
            'progress_override' => $this->progress_override === '' ? null : $this->progress_override,
        ]);
    }

    public function deleteItem(int $id): void
    {
        Gate::authorize('write');
        $this->event->planItems()->whereKey($id)->delete();
        if ($this->selectedId === $id) {
            $this->closeDrawer();
        }
    }

    /** Move an item to a gate — stamps or clears the approval seal. */
    public function setStatus(int $id, string $status): void
    {
        Gate::authorize('write');
        if (! array_key_exists($status, PlanItem::STATUSES)) {
            return;
        }
        $item = $this->event->planItems()->findOrFail($id);
        $item->status = $status;

        if (in_array($status, PlanItem::SIGNED, true)) {
            if (! $item->approved_at) {
                $item->approved_by = auth()->id();
                $item->approved_at = now();
            }
        } else {
            $item->approved_by = null;
            $item->approved_at = null;
        }
        $item->save();
    }

    /** Board drag: land an item in a column, in a given order. */
    public function moveToStatus(int $id, string $status, array $orderedIds = []): void
    {
        $this->setStatus($id, $status);
        foreach (array_values($orderedIds) as $pos => $itemId) {
            $this->event->planItems()->whereKey((int) $itemId)->update(['position' => $pos]);
        }
    }

    public function toggleOwner(int $userId): void
    {
        Gate::authorize('write');
        if (($k = array_search($userId, $this->owner_ids, true)) !== false) {
            unset($this->owner_ids[$k]);
            $this->owner_ids = array_values($this->owner_ids);
        } else {
            $this->owner_ids[] = $userId;
        }
        if ($this->selectedId) {
            $ids = User::whereIn('id', $this->owner_ids)->pluck('id')->all();
            $this->event->planItems()->findOrFail($this->selectedId)->owners()->sync($ids);
        }
    }

    // ── Subtasks ──────────────────────────────────────────────
    public function addSubtask(int $itemId): void
    {
        Gate::authorize('write');
        $title = trim($this->newSubtask);
        if ($title === '') {
            return;
        }
        $item = $this->event->planItems()->findOrFail($itemId);
        $item->subtasks()->create([
            'title' => $title,
            'position' => (int) $item->subtasks()->max('position') + 1,
        ]);
        $this->newSubtask = '';
    }

    public function toggleSubtask(int $subtaskId): void
    {
        Gate::authorize('write');
        $sub = $this->subtaskForEvent($subtaskId);
        $sub?->update(['is_done' => ! $sub->is_done]);
    }

    public function updateSubtask(int $subtaskId, string $field, $value): void
    {
        Gate::authorize('write');
        $sub = $this->subtaskForEvent($subtaskId);
        if (! $sub) {
            return;
        }
        match ($field) {
            'title' => $sub->update(['title' => trim((string) $value) ?: $sub->title]),
            'due_on' => $sub->update(['due_on' => $value ?: null]),
            'owner_id' => $sub->update(['owner_id' => $value ? (int) $value : null]),
            default => null,
        };
    }

    public function deleteSubtask(int $subtaskId): void
    {
        Gate::authorize('write');
        $this->subtaskForEvent($subtaskId)?->delete();
    }

    /** Guard: only subtasks belonging to this event's items are editable. */
    private function subtaskForEvent(int $subtaskId): ?PlanSubtask
    {
        return PlanSubtask::whereKey($subtaskId)
            ->whereHas('item', fn ($q) => $q->where('event_id', $this->event->id))
            ->first();
    }

    // ── Tracks ────────────────────────────────────────────────
    public function addTrack(): void
    {
        Gate::authorize('write');
        $name = trim($this->newTrackName);
        if ($name === '') {
            return;
        }
        $pos = (int) $this->event->planTracks()->max('position') + 1;
        $this->event->planTracks()->create([
            'name' => $name,
            'color' => self::PALETTE[$pos % count(self::PALETTE)],
            'position' => $pos,
        ]);
        $this->newTrackName = '';
    }

    public function startRenameTrack(int $id): void
    {
        $track = $this->event->planTracks()->findOrFail($id);
        $this->editingTrackId = $track->id;
        $this->trackEditName = $track->name;
    }

    public function saveTrackName(): void
    {
        if ($this->editingTrackId) {
            $name = trim($this->trackEditName);
            if ($name !== '') {
                $this->event->planTracks()->whereKey($this->editingTrackId)->update(['name' => $name]);
            }
        }
        $this->reset(['editingTrackId', 'trackEditName']);
    }

    public function deleteTrack(int $id): void
    {
        Gate::authorize('write');
        $track = $this->event->planTracks()->findOrFail($id);
        $target = $this->event->planTracks()->where('id', '!=', $id)->orderBy('position')->value('id');
        $this->event->planItems()->where('track_id', $id)->update(['track_id' => $target]);
        $track->delete();
    }

    public function reorderTracks(array $ids): void
    {
        foreach (array_values($ids) as $pos => $id) {
            $this->event->planTracks()->whereKey((int) $id)->update(['position' => $pos]);
        }
    }

    /** Inline-edit a track's name, goal, or colour from the Tracks manager. */
    public function updateTrack(int $id, string $field, $value): void
    {
        Gate::authorize('write');
        $track = $this->event->planTracks()->findOrFail($id);
        match ($field) {
            'name' => $track->update(['name' => trim((string) $value) ?: $track->name]),
            'goal' => $track->update(['goal' => trim((string) $value) ?: null]),
            'color' => $track->update(['color' => (string) $value ?: $track->color]),
            default => null,
        };
    }

    // ── Filters ───────────────────────────────────────────────
    public function filterByTrack(?int $id = null): void
    {
        $this->filterTrack = ($id && $this->filterTrack !== $id) ? $id : null;
    }

    public function filterByOwner(?int $id = null): void
    {
        $this->filterOwner = ($id && $this->filterOwner !== $id) ? $id : null;
    }

    // ── Render ────────────────────────────────────────────────
    public function render()
    {
        $tracks = $this->event->planTracks()->get();
        $all = $this->event->planItems()->with(['owners', 'subtasks', 'track', 'approver'])->get();

        $items = $all->filter(function (PlanItem $i) {
            if ($this->filterTrack && $i->track_id !== $this->filterTrack) {
                return false;
            }
            if ($this->filterOwner && ! $i->owners->contains('id', $this->filterOwner)) {
                return false;
            }
            if ($this->search !== '' && ! str_contains(mb_strtolower($i->title), mb_strtolower($this->search))) {
                return false;
            }

            return true;
        })->values();

        // Gate rail counts (whole plan, not filtered — the pipeline view is global).
        $gateCounts = collect(PlanItem::statuses())->mapWithKeys(fn ($s) => [$s => $all->where('status', $s)->count()]);

        $stats = [
            'total' => $all->count(),
            'done' => $all->whereIn('status', ['done'])->count(),
            'signed' => $all->filter(fn ($i) => $i->isSigned())->count(),
            'overdue' => $all->filter(fn ($i) => $i->isOverdue())->count(),
            'needApproval' => $all->where('status', 'needs_approval')->count(),
            'progress' => $all->count() ? (int) round($all->avg(fn ($i) => $i->progress())) : 0,
        ];

        return view('livewire.hub.plan-studio', [
            'tracks' => $tracks,
            'items' => $items,
            'byStatus' => collect(PlanItem::statuses())->mapWithKeys(fn ($s) => [$s => $items->where('status', $s)->sortBy('position')->values()]),
            'byTrack' => $tracks->mapWithKeys(fn ($t) => [$t->id => $items->where('track_id', $t->id)->values()]),
            'untracked' => $items->whereNull('track_id')->values(),
            'gateCounts' => $gateCounts,
            'stats' => $stats,
            'axis' => $this->axis($items),
            'geo' => $this->geoMap($items),
            'users' => User::orderBy('name')->get(),
            'selected' => $this->selectedId ? $all->firstWhere('id', $this->selectedId) : null,
        ]);
    }

    /** Date axis for the timeline — month ticks, span, today marker. */
    private function axis($items): array
    {
        $today = Carbon::today();
        $marks = collect([$today]);
        foreach ($items as $i) {
            if ($i->start_on) {
                $marks->push($i->start_on);
            }
            if ($i->due_on) {
                $marks->push($i->due_on);
            }
        }
        if ($this->event->starts_at) {
            $marks->push($this->event->starts_at);
        }

        $min = $marks->min()->copy()->startOfMonth();
        $max = $marks->max()->copy()->endOfMonth();
        if ($min->diffInDays($max) < 60) {
            $max = $min->copy()->addMonths(4)->endOfMonth();
        }

        $minTs = $min->timestamp;
        $span = max($max->timestamp - $minTs, 86400 * 30);
        $pos = fn ($d) => round(($d->copy()->startOfDay()->timestamp - $minTs) / $span * 100, 3);

        $ticks = [];
        for ($m = $min->copy(); $m <= $max; $m->addMonth()) {
            $ticks[] = ['label' => $m->format('M'), 'sub' => $m->format('Y'), 'left' => $pos($m)];
        }

        return [
            'ticks' => $ticks,
            'minTs' => $minTs,
            'spanDays' => (int) round($span / 86400),
            'todayLeft' => $pos($today),
            'todayIn' => $today->between($min, $max),
        ];
    }

    /** itemId => [left%, width%] bar geometry. */
    private function geoMap($items): array
    {
        $axis = $this->axis($items);
        $span = max(1, $axis['spanDays']);
        $map = [];
        foreach ($items as $i) {
            if (! $i->start_on && ! $i->due_on) {
                continue;
            }
            $s = ($i->start_on ?? $i->due_on)->copy()->startOfDay();
            $e = ($i->due_on ?? $i->start_on)->copy()->startOfDay();
            $left = max(0, min(99, ($s->timestamp - $axis['minTs']) / 86400 / $span * 100));
            $width = max(1.5, ($e->timestamp - $s->timestamp) / 86400 / $span * 100);
            $map[$i->id] = ['left' => round($left, 2), 'width' => round($width, 2)];
        }

        return $map;
    }
}
