<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * A brand-new, self-contained task board. Six stages, drag-and-drop, inline
 * quick-add, and a live detail panel. Every action updates in place — no
 * full-page reloads — and the board talks back with toasts.
 */
class TasksTab extends Component
{
    use BulkSelectable;

    public Event $event;

    /** board | timeline | list | gallery */
    public string $view = 'board';

    // ── Focus filters ──
    public string $filterArea = '';   // module slug

    public ?int $filterTrack = null;  // planning track link

    public ?int $filterAssignee = null;

    public string $focus = 'all';   // all | mine | overdue

    public string $search = '';

    // ── Detail panel ──
    public ?int $openId = null;

    public string $checkText = '';

    // ── Edit form (in the panel) ──
    public string $title = '';

    public string $description = '';

    public string $priority = 'normal';

    public string $area = '';

    public ?int $track_id = null;

    public ?int $assignee_id = null;

    public string $due_on = '';

    public string $start_on = '';

    public function mount(): void
    {
        $this->filterAssignee = request('mine') ? auth()->id() : null;
        // Quick Actions → "＋ Add Task" lands here with ?action=add.
        if (request('action') === 'add' && Gate::allows('write')) {
            $this->addTask();
        }
    }

    public function setView(string $view): void
    {
        if (in_array($view, ['board', 'timeline', 'list', 'gallery'], true)) {
            $this->view = $view;
        }
    }

    /** Create a task (respecting the active filters) and open it for detailing. */
    public function addTask(?string $module = null, ?int $trackId = null, string $status = 'todo'): void
    {
        Gate::authorize('write');
        $task = $this->event->tasks()->create([
            'title' => 'Untitled task',
            'status' => array_key_exists($status, Task::STAGES) ? $status : 'todo',
            'priority' => 'normal',
            'area' => $module ?: ($this->filterArea ?: null),
            'track_id' => $trackId ?: $this->filterTrack,
            'assignee_id' => $this->focus === 'mine' ? auth()->id() : null,
        ]);
        $this->openTask($task->id);
    }

    /** Board drag: land a task in a stage and re-order the column. */
    public function moveToStatus(int $taskId, string $status, array $orderedIds = []): void
    {
        $this->moveTask($taskId, $status);
        foreach (array_values($orderedIds) as $pos => $id) {
            $this->event->tasks()->whereKey((int) $id)->update(['sort' => $pos]);
        }
    }

    /* ── Toast ── */
    private function toast(string $message, string $tone = 'ok'): void
    {
        $this->dispatch('task-toast', message: $message, tone: $tone);
    }

    /* ── Create ── */
    public function quickAdd(string $status, string $title): void
    {
        Gate::authorize('write');
        $title = trim($title);
        if ($title === '' || ! in_array($status, Task::statuses(), true)) {
            return;
        }

        $task = $this->event->tasks()->create([
            'title' => $title,
            'status' => $status,
            'priority' => 'normal',
            'area' => $this->filterArea ?: null,
            'assignee_id' => $this->focus === 'mine' ? auth()->id() : null,
        ]);
        $this->toast('Task added.');
        $this->openTask($task->id);
    }

    /* ── Move (drag or button) ── */
    public function moveTask(int $taskId, string $status): void
    {
        Gate::authorize('write');
        if (! in_array($status, Task::statuses(), true)) {
            return;
        }
        $this->event->tasks()->whereKey($taskId)->firstOrFail()->update(['status' => $status]);
    }

    /** Advance one stage along the flow. */
    public function advance(int $taskId): void
    {
        $task = $this->event->tasks()->whereKey($taskId)->firstOrFail();
        $order = Task::statuses();
        $i = array_search($task->status, $order, true);
        // done and cancelled are terminal; everything else steps forward.
        if ($i === false || $task->status === 'done' || $task->status === 'cancelled') {
            return;
        }
        $this->moveTask($taskId, $order[$i + 1] ?? $task->status);
        $this->toast('Moved to '.Task::STAGES[$order[min($i + 1, count($order) - 1)]][0].'.');
    }

    /* ── Delete ── */
    public function deleteTask(int $taskId): void
    {
        Gate::authorize('write');
        $this->event->tasks()->whereKey($taskId)->firstOrFail()->delete();
        if ($this->openId === $taskId) {
            $this->openId = null;
        }
        $this->toast('Task deleted.');
    }

