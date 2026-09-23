<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\Task;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * My Work — what is on *my* plate, across every event.
 *
 * /tasks answers "what is the team doing"; the dashboard answers "how is the
 * portfolio". Neither answered the question a coordinator opens the app with:
 * what do I have to do today. This page is that answer, and it lets the
 * simple moves (start it, finish it) happen right here instead of three
 * clicks deep in each event's board.
 *
 * Approvals are listed, not decided, here: the decision carries a chain,
 * delegation and notifications that live in ApprovalsTab::decide(), and a
 * second copy of that logic is exactly how two screens start disagreeing.
 */
#[Layout('components.layouts.app', ['title' => 'My work', 'hideTitleRow' => true])]
class MyWork extends Component
{
    /** The only moves this page offers — the rest stay on the board. */
    private const MOVES = ['doing', 'done'];

    public function move(int $taskId, string $status): void
    {
        Gate::authorize('write');
        abort_unless(in_array($status, self::MOVES, true), 422);

        // Your own tasks only: this is your list, not a back door onto
        // everybody else's.
        $task = Task::whereKey($taskId)->where('assignee_id', auth()->id())->firstOrFail();

        // A task waiting on a sign-off is not yours to close from here.
        abort_if($task->status === 'review', 422);

        $task->update(['status' => $status]);

        $this->dispatch('my-work-toast', message: $status === 'done'
            ? "“{$task->title}” done."
            : "“{$task->title}” moved to In Progress.");
    }

    /**
     * My open tasks, bucketed by when they are due.
     *
     * @return array<string,Collection<int,Task>>
     */
    private function tasks(): array
    {
        $today = Carbon::today();
        $open = collect(Task::STAGES)->filter(fn ($stage) => $stage[2])->keys()->all();

        $tasks = Task::with('event')
            ->where('assignee_id', auth()->id())
            ->whereIn('status', $open)
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->get();

        return [
            'Overdue' => $tasks->filter(fn (Task $t) => $t->due_on?->lt($today)),
            'Today' => $tasks->filter(fn (Task $t) => $t->due_on?->isSameDay($today)),
            'Next 7 days' => $tasks->filter(fn (Task $t) => $t->due_on?->gt($today) && $t->due_on->lte($today->copy()->addDays(7))),
            'Later' => $tasks->filter(fn (Task $t) => $t->due_on?->gt($today->copy()->addDays(7))),
            'No due date' => $tasks->filter(fn (Task $t) => $t->due_on === null),
        ];
    }

    /**
     * Pending approvals whose current step I can decide — by the same rules
     * ApprovalsTab::decide() enforces, so nothing listed here refuses you
     * when you get there.
     */
    private function waitingOnMe(): Collection
    {
        $me = auth()->user();

        if (! Gate::allows('decide-approvals')) {
            return collect();
        }

        return EventApproval::with(['event', 'requester', 'steps'])
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('requested_by')->orWhere('requested_by', '!=', $me->id))
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->oldest()
            ->get()
            ->filter(fn (EventApproval $a) => $a->currentStep()?->decidableBy($me))
            ->values();
    }

    /** Requests I raised that are still with somebody else. */
    private function waitingOnOthers(): Collection
    {
        return EventApproval::with(['event', 'steps.approver'])
            ->where('status', 'pending')
            ->where('requested_by', auth()->id())
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->oldest()
            ->get();
    }

    /** Events I run or am on the team of — and am allowed to open. */
    private function myEvents(): Collection
    {
        $me = auth()->user();

        return Event::whereNull('archived_at')
            ->where(fn ($q) => $q->where('project_manager_id', $me->id)
                ->orWhereHas('teamMembers', fn ($t) => $t->whereKey($me->id)))
            ->withCount(['tasks as my_open_tasks' => fn ($q) => $q
                ->where('assignee_id', $me->id)
                ->whereNotIn('status', ['done', 'cancelled'])])
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Event $e) => $me->can('view', $e))
            ->values();
    }

    public function render()
    {
        $buckets = $this->tasks();

        return view('livewire.my-work', [
            'buckets' => $buckets,
            'openCount' => collect($buckets)->sum(fn ($b) => $b->count()),
            'overdueCount' => $buckets['Overdue']->count(),
            'waitingOnMe' => $this->waitingOnMe(),
            'waitingOnOthers' => $this->waitingOnOthers(),
            'events' => $this->myEvents(),
        ]);
    }
}
