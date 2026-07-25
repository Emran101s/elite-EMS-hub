<?php

namespace App\Support;

use App\Models\Event;

/**
 * Feeds the ORBIT shell's chrome — the KPI ribbon and the Event Pulse rail.
 *
 * Both are "a real-time snapshot" per docs/orbit-ia-brief.md, so every figure
 * here comes from a live query. Nothing on the ribbon is allowed to be a
 * hard-coded number.
 */
class OrbitShell
{
    /** Is the ORBIT shell switched on? */
    public static function enabled(): bool
    {
        return (bool) config('orbit.nav', false);
    }

    /** The dark ribbon: Health · Participants · Days left · Tasks · Budget · Suppliers · Approvals. */
    public static function kpis(Event $event): array
    {
        $breakdown = app(\App\Services\EventHealthService::class)->breakdown($event);
        $health = (int) round($breakdown['score'] ?? 0);

        $openTasks = $event->tasks()->whereNotIn('status', ['done', 'cancelled'])->count();
        $overdue = $event->tasks()
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_on')->whereDate('due_on', '<', now())
            ->count();

        $attendees = $event->attendees()->count();

        $daysLeft = $event->starts_at ? (int) round(now()->startOfDay()->diffInDays($event->starts_at->startOfDay(), false)) : null;

        $budget = (int) ($event->budget_cents ?? 0);
        $spent = (int) ($event->budgetItems()->sum('actual_cents') ?: 0);
        $usedPct = $budget > 0 ? (int) round($spent / $budget * 100) : 0;

        $suppliers = $event->suppliers()->count();

        $pendingApprovals = $event->approvals()->where('status', 'pending')->count();

        return array_values(array_filter([
            [
                'k' => 'Health score',
                'v' => $health.'%',
                'f' => match (true) {
                    $health >= 85 => 'Excellent',
                    $health >= 70 => 'Good',
                    $health >= 60 => 'Watch',
                    default => 'Behind',
                },
                'tone' => Tone::forHealth($health)->value,
            ],
            [
                'k' => 'Participants',
                'v' => number_format($attendees),
                'f' => 'registered',
            ],
            $daysLeft === null ? null : [
                'k' => 'Days left',
                'v' => $daysLeft >= 0 ? (string) $daysLeft : 'Done',
                'f' => $event->starts_at?->format('M j, Y'),
            ],
            [
                'k' => 'Open tasks',
                'v' => (string) $openTasks,
                'f' => $overdue > 0 ? $overdue.' overdue' : 'none overdue',
                'tone' => $overdue > 0 ? 'critical' : null,
            ],
            [
                'k' => 'Budget used',
                'v' => $usedPct.'%',
                // Spend against the whole, so 0% still tells you the size of the event.
                'f' => 'of JD '.number_format($budget / 100),
                'tone' => $usedPct > 90 ? 'critical' : ($usedPct > 75 ? 'flare' : null),
            ],
            [
                'k' => 'Suppliers',
                'v' => (string) $suppliers,
                'f' => 'engaged',
            ],
            [
                'k' => 'Approvals',
                'v' => (string) $pendingApprovals,
                'f' => $pendingApprovals > 0 ? 'pending' : 'all clear',
                'tone' => $pendingApprovals > 0 ? 'flare' : null,
            ],
        ]));
    }
}
