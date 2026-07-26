<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\EventRisk;
use App\Models\Task;
use App\Services\EventHealthService;
use Illuminate\Support\Collection;

/**
 * Real data for the Elite Command Canvas.
 *
 * Every method returns the exact shape its component consumes, so the views did
 * not change when this stopped being static. Figures come from the same health
 * engine the rest of the platform uses, so the dashboard can never disagree with
 * the event it links to.
 */
class CommandCanvasData
{
    private static ?Collection $cache = null;
    private static ?Collection $healthCache = null;

    /** Active events, loaded once per request. */
    private static function all(): Collection
    {
        return self::$cache ??= Event::active()
            ->with(['client', 'venue', 'projectManager'])
            ->withCount(['attendees', 'suppliers', 'sponsors'])
            ->orderBy('starts_at')
            ->get();
    }

    private static function health(): Collection
    {
        if (self::$healthCache === null) {
            $svc = app(EventHealthService::class);
            self::$healthCache = self::all()->mapWithKeys(fn (Event $e) => [$e->id => $svc->breakdown($e)]);
        }

        return self::$healthCache;
    }

    private static function money(int $cents, string $cur = 'JD'): string
    {
        $abs = abs($cents) / 100;

        return $abs >= 1_000_000
            ? $cur.' '.rtrim(rtrim(number_format($abs / 1_000_000, 2), '0'), '.').'M'
            : ($abs >= 1000 ? $cur.' '.round($abs / 1000).'K' : $cur.' '.number_format($abs));
    }

    /** The shared health scale — one place, used by every badge on the platform. */
    public static function band(int $score): array
    {
        return match (true) {
            $score >= 90 => ['value' => $score, 'label' => 'Excellent', 'tone' => 'ok'],
            $score >= 70 => ['value' => $score, 'label' => 'Good', 'tone' => 'info'],
            $score >= 50 => ['value' => $score, 'label' => 'Watch', 'tone' => 'warn'],
            default => ['value' => $score, 'label' => 'At Risk', 'tone' => 'risk'],
        };
    }

    // ── The dark KPI strip ───────────────────────────────────────────────

    public static function pulse(): array
    {
        $events = self::all();
        $ids = $events->pluck('id');

        $openTasks = Task::whereIn('event_id', $ids)->whereNotIn('status', ['done', 'cancelled'])->count();
        $overdue = Task::whereIn('event_id', $ids)->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_on')->whereDate('due_on', '<', now())->count();

        $risks = EventRisk::whereIn('event_id', $ids)->whereIn('status', ['open', 'escalated'])->count();
        $approvals = EventApproval::whereIn('event_id', $ids)->where('status', 'pending')->count();
        $urgent = EventApproval::whereIn('event_id', $ids)->where('status', 'pending')
            ->where('created_at', '<', now()->subDays(3))->count();

        $revenue = (int) $events->sum(fn (Event $e) => (int) ($e->incomeSummary()['collected'] ?? 0));

        return [
            ['label' => 'Active Events', 'value' => (string) $events->count(),
                'foot' => $events->where('stage', 'live')->count().' live', 'icon' => 'events'],
            ['label' => 'Participants', 'value' => number_format($events->sum('attendees_count')),
                'foot' => 'registered', 'icon' => 'people'],
            ['label' => 'Total Revenue', 'value' => self::money($revenue),
                'foot' => 'collected', 'icon' => 'money'],
            ['label' => 'Open Tasks', 'value' => (string) $openTasks,
                'foot' => $overdue ? $overdue.' overdue' : 'none overdue', 'icon' => 'tasks',
                'tone' => $overdue ? 'risk' : null],
            ['label' => 'Open Risks', 'value' => (string) $risks,
                'foot' => $risks ? 'need mitigation' : 'all clear', 'icon' => 'risk',
                'tone' => $risks ? 'risk' : null],
            ['label' => 'Pending Approvals', 'value' => (string) $approvals,
                'badge' => $urgent ? $urgent.' urgent' : null,
                'foot' => $urgent ? null : 'awaiting review', 'icon' => 'approve'],
        ];
    }

    public static function health_(): array
    {
        return self::health()->isEmpty()
            ? ['value' => 0, 'label' => '—']
            : self::band((int) round(self::health()->avg('score')));
    }

