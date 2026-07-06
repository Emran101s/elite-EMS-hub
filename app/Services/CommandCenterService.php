<?php

namespace App\Services;

use App\Models\Event;
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
    public function __construct(private EventHealthService $healthService)
    {
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
            'events' => Event::whereNull('archived_at')->count(),
            'projects' => Project::where('status', 'active')->count(),
            'budget' => (int) Event::whereNull('archived_at')->sum('budget_cents'),
            'openTasks' => Task::whereNot('status', 'completed')->count(),
            'atRisk' => Event::whereNull('archived_at')->whereIn('status', ['at_risk', 'behind'])->count(),
        ];
    }

    /**
     * Events arranged on an ellipse around the AI Command Core.
     * Adds pos_x / pos_y (percent coordinates within the hub canvas).
     */
    public function islands(): Collection
    {
        $events = Event::with(['venue', 'avatar', 'tasks', 'budgetItems', 'suppliers', 'rooms', 'agendaSessions', 'risks', 'approvals'])
            ->whereNull('archived_at')->whereNot('status', 'completed')->orderBy('starts_at')->get();
        $count = max($events->count(), 1);

        return $events->values()->map(function (Event $event, int $i) use ($count) {
            $angle = deg2rad(-90 + (360 / $count) * $i);

            $event->pos_x = round(50 + 42 * cos($angle), 1);
            $event->pos_y = round(50 + 36 * sin($angle), 1);

            // Islands show the same computed health score as the Event Hub.
            $health = $this->healthService->breakdown($event);
            $event->health_score = $health['score'];
            $event->health_group = $health['group'];

            return $event;
        });
    }

    /**
     * Explainable alert feed: every alert names its source records.
     */
    public function alerts(): Collection
    {
        $alerts = collect();

        foreach (Event::whereIn('status', ['at_risk', 'behind'])->orderBy('starts_at')->get() as $event) {
            $alerts->push([
                'severity' => $event->status === 'behind' ? 'risk' : 'warn',
                'title' => $event->name.' '.($event->status === 'behind' ? 'behind schedule' : 'at risk'),
                'detail' => $event->progress.'% complete · starts '.$event->starts_at?->diffForHumans(),
            ]);
        }

        $urgent = Task::with('event')
            ->whereNot('status', 'completed')
            ->whereIn('priority', ['urgent', 'high'])
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
        $openAssigned = Task::whereNot('status', 'completed')->whereNotNull('assignee_id')->count();

        $venuesTotal = max(Venue::count(), 1);
        $venuesInUse = Venue::whereHas('events', fn ($query) => $query
            ->whereNot('status', 'completed')
            ->whereBetween('starts_at', [now(), now()->addDays(self::VENUE_WINDOW_DAYS)]))->count();

        $suppliersTotal = max(Supplier::count(), 1);
        $engagements = Event::whereNot('status', 'completed')
            ->withCount('suppliers')->get()->sum('suppliers_count');

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
                'pct' => null, // Assets module lands in Phase 4
                'hint' => 'available once Assets goes live',
            ],
        ];
    }

    /**
     * Budget split by event health group (finance ledger arrives in Phase 3).
     */
    public function budgetByHealth(): array
    {
        $groups = ['track' => 0, 'warn' => 0, 'risk' => 0];

        foreach (Event::all() as $event) {
            $groups[$event->healthGroup()] += $event->budget_cents;
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
        return [
            'completed' => Task::where('status', 'completed')->count(),
            'in_progress' => Task::where('status', 'in_progress')->count(),
            'pending' => Task::where('status', 'pending')->count(),
        ];
    }

    public function deadlines(): Collection
    {
        return Task::with('event')
            ->whereNot('status', 'completed')
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
        $counts = [
            'On Track' => Event::where('status', 'on_track')->count(),
            'In Progress' => Event::whereIn('status', ['in_progress', 'planning'])->count(),
            'At Risk' => Event::whereIn('status', ['at_risk', 'behind'])->count(),
            'Completed' => Event::where('status', 'completed')->count(),
        ];

        return [
            'counts' => $counts,
            'max' => max(max($counts), 1),
        ];
    }
}
