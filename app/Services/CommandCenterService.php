<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\Event;
use App\Models\EventContractPayment;
use App\Models\Project;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Collection;

/**
 * Aggregates everything the Command Center dashboard shows.
 * Every number is derived from real records — no invented metrics; where a
 * data source doesn't exist yet (equipment → Phase 4) the value is null.
 */
class CommandCenterService
{
    public function __construct(private EventHealthService $healthService) {}

    /**
     * Scoring one event walks ten relations, and this dashboard scores the
     * portfolio five times over. Load the events once, with those relations
     * eager-loaded, and remember each score — the difference is ~250 queries
     * a page against ~60. The service lives for one request, so nothing can
     * go stale underneath the memo.
     */
    private ?Collection $eventsMemo = null;

    /** @var array<int, array> */
    private array $healthMemo = [];

    private function allEvents(): Collection
    {
        return $this->eventsMemo ??= Event::whereNull('archived_at')
            ->with(EventHealthService::RELATIONS)
            ->orderBy('starts_at')
            ->get();
    }

    /** The same set Event::active() returns, filtered in memory. */
    private function activeEvents(): Collection
    {
        return $this->allEvents()
            ->reject(fn (Event $event) => in_array($event->stage, ['completed', 'closed', 'cancelled'], true))
            ->values();
    }

    private function healthFor(Event $event): array
    {
        return $this->healthMemo[$event->id] ??= $this->healthService->breakdown($event);
    }

    /** Average open tasks a team member can carry before being "fully utilized". */
    private const MEMBER_TASK_CAPACITY = 12;

    /** Concurrent engagements a supplier can serve before being "fully utilized". */
    private const SUPPLIER_ENGAGEMENT_CAPACITY = 3;

    /** A venue counts as "in use" when it hosts an event within this window. */
    private const VENUE_WINDOW_DAYS = 60;

    public function stats(): array
    {
        return [
            'events' => $this->allEvents()->count(),
            'projects' => Project::where('status', 'active')->count(),
            'budget' => (int) $this->allEvents()->sum('budget_cents'),
            'openTasks' => Task::whereNot('status', 'done')->count(),
            'atRisk' => $this->activeEvents()
                ->filter(fn (Event $e) => in_array($this->healthFor($e)['status'], ['at_risk', 'behind'], true))
                ->count(),
        ];
    }

    /**
     * Events arranged on an ellipse around the AI Command Core.
     * Adds pos_x / pos_y (percent coordinates within the hub canvas).
     */
    public function islands(): Collection
    {
        $events = $this->activeEvents();
        $count = max($events->count(), 1);

        return $events->values()->map(function (Event $event, int $i) use ($count) {
            $deg = -90 + (360 / $count) * $i;
            $angle = deg2rad($deg);

            $event->pos_x = round(50 + 42 * cos($angle), 2);
            $event->pos_y = round(50 + 38 * sin($angle), 2);
            $event->orbit_deg = $deg;

            // Curved connector control point: straight-line midpoint pushed
            // perpendicular so every arc bows the same rotational way.
            $mx = (50 + $event->pos_x) / 2;
            $my = (50 + $event->pos_y) / 2;
            $dx = $event->pos_x - 50;
            $dy = $event->pos_y - 50;
            $len = max(sqrt($dx * $dx + $dy * $dy), 0.001);
            $event->ctrl_x = round($mx + (-$dy / $len) * 9, 2);
            $event->ctrl_y = round($my + ($dx / $len) * 9, 2);

            // Islands show the same computed health score as the Event Hub.
            $health = $this->healthFor($event);
            $event->health_score = $health['score'];
            $event->health_group = $health['group'];
            $event->health_status = $health['status'];
            $event->open_risks = $event->risks->filter->isOpen()->count();

            return $event;
        });
    }

    /**
     * The platform orbit: every active event's tasks on the six stages.
     * Each node carries a couple of real previews for the floating cards.
     */
    public function taskOrbit(): array
    {
        $tasks = Task::with(['event', 'assignee'])
            ->whereHas('event', fn ($q) => $q->active())
            ->get();

        return collect(Task::STAGES)->keys()->values()->map(function ($status, $i) use ($tasks) {
            [$label, $hex] = Task::STAGES[$status];
            $members = $tasks->where('status', $status);
            $angle = deg2rad(-90 + $i * 60);

            return [
                'status' => $status,
                'label' => $label,
                'hex' => $hex,
                'count' => $members->count(),
                'blocked' => 0,
                'x' => round(50 + 38 * cos($angle), 2),
                'y' => round(50 + 38 * sin($angle), 2),
                'angle' => -90 + $i * 60,
                'previews' => $members->sortBy(fn ($t) => $t->due_on?->timestamp ?? PHP_INT_MAX)->take(2)->values(),
            ];
        })->all();
    }