    // ── Navigation ───────────────────────────────────────────────────────

    public static function dock(): array
    {
        return [
            ['key' => 'home', 'label' => 'Home', 'icon' => 'home', 'href' => route('home')],
            ['key' => 'events', 'label' => 'Events', 'icon' => 'events', 'href' => route('events.index')],
            ['key' => 'people', 'label' => 'People', 'icon' => 'people', 'href' => url('/team')],
            ['key' => 'plan', 'label' => 'Plan', 'icon' => 'plan', 'href' => url('/tasks')],
            ['key' => 'money', 'label' => 'Money', 'icon' => 'money', 'href' => url('/finance')],
            ['key' => 'live', 'label' => 'Live', 'icon' => 'live', 'href' => route('events.index')],
            ['key' => 'intelligence', 'label' => 'Intelligence', 'icon' => 'intel', 'href' => url('/reports')],
            ['key' => 'vault', 'label' => 'Vault', 'icon' => 'vault', 'href' => url('/assets')],
            ['key' => 'settings', 'label' => 'Settings', 'icon' => 'settings', 'href' => url('/settings')],
        ];
    }

    // ── The canvas ───────────────────────────────────────────────────────

    /** The event at the centre: live if there is one, otherwise the nearest. */
    private static function primary(): ?Event
    {
        // Live wins; otherwise the event with the most committed to it. The
        // centre of gravity should be what the company is actually running,
        // not whichever stub happens to have the nearest date.
        return self::all()->first(fn (Event $e) => $e->stage === 'live')
            ?? self::all()->sortByDesc(fn (Event $e) => [
                in_array($e->stage, ['production', 'confirmed'], true) ? 1 : 0,
                (int) $e->budget_cents,
            ])->first();
    }

    public static function primaryEvent(): ?array
    {
        $e = self::primary();
        if (! $e) {
            return null;
        }

        $h = self::health()[$e->id] ?? ['score' => 0];
        $risks = EventRisk::where('event_id', $e->id)->whereIn('status', ['open', 'escalated'])->count();
        $overdue = $e->tasks()->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_on')->whereDate('due_on', '<', now())->count();

        return [
            'name' => $e->name,
            'dates' => $e->starts_at?->format('M j').' – '.(($e->ends_at ?? $e->starts_at)?->format('M j, Y')),
            'venue' => $e->venue?->name ?? trim($e->city.', '.$e->country, ', '),
            'health' => (int) $h['score'],
            'participants' => number_format($e->attendees_count),
            'risks' => $risks,
            'nextAction' => $overdue
                ? 'Clear '.$overdue.' overdue '.str('task')->plural($overdue)
                : 'Review event plan',
            'href' => route('events.hub', $e),
        ];
    }

    /**
     * The events orbiting the centre. Positions come from fixed slots rather
     * than a formula: the pods are wide, so only a handful of placements
     * actually clear the centre hexagon at any width.
     */
    public static function events(): array
    {
        $slots = [
            ['x' => 11, 'y' => 5],
            ['x' => 64, 'y' => 5],
            ['x' => 1, 'y' => 45],
            ['x' => 69, 'y' => 45],
            ['x' => 35, 'y' => 78],
        ];

        $primaryId = self::primary()?->id;

        // At-risk first, then soonest: the canvas shows what needs attention.
        $rest = self::all()
            ->reject(fn (Event $e) => $e->id === $primaryId)
            ->sortBy(fn (Event $e) => [
                (self::health()[$e->id]['score'] ?? 100) >= 50 ? 1 : 0,
                $e->starts_at?->timestamp ?? PHP_INT_MAX,
            ])
            ->take(count($slots))
            ->values();

        return $rest->map(function (Event $e, int $i) use ($slots) {
            $h = self::health()[$e->id] ?? ['score' => 0];
            $risks = EventRisk::where('event_id', $e->id)->whereIn('status', ['open', 'escalated'])->count();

            return [
                'name' => $e->name,
                'status' => match ($e->stage) {
                    'live', 'production' => 'progress',
                    'confirmed', 'completed' => 'confirmed',
                    default => ((int) ($h['score'] ?? 100)) < 50 ? 'risk' : 'planning',
                },
                'statusLabel' => str($e->stage)->replace('_', ' ')->title()->toString(),
                'dates' => $e->starts_at?->format('M j').' – '.(($e->ends_at ?? $e->starts_at)?->format('M j, Y')),
                'location' => $e->venue?->name ?? trim($e->city.', '.$e->country, ', '),
                'health' => (int) $h['score'],
                'participants' => number_format($e->attendees_count),
                'budget' => self::money((int) $e->budget_cents, $e->currencySymbol()),
                'foot' => $risks ? $risks.' '.str('Risk')->plural($risks) : 'On Track',
                'footTone' => $risks ? 'risk' : 'ok',
                'href' => route('events.hub', $e),
                'x' => $slots[$i]['x'], 'y' => $slots[$i]['y'], 'w' => 30,
            ];
        })->all();
    }

