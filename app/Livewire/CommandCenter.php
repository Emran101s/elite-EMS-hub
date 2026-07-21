<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventContractPayment;
use App\Models\EventRisk;
use App\Models\PlanItem;
use App\Models\PlanTrack;
use App\Models\Task;
use App\Models\User;
use App\Services\EventHealthService;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The Operations Room — one screen that answers "what needs me right now".
 *
 * Everything on it traces to a real record: each signal names its event, says
 * why it fired, and links straight to the tab that fixes it. Clicking an event
 * focuses the whole room on that event; the lens chips filter by kind.
 */
#[Layout('components.layouts.app', ['title' => 'Operations Room', 'subtitle' => 'Everything that needs you, across every event.'])]
class CommandCenter extends Component
{
    /** Filter the stream to a single event (null = the whole portfolio). */
    public ?int $focusEvent = null;

    /** all | overdue | approvals | blocked | money | risks */
    public string $lens = 'all';

    /** The lenses, in priority order: key => [label, hex]. */
    public const LENSES = [
        'overdue' => ['Overdue', '#EF4444'],
        'approvals' => ['Approvals', '#F97316'],
        'blocked' => ['Blocked', '#8B5CF6'],
        'money' => ['Money', '#D4AF37'],
        'risks' => ['Risks', '#F59E0B'],
    ];

    public function focusOn(?int $eventId = null): void
    {
        $this->focusEvent = ($eventId && $this->focusEvent !== $eventId) ? $eventId : null;
    }

    public function setLens(string $lens): void
    {
        $this->lens = ($lens === $this->lens || ! array_key_exists($lens, self::LENSES)) ? 'all' : $lens;
    }

    public function render(EventHealthService $healthService)
    {
        $events = Event::active()
            ->with([...EventHealthService::RELATIONS, 'tasks.assignee'])
            ->orderBy('starts_at')
            ->get();

        $health = $events->mapWithKeys(fn (Event $e) => [$e->id => $healthService->breakdown($e)]);
        $signals = $this->signals($events, $health);

        // Per-event signal tallies drive the rail badges (before any filtering).
        $byEvent = $signals->groupBy('event_id')->map->count();

        $visible = $signals
            ->when($this->focusEvent, fn (Collection $c) => $c->where('event_id', $this->focusEvent))
            ->when($this->lens !== 'all', fn (Collection $c) => $c->where('lens', $this->lens))
            ->values();

        $today = now()->startOfDay();

        return view('livewire.command-center', [
            'w' => $this->workspace($events, $health),
            'events' => $events,
            'health' => $health,
            'signals' => $visible->take(40),
            'signalTotal' => $visible->count(),
            'lensCounts' => collect(self::LENSES)->mapWithKeys(fn ($m, $k) => [$k => $signals->where('lens', $k)->count()]),
            'byEvent' => $byEvent,
            'focused' => $this->focusEvent ? $events->firstWhere('id', $this->focusEvent) : null,
            'pulse' => [
                'health' => (int) round($health->avg('score') ?? 0),
                'events' => $events->count(),
                'live' => $events->filter(fn (Event $e) => $e->starts_at && $e->starts_at->copy()->startOfDay()->lte($today)
                    && ($e->ends_at ?? $e->starts_at)->copy()->endOfDay()->gte($today))->count(),
                'atRisk' => $health->filter(fn ($h) => in_array($h['status'], ['at_risk', 'behind'], true))->count(),
                'critical' => $signals->where('severity', 'critical')->count(),
                'signals' => $signals->count(),
            ],
            'week' => $events->filter(fn (Event $e) => $e->starts_at
                && $e->starts_at->copy()->startOfDay()->between($today, $today->copy()->addDays(14)))->values(),
        ]);
    }