    /**
     * Equipment readiness across the active portfolio. Every room's equipment
     * lines move needed → requested → confirmed → onsite; a line counts as
     * ready once it is confirmed or on site.
     */
    private function equipmentReadiness(): array
    {
        $lines = $this->activeEvents()
            ->flatMap(fn (Event $event) => $event->rooms->flatMap(
                fn ($room) => $room->equipmentLines()
            ));

        if ($lines->isEmpty()) {
            return ['pct' => null, 'hint' => 'no equipment listed on any room yet'];
        }

        $ready = $lines->filter(fn ($line) => in_array($line['status'] ?? 'needed', ['confirmed', 'onsite'], true))->count();

        return [
            'pct' => (int) round($ready / $lines->count() * 100),
            'hint' => $ready.' of '.$lines->count().' lines confirmed or on site',
        ];
    }

    /**
     * Portfolio spend against estimate, from the budget lines the events
     * already carry — the Budget module tracks both, no new source needed.
     */
    public function portfolioSpend(): array
    {
        $items = $this->allEvents()->flatMap->budgetItems;

        return [
            'estimated' => (int) $items->sum('estimated_cents'),
            'actual' => (int) $items->sum('actual_cents'),
            'lines' => $items->count(),
        ];
    }

    /**
     * The intelligence core: one health picture across every active event,
     * from the same engine each hub shows.
     */
    public function platformCore(): array
    {
        $events = $this->activeEvents();
        // avg() skips nulls, so unscored events don't drag the platform average down.
        $breakdowns = $events->map(fn ($e) => $this->healthFor($e));

        $tasks = Task::whereHas('event', fn ($q) => $q->active())->get();
        $open = $tasks->filter->isOpen();
        $overdue = $open->filter(fn ($t) => $t->due_on && $t->due_on->lt(now()->startOfDay()))->count();
        $risks = $events->sum(fn ($e) => $e->risks->filter->isOpen()->count());

        $health = (int) round($breakdowns->avg('score') ?? 0);
        $budgetAvg = (int) round($breakdowns->pluck('components.budget')->filter()->avg() ?? 0);

        return [
            'health' => $health,
            'healthWord' => match (true) {
                $health >= 81 => 'Excellent', $health >= 61 => 'Good',
                $health >= 41 => 'At Risk', default => 'Critical',
            },
            'tasksPct' => $tasks->count() ? (int) round($tasks->where('status', 'done')->count() / $tasks->count() * 100) : 0,
            'schedule' => $overdue === 0 ? 'On Track' : $overdue.' overdue',
            'onTrack' => $overdue === 0,
            'budgetWord' => match (true) {
                $budgetAvg >= 81 => 'Good', $budgetAvg >= 61 => 'Fair', default => 'Strained',
            },
            'budgetGood' => $budgetAvg >= 61,
            'riskWord' => match (true) {
                $risks <= 2 => 'Low', $risks <= 5 => 'Medium', default => 'High',
            },
            'riskLow' => $risks <= 2,
        ];
    }

    /** The live feed:decisions from the audit trail, newest first. */
    public function activityFeed(int $limit = 8)
    {
        return AuditLog::with(['user', 'event'])->latest('id')->limit($limit)->get();
    }

    /**
     * Explainable alert feed: every alert names its source records.
     */
    /**
     * One alert per unhealthy event — the complete set, before `alerts()` trims
     * it for display. Kept separate so "every at-risk event raises an alert"
     * can be verified without the presentation cap hiding some of them.
     */
    public function healthAlerts(): Collection
    {
        $alerts = collect();

        // Health is computed, never stored — the same score the Event Hub shows.
        foreach ($this->allEvents() as $event) {
            $health = $this->healthFor($event);
            if (! in_array($health['status'], ['at_risk', 'behind'], true)) {
                continue;
            }
            $alerts->push([
                'severity' => $health['status'] === 'behind' ? 'risk' : 'warn',
                'title' => $event->name.' '.($health['status'] === 'behind' ? 'behind schedule' : 'at risk'),
                'detail' => 'health '.$health['score'].'% · starts '.$event->starts_at?->diffForHumans(),
            ]);
        }

        return $alerts;
    }