    /** How many active events did not fit on the canvas. */
    public static function overflow(): int
    {
        return max(0, self::all()->count() - 6);
    }

    // ── Right rail ───────────────────────────────────────────────────────

    /** AI Executive Director — what needs a decision, worst event first. */
    public static function aiRoute(): array
    {
        $svc = app(EventHealthService::class);
        $out = [];
        $seen = [];

        foreach (self::all()->sortBy(fn (Event $e) => self::health()[$e->id]['score'] ?? 100) as $e) {
            foreach (array_slice($svc->aiSummary($e)['attention'] ?? [], 0, 2) as $line) {
                $kind = str($line)->before(':')->before('—')->trim()->toString();
                if (in_array($kind, $seen, true)) {
                    continue;
                }
                $seen[] = $kind;

                $tone = str_contains($line, 'Risk') || str_contains($line, 'overdue') || str_contains($line, 'issue')
                    ? 'risk'
                    : (str_contains($line, 'approval') || str_contains($line, 'unassigned') ? 'warn' : 'info');

                $out[] = [
                    'title' => str($e->name)->limit(20).': '.str($line)->limit(44),
                    'impact' => ['risk' => 'High impact', 'warn' => 'Medium impact', 'info' => 'Low impact'][$tone],
                    'due' => $e->starts_at
                        ? 'Event in '.max(0, (int) round(now()->diffInDays($e->starts_at, false))).' days'
                        : 'No date set',
                    'tone' => $tone,
                    'href' => route('events.hub', $e),
                ];
            }
            if (count($out) >= 4) {
                break;
            }
        }

        return array_slice($out, 0, 4);
    }

    /** Live Signals — every line traces to a record. */
    public static function signals(): array
    {
        $out = [];

        foreach (self::all() as $e) {
            foreach ($e->approvals()->where('status', 'pending')->latest()->limit(2)->get() as $a) {
                $out[] = ['impact' => 'Medium Impact', 'tone' => 'warn', 'title' => $a->title,
                    'context' => $e->name.' · '.str($a->type)->title().' approval',
                    'time' => $a->created_at->format('h:i A'), 'sort' => $a->created_at->timestamp];
            }
            foreach ($e->tasks()->whereNotIn('status', ['done', 'cancelled'])
                ->whereNotNull('due_on')->whereDate('due_on', '<', now())
                ->orderBy('due_on')->limit(2)->get() as $t) {
                $out[] = ['impact' => 'High Impact', 'tone' => 'risk', 'title' => $t->title,
                    'context' => $e->name.' · due '.$t->due_on->diffForHumans(),
                    // due_on is a date, so a clock time here would always read midnight.
                    'time' => $t->due_on->format('M j'), 'sort' => $t->due_on->timestamp];
            }
        }

        usort($out, fn ($a, $b) => [$a['tone'] === 'risk' ? 0 : 1, $a['sort']] <=> [$b['tone'] === 'risk' ? 0 : 1, $b['sort']]);

        return array_slice($out, 0, 4);
    }

