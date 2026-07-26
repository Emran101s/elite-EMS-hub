<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventRisk;

/**
 * Event-level data in Command Canvas shapes.
 *
 * The portfolio canvas has CommandCanvasData; this is its counterpart one level
 * down, so the event hub can wear the same chrome without either screen
 * inventing its own figures. Both read the same health engine.
 */
class EventCanvasData
{
    /** The dark ribbon for one event. */
    public static function kpis(Event $event, array $health): array
    {
        $open = $event->tasks->filter->isOpen();
        $overdue = $open->filter(fn ($t) => $t->due_on?->isPast())->count();
        $approvals = $event->approvals->where('status', 'pending')->count();
        $risks = $event->risks->filter->isOpen()->count();

        $budget = (int) ($event->budget_cents ?? 0);
        $spent = (int) $event->budgetItems->sum('actual_cents');
        $usedPct = $budget > 0 ? (int) round($spent / $budget * 100) : 0;

        $days = $event->starts_at
            ? (int) round(now()->startOfDay()->diffInDays($event->starts_at->copy()->startOfDay(), false))
            : null;

        return array_values(array_filter([
            ['label' => 'Participants', 'value' => number_format($event->attendees()->count()),
                'foot' => 'registered', 'icon' => 'people'],
            $days === null ? null : ['label' => 'Days Left', 'value' => $days >= 0 ? (string) $days : 'Done',
                'foot' => $event->starts_at->format('M j, Y'), 'icon' => 'cal'],
            ['label' => 'Open Tasks', 'value' => (string) $open->count(),
                'foot' => $overdue ? $overdue.' overdue' : 'none overdue', 'icon' => 'tasks',
                'tone' => $overdue ? 'risk' : null],
            ['label' => 'Budget Used', 'value' => $usedPct.'%',
                'foot' => 'of '.$event->currencySymbol().' '.number_format($budget / 100), 'icon' => 'money',
                'tone' => $usedPct > 90 ? 'risk' : null],
            ['label' => 'Suppliers', 'value' => (string) $event->suppliers->count(),
                'foot' => 'engaged', 'icon' => 'supplier'],
            ['label' => 'Open Risks', 'value' => (string) $risks,
                'foot' => $risks ? 'need mitigation' : 'all clear', 'icon' => 'risk',
                'tone' => $risks ? 'risk' : null],
            ['label' => 'Approvals', 'value' => (string) $approvals,
                'badge' => $approvals ? $approvals.' pending' : null,
                'foot' => $approvals ? null : 'all clear', 'icon' => 'approve'],
        ]));
    }

    /** The module dock for one event — only the modules it has switched on. */
    public static function dock(Event $event, string $current): array
    {
        $icons = [
            'overview' => 'home', 'planning' => 'plan', 'tasks' => 'tasks', 'agenda' => 'cal',
            'speakers' => 'people', 'venue' => 'vault', 'suppliers' => 'supplier',
            'transportation' => 'supplier', 'accommodation' => 'home', 'exhibition' => 'grid',
            'sponsors' => 'star', 'attendees' => 'people', 'budget' => 'money',
            'brief' => 'doc', 'contract' => 'doc', 'risks' => 'risk', 'approvals' => 'approve',
            'files' => 'vault', 'reports' => 'report', 'ai' => 'ai', 'settings' => 'settings',
        ];

        $out = [];
        foreach (array_merge(['overview' => ['Overview']], Event::HUB_MODULES) as $key => $meta) {
            if ($key !== 'overview' && ! $event->moduleEnabled($key)) {
                continue;
            }
            $out[] = [
                'key' => $key,
                'label' => $key === 'overview' ? 'Overview' : $meta[0],
                'icon' => $icons[$key] ?? 'grid',
                'href' => route('events.hub', [$event, 'tab' => $key]),
            ];
        }

        return $out;
    }

    /** Event Pulse — the health engine's own components, plus what is late. */
    public static function pulse(Event $event, array $health): array
    {
        $rows = [];
        foreach ($health['components'] ?? [] as $key => $score) {
            if ($score === null) {
                continue;
            }
            $band = CommandCanvasData::band((int) round($score));
            $rows[] = ['label' => ucfirst($key), 'value' => (int) round($score).'%', 'tone' => $band['tone']];
        }

        $overdue = $event->tasks->filter(fn ($t) => $t->isOpen() && $t->due_on?->isPast())->count();
        if ($overdue) {
            $rows[] = ['label' => 'Overdue tasks', 'value' => (string) $overdue, 'tone' => 'risk'];
        }

        return $rows;
    }
}
