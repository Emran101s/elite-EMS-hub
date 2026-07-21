<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\Task;
use App\Services\EventHealthService;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'Events', 'subtitle' => 'Manage all events, venues, suppliers, budgets and operations.'])]
class EventsIndex extends Component
{
    use WithPagination;

    public const PER_PAGE = 8;

    /** Filter tabs → event type groups. */
    public const TYPE_TABS = [
        'all' => null,
        'conference' => ['conference', 'summit', 'hybrid_event', 'online_event'],
        'workshop' => ['workshop', 'training_program'],
        'exhibition' => ['exhibition', 'career_fair', 'product_launch'],
        'gala' => ['gala_dinner', 'awards_ceremony'],
        'vip' => ['vip_reception', 'embassy_event', 'private_dinner'],
        'outdoor' => ['outdoor_event', 'public_event'],
    ];

    public string $q = '';

    public string $tab = 'all';

    public string $view = 'cards';

    /** The card opened in place to show its full detail. */
    public ?int $expandedId = null;

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        $this->selectedId = $this->expandedId ?? $this->selectedId;
    }

    public string $sort = 'date';

    public bool $starred = false;

    public string $calMonth = '';

    public ?string $stage = null;

    /** Exact-type filter from ?type= (deep links keep working). */
    public ?string $exactType = null;

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->q = (string) request('q', '');
        $this->stage = request('stage') ?: null;
        $this->exactType = request('type') ?: null;
        $this->view = in_array(request('view'), ['cards', 'calendar'], true) ? request('view') : 'cards';
        $this->selectedId = request()->integer('selected') ?: null;
        $this->calMonth = now()->format('Y-m');
    }

    public function select(int $eventId): void
    {
        $this->selectedId = $eventId;
    }

    public function setTab(string $tab): void
    {
        if (array_key_exists($tab, self::TYPE_TABS)) {
            $this->tab = $tab;
            $this->exactType = null;
            $this->resetPage();
        }
    }

    public function updatedQ(): void
    {
        $this->resetPage();
    }

    public function updatedSort(): void
    {
        $this->resetPage();
    }

    public function toggleStarred(): void
    {
        $this->starred = ! $this->starred;
        $this->resetPage();
    }

    public function toggleFavorite(int $eventId): void
    {
        auth()->user()->favoriteEvents()->toggle($eventId);
    }

    public function duplicate(int $eventId)
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-events');
        $source = Event::whereNull('archived_at')->findOrFail($eventId);

        $copy = $source->replicate(['progress']);
        $copy->name = $source->name.' (Copy)';
        $copy->stage = 'draft';
        $copy->progress = 0;
        $copy->archived_at = null;
        $copy->save();

        session()->flash('status', "“{$copy->name}” created as a draft — open its hub to set it up.");

        return $this->redirectRoute('events.index', ['selected' => $copy->id]);
    }

    public function archive(int $eventId): void
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-events');
        Event::findOrFail($eventId)->update(['archived_at' => now()]);

        if ($this->selectedId === $eventId) {
            $this->selectedId = null;
        }

        session()->flash('status', 'Event archived — it no longer appears in lists or the Operations Hub.');
    }

    /**
     * Permanently delete an event and everything hanging off it — plan, tasks,
     * agenda, budget, suppliers, the lot. Manager-only and unrecoverable.
     */
    public function deleteEvent(int $eventId): void
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-events');
        $event = Event::findOrFail($eventId);
        $name = $event->name;
        $event->delete();

        if ($this->selectedId === $eventId) {
            $this->selectedId = null;
        }

        session()->flash('status', "“{$name}” was permanently deleted.");
    }

    public function prevMonth(): void
    {
        $this->calMonth = Carbon::createFromFormat('Y-m', $this->calMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calMonth = Carbon::createFromFormat('Y-m', $this->calMonth)->addMonth()->format('Y-m');
    }

    /**
     * How many events the health engine rates at-risk or behind, portfolio-wide.
     * With no filters active the list already *is* the whole portfolio, so we
     * reuse the scores we just computed instead of loading every event again.
     */
    private function atRiskCount(\Illuminate\Support\Collection $health): int
    {
        $unfiltered = $this->q === '' && ! $this->exactType && ! $this->stage
            && ! $this->starred && $this->tab === 'all';

        if ($unfiltered) {
            return $health->filter(fn ($h) => in_array($h['status'], ['at_risk', 'behind'], true))->count();
        }

        $service = app(EventHealthService::class);

        return Event::whereNull('archived_at')->with(EventHealthService::RELATIONS)->get()
            ->filter(fn (Event $e) => in_array($service->breakdown($e)['status'], ['at_risk', 'behind'], true))
            ->count();
    }

    /**
     * Everything an expanded card reveals — computed only for the open card so
     * a long list stays cheap.
     */
    private function cardDetail(Event $event, array $health): array
    {
        $today = now()->startOfDay();

        $tasks = $event->tasks;
        $open = $tasks->filter->isOpen();

        $planByTrack = \App\Models\PlanItem::where('event_id', $event->id)->get()->groupBy('track_id');
        $tracks = \App\Models\PlanTrack::where('event_id', $event->id)->orderBy('position')->get()
            ->map(function ($t) use ($planByTrack) {
                $items = $planByTrack->get($t->id, collect());
                $total = $items->count();
                $done = $items->where('status', 'done')->count();

                return ['name' => $t->name, 'color' => $t->color ?? '#3B82F6', 'done' => $done,
                    'total' => $total, 'pct' => $total ? (int) round($done / $total * 100) : 0];
            });

        $budget = (int) $event->budget_cents;
        $spent = (int) $event->budgetItems->sum('actual_cents');
        $outstanding = (int) \App\Models\EventContractPayment::where('event_id', $event->id)->get()
            ->sum(fn ($p) => max($p->amount_cents - $p->paid_cents, 0));

        // The next things due — tasks and deliverables together, soonest first.
        $deadlines = collect()
            ->concat($open->filter(fn (Task $t) => $t->due_on)->map(fn (Task $t) => [
                'title' => $t->title, 'due' => $t->due_on, 'kind' => 'Task', 'hex' => $t->stageHex(),
            ]))
            ->concat(\App\Models\PlanItem::where('event_id', $event->id)
                ->whereNotIn('status', \App\Models\PlanItem::CLOSED)->whereNotNull('due_on')->get()
                ->map(fn ($p) => ['title' => $p->title, 'due' => $p->due_on, 'kind' => 'Deliverable', 'hex' => $p->statusHex()]))
            ->sortBy(fn ($r) => $r['due']->timestamp)->take(5)->values();

        return [
            'ai' => app(EventHealthService::class)->aiSummary($event),
            'components' => $health['components'] ?? [],
            'tracks' => $tracks,
            'stages' => collect(Task::STAGES)->mapWithKeys(fn ($m, $k) => [$k => $tasks->where('status', $k)->count()]),
            'taskTotal' => $tasks->count(),
            'overdue' => $open->filter->isOverdue()->count(),
            'unassigned' => $open->whereNull('assignee_id')->count(),
            'budget' => $budget,
            'spent' => $spent,
            'spentPct' => $budget > 0 ? min(100, (int) round($spent / $budget * 100)) : null,
            'outstanding' => $outstanding,
            'team' => $event->teamMembers,
            'risks' => $event->risks->filter->isOpen()->count(),
            'approvals' => $event->approvals->where('status', 'pending')->count(),
            'sessions' => $event->agendaSessions->count(),
            'suppliers' => $event->suppliers->count(),
            'deadlines' => $deadlines,
        ];
    }

    private function baseQuery()
    {
        return Event::query()
            ->whereNull('archived_at')
            ->when($this->q, fn ($query, $q) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%")
                ->orWhere('country', 'like', "%{$q}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                ->orWhereHas('venue', fn ($v) => $v->where('name', 'like', "%{$q}%"))))
            ->when($this->exactType, fn ($query, $type) => $query->where('type', $type))
            ->when(! $this->exactType && self::TYPE_TABS[$this->tab], fn ($query) => $query->whereIn('type', self::TYPE_TABS[$this->tab]))
            ->when($this->stage, fn ($query, $stage) => $query->where('stage', $stage))
            ->when($this->starred, fn ($query) => $query->whereHas('favoritedBy', fn ($f) => $f->whereKey(auth()->id())));
    }

    public function render()
    {
        $pulse = app(EventHealthService::class);

        $relations = [
            'venue', 'avatar', 'client', 'projectManager', 'tasks',
            'budgetItems', 'suppliers', 'rooms', 'agendaSessions', 'risks', 'approvals', 'sponsors', 'teamMembers',
        ];

        $all = $this->baseQuery()->with($relations)->get();

        $health = $all->mapWithKeys(fn (Event $event) => [$event->id => $pulse->breakdown($event)]);

        $metrics = $all->mapWithKeys(function (Event $event) {
            $estimated = $event->budgetItems->sum('estimated_cents');
            $actual = $event->budgetItems->sum('actual_cents');
            $denominator = $event->budget_cents > 0 ? $event->budget_cents : $estimated;

            return [$event->id => [
                'participants' => $event->expected_participants,
                'sponsors' => $event->sponsors->count(),
                'budget_used' => $denominator > 0 ? (int) round($actual / $denominator * 100) : null,
            ]];
        });

        $sorted = match ($this->sort) {
            'health' => $all->sortByDesc(fn (Event $event) => $health[$event->id]['score']),
            'budget' => $all->sortByDesc(fn (Event $event) => $metrics[$event->id]['budget_used'] ?? -1),
            default => $all->sortBy('starts_at'),
        };

        $events = new LengthAwarePaginator(
            $sorted->values()->forPage($this->getPage(), self::PER_PAGE)->values(),
            $sorted->count(),
            self::PER_PAGE,
            $this->getPage(),
        );

        $favoriteIds = auth()->user()->favoriteEvents()->pluck('events.id')->all();

        $selected = $all->firstWhere('id', $this->selectedId) ?? collect($events->items())->first();

        // "Next up": whatever is live right now, else the nearest upcoming event.
        $today = now()->startOfDay();
        $liveNow = $all->first(fn (Event $e) => $e->starts_at
            && $e->starts_at->copy()->startOfDay()->lte($today)
            && ($e->ends_at ?? $e->starts_at)->copy()->endOfDay()->gte($today));
        $nextUp = $liveNow ?? $all
            ->filter(fn (Event $e) => $e->starts_at && $e->starts_at->copy()->startOfDay()->gte($today))
            ->sortBy('starts_at')->first();

        return view('livewire.events-index', [
            'events' => $events,
            'health' => $health,
            'metrics' => $metrics,
            'favoriteIds' => $favoriteIds,
            'selected' => $selected,
            'nextUp' => $nextUp,
            'nextUpLive' => (bool) $liveNow,
            'nextUpHealth' => $nextUp ? $health[$nextUp->id] : null,
            'nextUpMetrics' => $nextUp ? $metrics[$nextUp->id] : null,
            'selectedHealth' => $selected ? $health[$selected->id] : null,
            'ai' => $selected ? $pulse->aiSummary($selected) : null,
            'calendar' => $this->view === 'calendar' ? $this->buildCalendar($all) : null,
            'expandedId' => $this->expandedId,
            'expanded' => $this->expandedId && ($ex = $all->firstWhere('id', $this->expandedId))
                ? $this->cardDetail($ex, $health[$ex->id])
                : null,
            'kpis' => [
                ['label' => 'Total Events', 'value' => Event::whereNull('archived_at')->count(), 'icon' => 'calendar', 'tone' => 'blue',
                    'trend' => '↑ '.Event::whereNull('archived_at')->whereMonth('created_at', now()->month)->count().' added this month', 'up' => true],
                ['label' => 'Active Events', 'value' => Event::whereNull('archived_at')->whereIn('stage', ['confirmed', 'planning', 'production'])->count(), 'icon' => 'folder', 'tone' => 'green',
                    'trend' => '↑ '.Event::whereNull('archived_at')->where('stage', 'production')->count().' in production', 'up' => true],
                ['label' => 'Live Events', 'value' => Event::whereNull('archived_at')->where('stage', 'live')->count(), 'icon' => 'sparkles', 'tone' => 'gold',
                    'trend' => 'happening today', 'up' => true],
                // Derived from the same computed health the hub shows — not a stored flag.
                ['label' => 'At Risk', 'value' => $this->atRiskCount($health), 'icon' => 'bell', 'tone' => 'red',
                    'trend' => '↓ needs attention', 'up' => false],
                ['label' => 'Open Tasks', 'value' => Task::whereNot('status', 'done')->count(), 'icon' => 'clipboard', 'tone' => 'green',
                    'trend' => '↑ across all events', 'up' => true],
                ['label' => 'Pending Approvals', 'value' => EventApproval::where('status', 'pending')->count(), 'icon' => 'identification', 'tone' => 'gold',
                    'trend' => '↑ awaiting decision', 'up' => true],
            ],
        ]);
    }

    /**
     * Month grid: weeks of days, each day carrying the events that span it.
     */
    private function buildCalendar($all): array
    {
        $month = Carbon::createFromFormat('Y-m', $this->calMonth)->startOfMonth();
        $cursor = $month->copy()->startOfWeek(Carbon::SUNDAY);
        $end = $month->copy()->endOfMonth()->endOfWeek(Carbon::SATURDAY);

        $weeks = [];
        while ($cursor <= $end) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $day = $cursor->copy();
                $week[] = [
                    'date' => $day,
                    'inMonth' => $day->month === $month->month,
                    'events' => $all->filter(fn (Event $event) => $event->starts_at
                        && $day->betweenIncluded($event->starts_at->startOfDay(), ($event->ends_at ?? $event->starts_at)->endOfDay()))->values(),
                ];
                $cursor->addDay();
            }
            $weeks[] = $week;
        }

        return ['label' => $month->format('F Y'), 'weeks' => $weeks];
    }

}
