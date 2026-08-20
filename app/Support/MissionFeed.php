<?php

namespace App\Support;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventApproval;
use App\Models\EventBudgetVersion;
use App\Models\EventContract;
use Carbon\Carbon;

/**
 * Mission Feed — the Event Hub Overview's one compact "what's happening
 * around this event right now" list. Not an audit trail: three curated,
 * real sources, capped at 6 rows, ranked in the order a reader actually
 * cares about — action required, then what's coming up, then what was
 * just decided.
 *
 * A routine field edit (description, tags, metadata) never appears here.
 * Only a status transition on a model a real decision belongs to counts
 * as a "milestone" row — the same bar the good/bad examples in the brief
 * drew: "Budget approved" is a milestone, "Description edited" is noise.
 *
 * Tier 1 also carries the two real signals the old Live Alerts feed
 * showed that nothing else on the Hub covers — a forecast over its cap by
 * a specific amount (Command Stack's own budget signal only says "not
 * costed", never "by how much"), and a supplier flagged with an issue
 * (no other surface shows this at all). Both read the same computations
 * EventHubController::liveAlerts() already used.
 */
class MissionFeed
{
    /** Auditable models whose status transitions are real milestones — not every model that logs. */
    private const MILESTONE_TYPES = [EventApproval::class, EventContract::class, EventBudgetVersion::class];

    /** A move INTO one of these is a decision. A move into "pending"/"sent"/"draft" is still in progress. */
    private const MILESTONE_STATUSES = ['approved', 'rejected', 'signed', 'void', 'superseded'];

    private const MILESTONE_TAB = [
        'EventApproval' => 'approvals',
        'EventContract' => 'contract',
        'EventBudgetVersion' => 'budget',
    ];

    public static function rows(Event $event, int $limit = 6): array
    {
        $now = now();

        // Tier 1 — action required: open tasks already overdue or due inside 48h.
        $actionRequired = $event->tasks
            ->filter(fn ($t) => $t->isOpen() && $t->due_on && $t->due_on->lte($now->copy()->addDays(2)))
            ->sortBy('due_on')
            ->take(3)
            ->map(function ($t) use ($event, $now) {
                $days = (int) $now->copy()->startOfDay()->diffInDays($t->due_on, false);

                return [
                    'tier' => 1,
                    'icon' => 'clock',
                    'title' => $t->title,
                    'when' => $days < 0 ? abs($days).'d overdue' : ($days === 0 ? 'due today' : 'due in '.$days.'d'),
                    'sort' => $t->due_on,
                    'href' => route('events.hub', [$event, 'tab' => 'tasks']),
                ];
            });

        // Tier 1 also — a forecast that's blown its cap, and any supplier
        // flagged with an issue. Neither is invented here: same source
        // EventHubController::liveAlerts() already read.
        $cost = $event->costForecast();
        if ($cost['over'] > 0) {
            $actionRequired->push([
                'tier' => 1,
                'icon' => 'currency',
                'title' => 'Forecast is '.$event->money($cost['over']).' over budget',
                'when' => $cost['pct'].'% of cap',
                'sort' => $now,
                'href' => route('events.hub', [$event, 'tab' => 'budget']),
            ]);
        }

        foreach ($event->suppliers->filter(fn ($s) => $s->pivot->status === 'issue') as $supplier) {
            $actionRequired->push([
                'tier' => 1,
                'icon' => 'truck',
                'title' => 'Supplier issue: '.$supplier->name,
                'when' => 'needs attention',
                'sort' => $supplier->updated_at ?? $now,
                'href' => route('events.hub', [$event, 'tab' => 'suppliers']),
            ]);
        }

        foreach ($event->transport->where('status', 'issue') as $movement) {
            $actionRequired->push([
                'tier' => 1,
                'icon' => 'truck',
                'title' => 'Needs attention: '.($movement->route ?: 'Movement'),
                'when' => $movement->issue_note ?: 'flagged',
                'sort' => $movement->updated_at ?? $now,
                'href' => route('events.hub', [$event, 'tab' => 'transportation']),
            ]);
        }

        // Keep tier 1 from crowding out everything else on a bad week.
        $actionRequired = $actionRequired->take(3);

        // Tier 2 — what's coming up: the next real agenda sessions. starts_at
        // is a bare time string on the session; the day carries the date.
        $upcomingSessions = $event->agendaDays
            ->flatMap(fn ($day) => $day->sessions->map(function ($session) use ($day) {
                $session->setAttribute('mission_feed_starts', $day->date && $session->starts_at
                    ? Carbon::parse($day->date->format('Y-m-d').' '.$session->starts_at)
                    : null);

                return $session;
            }))
            ->filter(fn ($s) => $s->mission_feed_starts?->isFuture())
            ->sortBy('mission_feed_starts')
            ->take(2)
            ->map(fn ($s) => [
                'tier' => 2,
                'icon' => 'calendar',
                'title' => $s->title,
                'when' => 'starts '.$s->mission_feed_starts->diffForHumans(short: true),
                'sort' => $s->mission_feed_starts,
                'href' => route('events.hub', [$event, 'tab' => 'agenda']),
            ]);

        // Tier 3 — what was just decided: status changes only, on the few
        // models a real decision belongs to.
        $milestones = AuditLog::where('event_id', $event->id)
            ->whereIn('auditable_type', array_map('class_basename', self::MILESTONE_TYPES))
            ->where('action', 'updated')
            ->whereNotNull('changes')
            ->latest()
            ->limit(15)
            ->get()
            ->filter(fn ($log) => in_array($log->changes['status'][1] ?? null, self::MILESTONE_STATUSES, true))
            ->take(3)
            ->map(fn ($log) => [
                'tier' => 3,
                'icon' => 'sparkles',
                'title' => $log->label.' '.$log->changes['status'][1],
                'when' => $log->created_at->diffForHumans(short: true),
                'sort' => $log->created_at,
                'href' => route('events.hub', [$event, 'tab' => self::MILESTONE_TAB[$log->auditable_type] ?? 'overview']),
            ]);

        return $actionRequired
            ->concat($upcomingSessions)
            ->concat($milestones)
            ->sortBy('tier')
            ->take($limit)
            ->values()
            ->all();
    }
}