    /**
     * The dashboard's alert rail: health, conflicts, money and urgent tasks,
     * risk-first and capped — it is a shortlist, not an exhaustive feed.
     */
    public function alerts(): Collection
    {
        $alerts = $this->healthAlerts();

        // The same venue, person or supplier promised to two events at once.
        foreach (app(ResourceConflicts::class)->detect() as $conflict) {
            $alerts->push([
                'severity' => $conflict['severity'],
                'title' => ucfirst($conflict['type']).' double-booked: '.$conflict['label'],
                'detail' => $conflict['detail'],
            ]);
        }

        // Money that should have landed and hasn't — the alert that pays rent.
        $overduePayments = EventContractPayment::with('event')
            ->whereDate('due_on', '<', now())
            ->whereColumn('paid_cents', '<', 'amount_cents')
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->orderBy('due_on')
            ->get();

        foreach ($overduePayments as $p) {
            $alerts->push([
                'severity' => 'risk',
                'title' => ($p->event?->name ?? 'Event').' — installment overdue',
                'detail' => $p->label.' · '.Event::moneyIn($p->outstandingCents(), $p->event?->currency ?? 'USD').' outstanding since '.$p->due_on->format('j M'),
            ]);
        }

        $urgent = Task::with('event')
            ->whereNot('status', 'done')
            ->whereIn('priority', ['urgent', 'high'])
            ->whereHas('event', fn ($query) => $query->whereNull('archived_at'))
            ->orderBy('due_on')
            ->limit(4)
            ->get();

        foreach ($urgent as $task) {
            $alerts->push([
                'severity' => $task->priority === 'urgent' ? 'risk' : 'warn',
                'title' => $task->title,
                'detail' => ($task->event?->name ?? 'General').' · due '.$task->due_on?->diffForHumans(),
            ]);
        }

        return $alerts
            ->sortBy(fn (array $alert) => $alert['severity'] === 'risk' ? 0 : 1)
            ->take(6)
            ->values();
    }

    /**
     * Utilization percentages with the formula stated per line.
     */
    public function utilization(): array
    {
        $members = max(User::count(), 1);
        $openAssigned = Task::whereNot('status', 'done')->whereNotNull('assignee_id')->count();

        $venuesTotal = max(Venue::count(), 1);
        $venuesInUse = Venue::whereHas('events', fn ($query) => $query
            ->active()
            ->whereBetween('starts_at', [now(), now()->addDays(self::VENUE_WINDOW_DAYS)]))->count();

        $suppliersTotal = max(Supplier::count(), 1);
        $engagements = $this->activeEvents()->sum(fn (Event $e) => $e->suppliers->count());

        $equipment = $this->equipmentReadiness();

        return [
            [
                'label' => 'Team Members',
                'pct' => min(100, (int) round($openAssigned / ($members * self::MEMBER_TASK_CAPACITY) * 100)),
                'hint' => $openAssigned.' open tasks across '.$members.' people',
            ],
            [
                'label' => 'Venues',
                'pct' => min(100, (int) round($venuesInUse / $venuesTotal * 100)),
                'hint' => $venuesInUse.' of '.$venuesTotal.' booked in the next '.self::VENUE_WINDOW_DAYS.' days',
            ],
            [
                'label' => 'Suppliers',
                'pct' => min(100, (int) round($engagements / ($suppliersTotal * self::SUPPLIER_ENGAGEMENT_CAPACITY) * 100)),
                'hint' => $engagements.' active engagements',
            ],
            [
                'label' => 'Equipment',
                'pct' => $equipment['pct'],
                'hint' => $equipment['hint'],
            ],
        ];
    }

    /**
     * Budget split by event health group (finance ledger arrives in Phase 3).
     */
    public function budgetByHealth(): array
    {
        // 'neutral' is budget sitting on draft/proposal events — money that is
        // not committed yet, and must never be reported as money at risk.
        $groups = ['track' => 0, 'warn' => 0, 'risk' => 0, 'neutral' => 0];

        foreach ($this->allEvents() as $event) {
            $groups[$this->healthFor($event)['group']] += $event->budget_cents;
        }

        $total = max(array_sum($groups), 1);

        return [
            'total' => array_sum($groups),
            'segments' => collect($groups)->map(fn (int $cents, string $group) => [
                'group' => $group,
                'cents' => $cents,
                'pct' => round($cents / $total * 100, 1),
            ])->values()->all(),
        ];
    }

    public function taskCounts(): array
    {
        $tasks = Task::whereHas('event', fn ($q) => $q->active())->get();

        return collect(Task::STAGES)->map(function ($meta, $status) use ($tasks) {
            [$label, $hex] = $meta;

            return ['label' => $label, 'hex' => $hex, 'count' => $tasks->where('status', $status)->count()];
        })->all();
    }

    public function deadlines(): Collection
    {
        return Task::with('event')
            ->whereNot('status', 'done')
            ->whereNotNull('due_on')
            ->orderBy('due_on')
            ->limit(4)
            ->get();
    }

    public function topSuppliers(): Collection
    {
        return Supplier::orderByDesc('rating')->limit(3)->get();
    }

    public function statusBars(): array
    {
        // Event health is computed, never stored; the lifecycle lives on `stage`.
        $groups = $this->activeEvents()->groupBy(fn (Event $e) => $this->healthFor($e)['group']);

        $counts = [
            'On Track' => $groups->get('track', collect())->count(),
            'In Progress' => $groups->get('warn', collect())->count(),
            'At Risk' => $groups->get('risk', collect())->count(),
            'Not Started' => $groups->get('neutral', collect())->count(),
            'Completed' => $this->allEvents()->whereIn('stage', ['completed', 'closed'])->count(),
        ];

        return [
            'counts' => $counts,
            'max' => max(max($counts), 1),
        ];
    }
}