    /* ── Detail panel ── */
    public function openTask(int $taskId): void
    {
        $t = $this->event->tasks()->find($taskId);
        if (! $t) {
            return;
        }
        $this->openId = $t->id;
        $this->title = $t->title;
        $this->description = $t->description ?? '';
        $this->priority = $t->priority;
        $this->area = $t->area ?? '';
        $this->track_id = $t->track_id;
        $this->assignee_id = $t->assignee_id;
        $this->due_on = $t->due_on?->toDateString() ?? '';
        $this->start_on = $t->start_on?->toDateString() ?? '';
        $this->checkText = '';
    }

    public function closeTask(): void
    {
        $this->openId = null;
    }

    /** One patch door for the panel's inline edits. */
    public function patch(string $field, $value): void
    {
        Gate::authorize('write');
        abort_unless(in_array($field, ['title', 'description', 'priority', 'area', 'track_id', 'assignee_id', 'due_on', 'start_on'], true), 422);

        if ($field === 'priority' && ! array_key_exists($value, Task::PRIORITIES)) {
            return;
        }
        if ($field === 'area' && $value !== '' && ! array_key_exists($value, Task::MODULES)) {
            return;
        }
        if ($field === 'track_id' && $value !== '' && $value !== null && ! $this->event->planTracks()->whereKey($value)->exists()) {
            return;
        }

        $this->event->tasks()->whereKey($this->openId)->firstOrFail()
            ->update([$field => $value === '' ? null : $value]);
    }

    /* ── Checklist ── */
    public function addCheckItem(): void
    {
        Gate::authorize('write');
        $text = trim($this->checkText);
        if ($text === '' || ! $this->openId) {
            return;
        }
        $task = $this->event->tasks()->whereKey($this->openId)->firstOrFail();
        $task->update(['checklist' => [...($task->checklist ?? []), ['text' => $text, 'done' => false]]]);
        $this->checkText = '';
    }

    public function toggleCheckItem(int $index): void
    {
        Gate::authorize('write');
        $task = $this->event->tasks()->whereKey($this->openId)->firstOrFail();
        $items = $task->checklist ?? [];
        if (isset($items[$index])) {
            $items[$index]['done'] = ! $items[$index]['done'];
            $task->update(['checklist' => $items]);
        }
    }

    public function removeCheckItem(int $index): void
    {
        Gate::authorize('write');
        $task = $this->event->tasks()->whereKey($this->openId)->firstOrFail();
        $items = $task->checklist ?? [];
        unset($items[$index]);
        $task->update(['checklist' => array_values($items)]);
    }

    /* ── Filters ── */
    public function setFocus(string $focus): void
    {
        $this->focus = in_array($focus, ['all', 'mine', 'overdue'], true) ? $focus : 'all';
        $this->filterAssignee = $focus === 'mine' ? auth()->id() : null;
    }

    public function filterByArea(string $area): void
    {
        $this->filterArea = $this->filterArea === $area ? '' : $area;
    }

    public function filterByModule(string $module): void
    {
        $this->filterArea = $this->filterArea === $module ? '' : $module;
    }

    public function filterByTrack(?int $id = null): void
    {
        $this->filterTrack = ($id && $this->filterTrack !== $id) ? $id : null;
    }

