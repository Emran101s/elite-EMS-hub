<?php

namespace App\Services;

use App\Models\Task;
use App\Models\User;
use App\Notifications\TaskDigest;
use App\Notifications\TasksAssigned;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Who hears about tasks, and when.
 *
 * Every screen that hands a task to someone calls assigned(); the daily
 * digest calls digest(). The rules live here once: never mail you about what
 * you did to yourself, never mail someone with no address, never send an
 * empty digest.
 */
class TaskNotifier
{
    /**
     * $tasks were just given to $to by $by. Silent when $to is nobody, has
     * no email, or is the person doing the assigning.
     *
     * @param  Task|Collection<int,Task>  $tasks
     */
    public function assigned(Task|Collection $tasks, ?User $to, ?User $by): void
    {
        $tasks = $tasks instanceof Task ? new EloquentCollection([$tasks]) : EloquentCollection::make($tasks->all());

        if (! $to || blank($to->email) || $tasks->isEmpty() || $to->id === $by?->id) {
            return;
        }

        $to->notify(new TasksAssigned($tasks->loadMissing('event'), $by?->name));
    }

    /**
     * Send each person with overdue, today's or tomorrow's open tasks one
     * digest. Returns how many people were mailed.
     */
    public function digest(?Carbon $today = null, bool $dryRun = false, ?callable $report = null): int
    {
        // "Today" is the office's calendar date (between midnight and 03:00 in
        // Amman, UTC is still on yesterday), taken as a plain date in the
        // app's timezone so it compares cleanly with the due_on date column.
        $today = Carbon::parse(($today ?? Carbon::now(config('app.office_timezone')))->toDateString());
        $tomorrow = $today->copy()->addDay();

        $tasks = Task::with('event')
            ->whereNotNull('assignee_id')
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '<=', $tomorrow->toDateString())
            ->orderBy('due_on')
            ->get()
            ->groupBy('assignee_id');

        $sent = 0;

        foreach (User::whereKey($tasks->keys())->get() as $user) {
            if (blank($user->email)) {
                continue;
            }

            $mine = $tasks[$user->id];
            $buckets = [
                'overdue' => $mine->filter(fn (Task $t) => $t->due_on->lt($today))->values(),
                'today' => $mine->filter(fn (Task $t) => $t->due_on->isSameDay($today))->values(),
                'tomorrow' => $mine->filter(fn (Task $t) => $t->due_on->isSameDay($tomorrow))->values(),
            ];

            if ($report) {
                $report($user, $buckets);
            }

            if (! $dryRun) {
                $user->notify(new TaskDigest($buckets));
            }

            $sent++;
        }

        return $sent;
    }
}