    /**
     * The workspace cards — one read of the whole platform, not just tasks.
     * Every number here traces to a record and links somewhere useful.
     */
    private function workspace(Collection $events, Collection $health): array
    {
        $today = now()->startOfDay();
        $ids = $events->pluck('id');

        // ── Tasks across the active portfolio ──
        $tasks = $events->flatMap(fn (Event $e) => $e->tasks);
        $open = $tasks->filter->isOpen();
        $byStage = collect(Task::STAGES)->mapWithKeys(fn ($m, $k) => [$k => $tasks->where('status', $k)->count()]);
        $byPriority = collect(Task::PRIORITIES)->mapWithKeys(fn ($m, $k) => [$k => $open->where('priority', $k)->count()]);

        // ── The event these hero cards describe ──
        // Focusing an event in the rail drives them; otherwise fall back to the
        // nearest upcoming event that actually has work on it, so the cards are
        // never blank when there is something worth showing.
        $upcoming = $events->filter(fn (Event $e) => $e->starts_at && $e->starts_at->copy()->startOfDay()->gte($today));
        $withWork = $upcoming->first(fn (Event $e) => $tasks->where('event_id', $e->id)->isNotEmpty()
            || PlanTrack::where('event_id', $e->id)->exists());

        $next = ($this->focusEvent ? $events->firstWhere('id', $this->focusEvent) : null)
            ?? $withWork
            ?? $upcoming->first()
            ?? $events->first();

        $nextTasks = $next ? $tasks->where('event_id', $next->id) : collect();
        $trackRows = collect();
        if ($next) {
            $planByTrack = PlanItem::where('event_id', $next->id)->get()->groupBy('track_id');
            $trackRows = PlanTrack::where('event_id', $next->id)->orderBy('position')->get()
                ->map(function (PlanTrack $t) use ($planByTrack) {
                    $items = $planByTrack->get($t->id, collect());
                    $total = $items->count();
                    $done = $items->where('status', 'done')->count();

                    return ['name' => $t->name, 'color' => $t->color ?? '#3B82F6', 'done' => $done, 'total' => $total,
                        'pct' => $total ? (int) round($done / $total * 100) : 0];
                });
        }

        // ── Team load ──
        $byAssignee = $open->whereNotNull('assignee_id')->groupBy('assignee_id')->map->count();
        $people = User::whereIn('id', $byAssignee->keys())->get()
            ->map(fn (User $u) => ['user' => $u, 'count' => $byAssignee[$u->id] ?? 0])
            ->sortByDesc('count')->take(4)->values();

        // ── Money ──
        $budget = (int) $events->sum('budget_cents');
        $spent = (int) $events->sum(fn (Event $e) => $e->budgetItems->sum('actual_cents'));
        $outstanding = (int) EventContractPayment::whereIn('event_id', $ids)
            ->get()->sum(fn ($p) => max($p->amount_cents - $p->paid_cents, 0));
        $sponsorship = (int) \App\Models\EventSponsor::whereIn('event_id', $ids)->sum('amount_cents');

        return [
            'next' => $next,
            'nextDays' => $next?->starts_at ? (int) round($today->diffInDays($next->starts_at->copy()->startOfDay(), false)) : null,
            'nextDone' => $nextTasks->where('status', 'done')->count(),
            'nextTotal' => $nextTasks->count(),
            'nextOverdue' => $nextTasks->filter->isOverdue()->count(),
            'nextDue7' => $nextTasks->filter(fn (Task $t) => $t->isOpen() && $t->due_on
                && $t->due_on->copy()->startOfDay()->between($today, $today->copy()->addDays(7)))->count(),
            'nextUnassigned' => $nextTasks->filter(fn (Task $t) => $t->isOpen() && ! $t->assignee_id)->count(),
            'tracks' => $trackRows,
            'byStage' => $byStage,
            'byPriority' => $byPriority,
            'taskTotal' => $tasks->count(),
            'taskDone' => $byStage['done'] ?? 0,
            'overdue' => $open->filter->isOverdue()->count(),
            'due7' => $open->filter(fn (Task $t) => $t->due_on && $t->due_on->copy()->startOfDay()->between($today, $today->copy()->addDays(7)))->count(),
            'due30' => $open->filter(fn (Task $t) => $t->due_on && $t->due_on->copy()->startOfDay()->between($today, $today->copy()->addDays(30)))->count(),
            'unassigned' => $open->whereNull('assignee_id')->count(),
            'people' => $people,
            'teamSize' => User::count(),
            'risks' => $events->sum(fn (Event $e) => $e->risks->filter->isOpen()->count()),
            'approvals' => $events->sum(fn (Event $e) => $e->approvals->where('status', 'pending')->count()),
            'suppliers' => \App\Models\Supplier::count(),
            'sessions' => $events->sum(fn (Event $e) => $e->agendaSessions->count()),
            'budget' => $budget,
            'spent' => $spent,
            'spentPct' => $budget > 0 ? min(100, (int) round($spent / $budget * 100)) : 0,
            'outstanding' => $outstanding,
            'sponsorship' => $sponsorship,
            'currency' => $events->first()?->currency ?? 'USD',
        ];
    }