    public function render()
    {
        $this->event->ensurePlanTracks();
        $tracks = $this->event->planTracks()->get();
        $all = $this->event->tasks()->with(['assignee', 'track'])->orderBy('sort')->orderByDesc('id')->get();

        $visible = $all
            ->when($this->filterArea !== '', fn ($c) => $c->where('area', $this->filterArea))
            ->when($this->filterTrack, fn ($c) => $c->where('track_id', $this->filterTrack))
            ->when($this->focus === 'mine', fn ($c) => $c->where('assignee_id', auth()->id()))
            ->when($this->focus === 'overdue', fn ($c) => $c->filter(fn ($t) => $t->isOverdue()))
            ->when($this->search !== '', fn ($c) => $c->filter(fn ($t) => str_contains(mb_strtolower($t->title), mb_strtolower($this->search))))
            ->values();

        $lanes = collect(Task::STAGES)->map(fn ($meta, $status) => [
            'status' => $status,
            'label' => $meta[0],
            'hex' => $meta[1],
            'open' => $meta[2],
            'tasks' => $visible->where('status', $status)->sortBy('sort')->values(),
        ]);

        $open = $all->filter->isOpen();
        $stats = [
            'total' => $all->count(),
            'done' => $all->where('status', 'done')->count(),
            'overdue' => $open->filter(fn ($t) => $t->isOverdue())->count(),
            'needApproval' => $all->where('status', 'review')->count(),
            'mine' => $all->where('assignee_id', auth()->id())->filter->isOpen()->count(),
            'pct' => $all->count() ? (int) round($all->avg(fn ($t) => $t->progress())) : 0,
        ];

        // Module chips: only modules that actually have tasks.
        $moduleCounts = $all->whereNotNull('area')->groupBy('area')->map->count()->sortDesc();

        return view('livewire.hub.tasks-tab', [
            'tracks' => $tracks,
            'lanes' => $lanes,
            'byStatus' => $lanes->mapWithKeys(fn ($l) => [$l['status'] => $l['tasks']]),
            'byModule' => collect(Task::MODULES)->mapWithKeys(fn ($m, $slug) => [$slug => $visible->where('area', $slug)->values()])->filter(fn ($c) => $c->isNotEmpty()),
            'unmoduled' => $visible->whereNull('area')->values(),
            'items' => $visible,
            'stats' => $stats,
            'gateCounts' => collect(Task::STAGES)->mapWithKeys(fn ($m, $s) => [$s => $all->where('status', $s)->count()]),
            'moduleCounts' => $moduleCounts,
            'users' => User::orderBy('name')->get(),
            'detail' => $this->openId ? $all->firstWhere('id', $this->openId) : null,
            'axis' => $this->axis($visible),
            'geo' => $this->geoMap($visible),
            'daysToEvent' => $this->event->starts_at
                ? (int) now()->startOfDay()->diffInDays($this->event->starts_at->copy()->startOfDay(), false)
                : null,
        ]);
    }

    /** Date axis for the timeline — month ticks, span, today marker. */
    private function axis($items): array
    {
        $today = Carbon::today();
        $marks = collect([$today]);
        foreach ($items as $t) {
            if ($t->start_on) {
                $marks->push($t->start_on);
            }
            if ($t->due_on) {
                $marks->push($t->due_on);
            }
        }
        if ($this->event->starts_at) {
            $marks->push($this->event->starts_at);
        }

        $min = $marks->min()->copy()->startOfMonth();
        $max = $marks->max()->copy()->endOfMonth();
        if ($min->diffInDays($max) < 45) {
            $max = $min->copy()->addMonths(3)->endOfMonth();
        }
        $minTs = $min->timestamp;
        $span = max($max->timestamp - $minTs, 86400 * 30);
        $pos = fn ($d) => round(($d->copy()->startOfDay()->timestamp - $minTs) / $span * 100, 3);

        $ticks = [];
        for ($m = $min->copy(); $m <= $max; $m->addMonth()) {
            $ticks[] = ['label' => $m->format('M'), 'sub' => $m->format('Y'), 'left' => $pos($m)];
        }

        return ['ticks' => $ticks, 'minTs' => $minTs, 'spanDays' => (int) round($span / 86400), 'todayLeft' => $pos($today), 'todayIn' => $today->between($min, $max)];
    }

    /** taskId => [left%, width%] — a task with only a due date renders as a short marker. */
    private function geoMap($items): array
    {
        $axis = $this->axis($items);
        $span = max(1, $axis['spanDays']);
        $map = [];
        foreach ($items as $t) {
            if (! $t->start_on && ! $t->due_on) {
                continue;
            }
            $s = ($t->start_on ?? $t->due_on)->copy()->startOfDay();
            $e = ($t->due_on ?? $t->start_on)->copy()->startOfDay();
            $left = max(0, min(99, ($s->timestamp - $axis['minTs']) / 86400 / $span * 100));
            $width = max(1.5, ($e->timestamp - $s->timestamp) / 86400 / $span * 100);
            $map[$t->id] = ['left' => round($left, 2), 'width' => round($width, 2)];
        }

        return $map;
    }
}