    public static function quickActions(): array
    {
        return [
            ['label' => 'New Event', 'icon' => 'events', 'href' => route('events.create')],
            ['label' => 'New Task', 'icon' => 'tasks', 'href' => url('/tasks')],
            ['label' => 'Add Contact', 'icon' => 'people', 'href' => url('/crm')],
            ['label' => 'Add Supplier', 'icon' => 'supplier', 'href' => url('/suppliers')],
            ['label' => 'New Contract', 'icon' => 'doc', 'href' => route('events.index')],
            ['label' => 'New Payment', 'icon' => 'money', 'href' => url('/finance')],
            ['label' => 'Upload Document', 'icon' => 'upload', 'href' => url('/assets')],
            ['label' => 'Generate Report', 'icon' => 'report', 'href' => url('/reports')],
            ['label' => 'Ask AI', 'icon' => 'ai', 'href' => url('/ai-assistant')],
            ['label' => 'More', 'icon' => 'more', 'href' => url('/settings')],
        ];
    }

    // ── Bottom insight cards ─────────────────────────────────────────────

    /** Mission Route — the company's journey, counted from real stages. */
    public static function missionRoute(): array
    {
        $by = self::all()->countBy('stage');
        $n = fn (string $k) => (int) ($by[$k] ?? 0);

        return [
            ['label' => 'Planning', 'count' => $n('planning').' Events', 'state' => $n('planning') ? 'ok' : 'idle'],
            ['label' => 'Production', 'count' => $n('production').' Events', 'state' => $n('production') ? 'ok' : 'idle'],
            ['label' => 'Build-Up', 'count' => $n('confirmed').' Events', 'state' => $n('confirmed') ? 'warn' : 'idle'],
            ['label' => 'Live', 'count' => $n('live').' Events', 'state' => $n('live') ? 'live' : 'idle'],
            ['label' => 'Close-Out', 'count' => $n('completed').' Events', 'state' => $n('completed') ? 'ok' : 'idle'],
            ['label' => 'Post Event', 'count' => $n('closed').' Events', 'state' => $n('closed') ? 'ok' : 'idle'],
        ];
    }

    public static function financial(): array
    {
        $events = self::all();
        $budget = (int) $events->sum('budget_cents');
        $spent = (int) $events->sum(fn (Event $e) => (int) $e->budgetItems()->sum('actual_cents'));
        $committed = (int) $events->sum(fn (Event $e) => (int) $e->budgetItems()->sum('estimated_cents'));
        $collected = (int) $events->sum(fn (Event $e) => (int) ($e->incomeSummary()['collected'] ?? 0));

        return [
            'usedPct' => $budget > 0 ? (int) round($spent / $budget * 100) : 0,
            'rows' => [
                ['label' => 'Total Budget', 'value' => self::money($budget)],
                ['label' => 'Committed', 'value' => self::money($committed)],
                ['label' => 'Spent', 'value' => self::money($spent)],
                ['label' => 'Remaining', 'value' => self::money(max(0, $budget - $spent))],
            ],
            'forecast' => ['label' => 'Income collected', 'value' => self::money($collected)],
        ];
    }

    /** Team workload — open tasks per person against a 5-task comfort load. */
    public static function workload(): array
    {
        $ids = self::all()->pluck('id');

        return Task::whereIn('event_id', $ids)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('assignee_id')
            ->with('assignee')
            ->get()
            ->groupBy('assignee_id')
            ->map(fn ($tasks) => [
                'team' => str($tasks->first()->assignee?->name ?? 'Unassigned')->explode(' ')->first(),
                'pct' => min(100, (int) round($tasks->count() / 5 * 100)),
            ])
            ->sortByDesc('pct')
            ->take(6)
            ->values()
            ->all();
    }

    /** Upcoming milestones — the next dated things across every event. */
    public static function milestones(): array
    {
        $ids = self::all()->pluck('id');

        return Task::whereIn('event_id', $ids)
            ->whereNotIn('status', ['done', 'cancelled'])
            ->whereNotNull('due_on')
            ->whereDate('due_on', '>=', now()->subDays(3))
            ->orderBy('due_on')
            ->limit(5)
            ->get()
            ->map(fn (Task $t) => [
                'label' => $t->title,
                'when' => $t->due_on->isToday() ? 'Today'
                    : ($t->due_on->isTomorrow() ? 'Tomorrow'
                    : ($t->due_on->isPast() ? 'Overdue' : $t->due_on->format('M j'))),
                'tone' => $t->due_on->isPast() ? 'risk' : ($t->due_on->diffInDays() <= 2 ? 'warn' : 'info'),
            ])
            ->all();
    }
}
