<?php

namespace App\Livewire;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\TaskNotifier;
use App\Support\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every task across events and projects — and now a place to work them.
 *
 * It was a read-only report: to change a status or an owner you had to
 * open each task's event and find it on that board. Filters answer "whose,
 * which event, when"; the row changes status and owner in place; a selection
 * does the same to many rows at once. Moves are the same plain status update
 * the event board makes (TasksTab::moveTask), behind the same `write` gate.
 */
#[Layout('components.layouts.app', ['title' => 'Tasks', 'hideTitleRow' => true])]
class TasksIndex extends Component
{
    use BulkSelectable;
    use WithPagination;

    #[Url]
    public string $status = '';

    /** '' anyone · 'me' · 'unassigned' · a user id */
    #[Url]
    public string $who = '';

    #[Url]
    public string $event = '';

    /** '' any time · 'overdue' · 'week' (next 7 days) · 'none' (no date) */
    #[Url]
    public string $due = '';

    #[Url]
    public string $q = '';

    public function updated(string $property): void
    {
        if (in_array($property, ['status', 'who', 'event', 'due', 'q'], true)) {
            $this->resetPage();
            $this->clearSelection();
        }
    }

    public function clearFilters(): void
    {
        $this->reset('status', 'who', 'event', 'due', 'q');
        $this->resetPage();
        $this->clearSelection();
    }

    /* ── One row ── */

    public function setStatus(int $taskId, string $status): void
    {
        Gate::authorize('write');
        abort_unless(in_array($status, Task::statuses(), true), 422);

        Task::findOrFail($taskId)->update(['status' => $status]);
    }

    public function assign(int $taskId, string $userId): void
    {
        Gate::authorize('write');

        $task = Task::findOrFail($taskId);
        $to = $this->assigneeId($userId);

        if ($task->assignee_id === $to) {
            return;
        }

        $task->update(['assignee_id' => $to]);
        app(TaskNotifier::class)->assigned($task, $task->assignee, auth()->user());
    }

    /* ── The selection ── */

    public function bulkStatus(string $status): void
    {
        Gate::authorize('write');
        abort_unless(in_array($status, Task::statuses(), true), 422);

        $n = Task::whereKey($this->selectedIds())->update(['status' => $status]);
        $this->finishBulk($n, 'moved to '.Workflow::label('task_stage', $status));
    }

    public function bulkAssign(string $userId): void
    {
        Gate::authorize('write');

        $assignee = $this->assigneeId($userId);

        // Only the rows that actually change hands are news to anybody.
        $handedOver = Task::whereKey($this->selectedIds())
            ->where(fn ($q) => $q->whereNull('assignee_id')->orWhere('assignee_id', '!=', $assignee))
            ->get();

        $n = Task::whereKey($this->selectedIds())->update(['assignee_id' => $assignee]);

        $to = $assignee ? User::find($assignee) : null;
        app(TaskNotifier::class)->assigned($handedOver, $to, auth()->user());

        $this->finishBulk($n, $to ? 'assigned to '.$to->name : 'unassigned');
    }

    private function finishBulk(int $n, string $what): void
    {
        $this->clearSelection();
        $this->dispatch('tasks-toast', message: $n.' '.str('task')->plural($n).' '.$what.'.');
    }

    /** '' means nobody; anything else must be a real user. */
    private function assigneeId(string $userId): ?int
    {
        if ($userId === '') {
            return null;
        }

        return User::findOrFail((int) $userId)->id;
    }

    /* ── Reading ── */

    /** Every filter except status — the workflow strip counts across stages. */
    private function scoped(): Builder
    {
        $today = Carbon::today();

        return Task::query()
            ->when($this->who === 'me', fn ($q) => $q->where('assignee_id', auth()->id()))
            ->when($this->who === 'unassigned', fn ($q) => $q->whereNull('assignee_id'))
            ->when(ctype_digit($this->who), fn ($q) => $q->where('assignee_id', (int) $this->who))
            ->when(ctype_digit($this->event), fn ($q) => $q->where('event_id', (int) $this->event))
            ->when($this->due === 'overdue', fn ($q) => $q->whereDate('due_on', '<', $today)->whereNotIn('status', ['done', 'cancelled']))
            ->when($this->due === 'week', fn ($q) => $q->whereDate('due_on', '>=', $today)->whereDate('due_on', '<=', $today->copy()->addDays(7)))
            ->when($this->due === 'none', fn ($q) => $q->whereNull('due_on'))
            ->when(trim($this->q) !== '', fn ($q) => $q->where('title', 'like', '%'.trim($this->q).'%'));
    }

    public function render()
    {
        $counts = (clone $this->scoped())->selectRaw('status, count(*) as c')->groupBy('status')->pluck('c', 'status')
            // Keyed by the model's own stages, so a new status can never vanish here.
            ->pipe(fn ($by) => collect(Task::statuses())->mapWithKeys(fn ($s) => [$s => (int) ($by[$s] ?? 0)]));

        $tasks = $this->scoped()->with(['event', 'assignee'])
            ->when(in_array($this->status, Task::statuses(), true), fn ($q) => $q->where('status', $this->status))
            ->orderByRaw("status = 'done'")->orderBy('due_on')
            ->paginate(25);

        return view('livewire.tasks-index', [
            'tasks' => $tasks,
            'counts' => $counts,
            'statusFilter' => in_array($this->status, Task::statuses(), true) ? $this->status : null,
            'people' => User::orderBy('name')->get(['id', 'name']),
            'events' => Event::whereNull('archived_at')->orderBy('starts_at')->get(['id', 'name']),
            'filtered' => $this->who !== '' || $this->event !== '' || $this->due !== '' || trim($this->q) !== '' || $this->status !== '',
        ]);
    }
}
