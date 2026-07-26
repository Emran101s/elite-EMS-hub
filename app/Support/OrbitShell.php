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

    /** The portfolio dock — the same nine-slot shape, one level up. */
    public static function portfolioDock(): array
    {
        return [
            ['key' => 'home', 'label' => 'Home', 'icon' => 'home', 'href' => route('home')],
            ['key' => 'events', 'label' => 'Events', 'icon' => 'grid', 'href' => route('events.index')],
            ['key' => 'projects', 'label' => 'Projects', 'icon' => 'note', 'href' => url('/projects')],
            ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'task', 'href' => url('/tasks')],
            ['key' => 'finance', 'label' => 'Finance', 'icon' => 'money', 'href' => url('/finance')],
            ['key' => 'sponsors', 'label' => 'Sponsors', 'icon' => 'star', 'href' => url('/sponsors')],
            ['key' => 'team', 'label' => 'Team', 'icon' => 'users', 'href' => url('/team')],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart', 'href' => url('/reports')],
            ['key' => 'ai', 'label' => 'AI', 'icon' => 'spark', 'href' => url('/ai')],
        ];
    }

    /**
     * The portfolio ribbon. Same shape as the per-event one, one level up: what
     * an owner needs to know about every live event at a glance.
     */
    public static function portfolioKpis(): array
    {
        $events = Event::query()->whereNull('archived_at');
        $total = (clone $events)->count();
        $active = (clone $events)->whereIn('stage', ['planning', 'production', 'live', 'confirmed'])->count();

        $openTasks = \App\Models\Task::query()->whereNotIn('status', ['done', 'cancelled'])->count();
        $overdue = \App\Models\Task::query()->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_on')->whereDate('due_on', '<', now())->count();

        $budget = (int) (clone $events)->sum('budget_cents');

        $atRisk = (clone $events)->get()
            ->filter(fn (Event $e) => $e->healthGroup() === 'risk')
            ->count();

        return [
            ['k' => 'Events', 'v' => (string) $total, 'f' => $active.' active'],
            ['k' => 'At risk', 'v' => (string) $atRisk, 'f' => $atRisk > 0 ? 'need attention' : 'all healthy', 'tone' => $atRisk > 0 ? 'critical' : 'vital'],
            ['k' => 'Open tasks', 'v' => (string) $openTasks, 'f' => $overdue > 0 ? $overdue.' overdue' : 'none overdue', 'tone' => $overdue > 0 ? 'flare' : null],
            ['k' => 'Portfolio budget', 'v' => 'JD '.number_format($budget / 100), 'f' => 'committed'],
        ];
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
