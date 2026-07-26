<?php

namespace App\Support;

use App\Models\Event;

/**
 * One set of real figures, shared by all three bake-off prototypes, so the
 * comparison is about the design and nothing else.
 */
class BakeoffData
{
    public static function get(): array
    {
        $event = Event::query()->withCount('agendaSessions')
            ->orderByDesc('agenda_sessions_count')->firstOrFail();

        $budget = (int) $event->budget_cents;
        $spent = (int) $event->budgetItems()->sum('actual_cents');
        $tasks = $event->tasks;
        $open = $tasks->filter->isOpen();

        return [
            'event' => $event,
            'daysOut' => (int) round(now()->startOfDay()->diffInDays($event->starts_at->copy()->startOfDay(), false)),
            'attendees' => $event->attendees()->count(),
            'confirmed' => $event->attendees()->where('status', 'confirmed')->count(),
            'pending' => $event->attendees()->whereIn('status', ['pending', 'registered'])->count(),
            'waitlist' => $event->attendees()->where('status', 'waitlist')->count(),
            'suppliers' => $event->suppliers()->count(),
            'supplierIssues' => $event->suppliers()->wherePivot('status', 'issue')->count(),
            'speakers' => $event->speakers()->count(),
            'speakersConfirmed' => $event->speakers()->where('status', 'confirmed')->count(),
            'sponsorship' => (int) $event->sponsors()->sum('amount_cents'),
            'sponsorsPaid' => (int) $event->sponsors()->sum('paid_cents'),
            'sponsors' => $event->sponsors()->orderByDesc('amount_cents')->get(),
            'sessions' => $event->agendaSessions()->count(),
            'budget' => $budget,
            'spent' => $spent,
            'spentPct' => $budget ? (int) round($spent / $budget * 100) : 0,
            'tasksOpen' => $open->count(),
            'tasksDone' => $tasks->count() - $open->count(),
            'overdue' => $open->filter(fn ($t) => $t->due_on?->isPast())->count(),
            'topTasks' => $open->sortBy('due_on')->take(5),
            'issues' => $event->suppliers()->wherePivot('status', 'issue')->get(),
        ];
    }
}