    /**
     * Every live signal across the portfolio, newest pain first.
     * Each one names its event and links to the tab that resolves it.
     */
    private function signals(Collection $events, Collection $health): Collection
    {
        $out = collect();
        $today = now()->startOfDay();
        $ids = $events->pluck('id');
        $names = $events->pluck('name', 'id');

        $push = function (string $lens, string $severity, int $eventId, string $title, string $detail, string $tab, $sortAt) use (&$out, $names) {
            $out->push([
                'key' => $lens.'-'.$eventId.'-'.md5($title.$detail),
                'lens' => $lens,
                'severity' => $severity,
                'event_id' => $eventId,
                'event_name' => $names[$eventId] ?? 'Event',
                'title' => $title,
                'detail' => $detail,
                'link' => route('events.hub', [$eventId, 'tab' => $tab]),
                'sort' => $sortAt instanceof \DateTimeInterface ? $sortAt->getTimestamp() : PHP_INT_MAX,
            ]);
        };

        $allTasks = $events->flatMap(fn (Event $e) => $e->tasks);

        // ── Overdue tasks ──
        foreach ($allTasks->filter(fn (Task $t) => $t->isOpen() && $t->due_on
            && $t->due_on->copy()->startOfDay()->lt($today))->sortBy('due_on') as $t) {
            $late = (int) $t->due_on->diffInDays($today);
            $push('overdue', $late > 7 ? 'critical' : 'warn', $t->event_id, $t->title,
                $late.' '.str('day')->plural($late).' overdue'.($t->assignee ? ' · '.str($t->assignee->name)->before(' ') : ''), 'tasks', $t->due_on);
        }

        // ── Awaiting sign-off ──
        foreach ($allTasks->where('status', 'review') as $t) {
            $push('approvals', 'warn', $t->event_id, $t->title, 'Task awaiting approval', 'tasks', $t->due_on ?? now());
        }
        foreach (PlanItem::whereIn('event_id', $ids)->where('status', 'needs_approval')->get() as $p) {
            $push('approvals', 'warn', $p->event_id, $p->title, 'Deliverable awaiting sign-off', 'planning', $p->due_on ?? now());
        }

        // ── Blocked / late deliverables ──
        foreach (PlanItem::whereIn('event_id', $ids)->whereNotIn('status', PlanItem::CLOSED)
            ->whereNotNull('due_on')->whereDate('due_on', '<', $today)->orderBy('due_on')->get() as $p) {
            $late = (int) $p->due_on->diffInDays($today);
            $push('blocked', $late > 7 ? 'critical' : 'warn', $p->event_id, $p->title,
                'Deliverable '.$late.' '.str('day')->plural($late).' late', 'planning', $p->due_on);
        }

        // ── Money that should have landed ──
        foreach (EventContractPayment::with('event')->whereIn('event_id', $ids)
            ->whereDate('due_on', '<', now())->whereColumn('paid_cents', '<', 'amount_cents')->orderBy('due_on')->get() as $p) {
            $push('money', 'critical', $p->event_id, $p->label ?: 'Installment overdue',
                Event::moneyIn($p->outstandingCents(), $p->event?->currency ?? 'USD').' outstanding since '.$p->due_on->format('j M'), 'contract', $p->due_on);
        }

        // ── Open risks that actually bite ──
        foreach ($events->flatMap(fn (Event $e) => $e->risks)->filter->isOpen() as $r) {
            if (($r->probability ?? 0) * ($r->impact ?? 0) < 9) {
                continue;
            }
            $push('risks', ($r->probability * $r->impact) >= 16 ? 'critical' : 'warn', $r->event_id, $r->title,
                'Risk score '.($r->probability * $r->impact).' · '.str($r->category ?? 'general')->replace('_', ' ')->title(), 'risks', now());
        }

        // ── Events whose computed health is poor ──
        foreach ($events as $e) {
            $h = $health[$e->id];
            if (! in_array($h['status'], ['at_risk', 'behind'], true)) {
                continue;
            }
            $push('risks', $h['status'] === 'behind' ? 'critical' : 'warn', $e->id, $e->name.' is '.str($h['status'])->replace('_', ' '),
                'Health '.$h['score'].'%'.($e->starts_at ? ' · starts '.$e->starts_at->diffForHumans() : ''), 'overview', now()->subYear());
        }

        return $out->sortBy([
            fn ($a, $b) => ($a['severity'] === 'critical' ? 0 : 1) <=> ($b['severity'] === 'critical' ? 0 : 1),
            fn ($a, $b) => $a['sort'] <=> $b['sort'],
        ])->values();
    }
}
