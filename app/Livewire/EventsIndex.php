<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\EventContractPayment;
use App\Models\PlanItem;
use App\Models\PlanTrack;
use App\Models\Task;
use App\Services\EventHealthService;
use App\Services\EventJourney;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
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

    /** journey · grid · lanes · list · calendar. Journey is where you land. */
    public string $view = 'journey';

    /** Where each stage sits on the board. */
    public const LANES = [
        0 => ['title' => 'Pipeline', 'note' => 'bidding · not committed', 'stages' => ['draft', 'proposal']],
        1 => ['title' => 'In production', 'note' => 'signed · being built', 'stages' => ['confirmed', 'planning', 'production']],
        2 => ['title' => 'Delivering', 'note' => 'live · on the ground', 'stages' => ['live']],
    ];

    public static function laneFor(string $stage): int
    {
        foreach (self::LANES as $key => $lane) {
            if (in_array($stage, $lane['stages'], true)) {
                return $key;
            }
        }

        return 1;
    }

    /** The card opened in place to show its full detail. */
    public ?int $expandedId = null;

    /**
     * Selecting an event opens it in the Inspector. It used to expand the card
     * in place, which shoved every other card down the page.
     */
    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
        $this->selectedId = $this->expandedId ?? $this->selectedId;
    }

    public function closeInspector(): void
    {
        $this->expandedId = null;
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
        // ?view=cards still works — it was the grid's name before the lanes.
        $requested = request('view') === 'cards' ? 'grid' : request('view');
        $this->view = in_array($requested, ['journey', 'grid', 'lanes', 'list', 'calendar'], true) ? $requested : 'journey';
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
        Gate::authorize('manage-events');
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
        Gate::authorize('manage-events');
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
    // ── Bulk selection (List view) ──────────────────────────────

    /** @var array<int,int> event ids ticked for a bulk action */
    public array $selectedIds = [];

    public function toggleSelect(int $id): void
    {
        $this->selectedIds = in_array($id, $this->selectedIds, true)
            ? array_values(array_diff($this->selectedIds, [$id]))
            : [...$this->selectedIds, $id];
    }

    /** Header tick-box: select or clear every row on the page in view. */
    public function toggleSelectPage(array $ids): void
    {
        $ids = array_map('intval', $ids);
        $allOn = $ids !== [] && ! array_diff($ids, $this->selectedIds);

        $this->selectedIds = $allOn
            ? array_values(array_diff($this->selectedIds, $ids))
            : array_values(array_unique([...$this->selectedIds, ...$ids]));
    }

    /** Everything the current filters match, across every page. */
    public function selectAllMatching(): void
    {
        $this->selectedIds = $this->baseQuery()->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    public function clearSelection(): void
    {
        $this->selectedIds = [];
    }

    public function deleteSelected(): void
    {
        Gate::authorize('manage-events');

        $ids = array_map('intval', $this->selectedIds);
        if ($ids === []) {
            return;
        }

        // Delete per model so each event's own cleanup/cascades still run.
        $events = Event::whereIn('id', $ids)->get();
        $n = $events->count();
        $events->each->delete();

        if (in_array($this->selectedId, $ids, true)) {
            $this->selectedId = null;
        }
        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('status', $n.' '.str('event')->plural($n).' permanently deleted.');
    }

    public function deleteEvent(int $eventId): void
    {
        Gate::authorize('manage-events');
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
    private function atRiskCount(Collection $health): int
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

        $planByTrack = PlanItem::where('event_id', $event->id)->get()->groupBy('track_id');
        $tracks = PlanTrack::where('event_id', $event->id)->orderBy('position')->get()
            ->map(function ($t) use ($planByTrack) {
                $items = $planByTrack->get($t->id, collect());
                $total = $items->count();
                $done = $items->where('status', 'done')->count();

                return ['name' => $t->name, 'color' => $t->color ?? '#3B82F6', 'done' => $done,
                    'total' => $total, 'pct' => $total ? (int) round($done / $total * 100) : 0];
            });

        $budget = (int) $event->budget_cents;
        $spent = (int) $event->budgetItems->sum('actual_cents');
        $outstanding = (int) EventContractPayment::where('event_id', $event->id)->get()
            ->sum(fn ($p) => max($p->amount_cents - $p->paid_cents, 0));

        // The next things due — tasks and deliverables together, soonest first.
        $deadlines = collect()
            ->concat($open->filter(fn (Task $t) => $t->due_on)->map(fn (Task $t) => [
                'title' => $t->title, 'due' => $t->due_on, 'kind' => 'Task', 'hex' => $t->stageHex(),
            ]))
            ->concat(PlanItem::where('event_id', $event->id)
                ->whereNotIn('status', PlanItem::CLOSED)->whereNotNull('due_on')->get()
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

    /**
     * The portfolio wall: one payload per event card.
     *
     * Everything the card shows is counted here rather than in the template, so
     * a card cannot say 67% while the bar under it draws something else.
     *
     * @return Collection<int,array>
     */
    private function cards(Collection $events, Collection $health): Collection
    {
        $today = Carbon::today();

        return $events->map(function (Event $event) use ($health, $today) {
            $h = $health[$event->id];

            $live = $event->starts_at
                && $event->starts_at->copy()->startOfDay()->lte($today)
                && ($event->ends_at ?? $event->starts_at)->copy()->endOfDay()->gte($today);

            // One status decides the whole card: the chip, the ring, the tint
            // behind the title and the button at the bottom. A card whose ring
            // is gold and whose chip is red is two opinions about one event.
            //
            //   [label, chip, ring hex, tint, button]
            [$status, $chip, $hex, $tint, $button] = match (true) {
                $live => ['Live', 'bg-gold-400 text-navy-950', 'var(--color-gold-500)',
                    'rgba(212,175,55,0.28)', 'bg-navy-950 text-white hover:bg-navy-800'],
                ! EventHealthService::isScored($event) => ['Planning', 'bg-violet-100 text-violet-700', '#8B5CF6',
                    'rgba(139,92,246,0.16)', 'border border-violet-300 text-violet-700 hover:bg-violet-50'],
                $h['group'] === 'risk' => ['At risk', 'bg-red-100 text-red-700', '#EF4444',
                    'rgba(239,68,68,0.14)', 'border border-red-300 text-red-700 hover:bg-red-50'],
                $h['group'] === 'warn' => ['At watch', 'bg-amber-100 text-amber-700', '#F59E0B',
                    'rgba(245,158,11,0.16)', 'border border-amber-300 text-amber-700 hover:bg-amber-50'],
                default => ['On track', 'bg-emerald-100 text-emerald-700', '#10B981',
                    'rgba(16,185,129,0.14)', 'border border-emerald-300 text-emerald-700 hover:bg-emerald-50'],
            };

            $tasks = $event->tasks->where('status', '!=', 'cancelled');
            $done = $tasks->whereIn('status', ['done', 'approved'])->count();

            $budget = (int) ($event->budget_cents ?: $event->budgetItems->sum('estimate_cents'));
            $spent = (int) $event->budgetItems->sum('actual_cents');

            return [
                'event' => $event,
                'live' => $live,
                'status' => $status,
                'chip' => $chip,
                'hex' => $hex,
                'tint' => $tint,
                'button' => $button,
                // Only the event that is happening gets the dark treatment. It
                // is the one you would look up first in a room of five.
                'dark' => $live,
                'progress' => $h['score'] ?? $event->progress,
                'group' => $h['group'],
                'where' => collect([$event->venue?->name, $event->city])->filter()->implode(', ') ?: 'Venue TBC',
                'when' => $event->starts_at
                    ? $event->starts_at->format('j M').' – '.($event->ends_at?->format('j M Y') ?? $event->starts_at->format('Y'))
                    : 'Dates TBC',
                'stats' => [
                    ['users', $event->attendees->count() ?: (int) $event->expected_participants, 'Participants'],
                    ['sparkles', $event->speakers->count(), 'Speakers'],
                    ['star', $event->sponsors->count(), 'Sponsors'],
                    ['building', $event->rooms->count(), 'Venues'],
                ],
                'budgetPct' => $budget > 0 ? min(100, (int) round($spent / $budget * 100)) : null,
                'budgetLine' => $budget > 0
                    ? self::shortMoney($spent, $event->currency).' / '.self::shortMoney($budget, $event->currency)
                    : 'No budget set',
                'tasksDone' => $done,
                'tasksTotal' => $tasks->count(),
                'tasksPct' => $tasks->count() ? (int) round($done / $tasks->count() * 100) : null,
                'risks' => $event->risks->filter->isOpen()->count(),
                'health' => $h['score'] === null ? 'Not scored'
                    : match ($h['group']) { 'risk' => 'At risk', 'warn' => 'At watch', default => 'Healthy' },
                'team' => $event->teamMembers->take(3),
                'teamMore' => max(0, $event->teamMembers->count() - 3),
            ];
        })->values();
    }

    /**
     * Every event on one month scale, so a clash of two builds in the same week
     * is something you see rather than something you work out.
     *
     * @return array{months:array,rows:array}|null
     */
    private function timeline(Collection $events): ?array
    {
        $dated = $events->filter->starts_at;

        if ($dated->isEmpty()) {
            return null;
        }

        $from = $dated->min(fn (Event $e) => $e->starts_at)->copy()->startOfMonth();
        $to = $dated->max(fn (Event $e) => ($e->ends_at ?? $e->starts_at))->copy()->endOfMonth();

        // A one-month window is a bar with nothing to compare it to.
        if ($from->diffInMonths($to) < 2) {
            $to = $from->copy()->addMonths(2)->endOfMonth();
        }

        $span = max(1, $from->diffInDays($to));
        $months = [];
        for ($m = $from->copy(); $m <= $to; $m->addMonth()) {
            $months[] = [
                'label' => $m->format('M Y'),
                'left' => round($from->diffInDays($m) / $span * 100, 3),
            ];
        }

        $rows = $dated->sortBy('starts_at')->map(function (Event $e) use ($from, $span) {
            $start = $e->starts_at->copy()->startOfDay();
            $end = ($e->ends_at ?? $e->starts_at)->copy()->endOfDay();

            return [
                'event' => $e,
                'left' => round($from->diffInDays($start) / $span * 100, 3),
                // A one-day event still needs to be clickable, hence the floor.
                'width' => max(2.2, round($start->diffInDays($end) / $span * 100, 3)),
                'label' => $start->format('j M').($end->isSameDay($start) ? '' : ' – '.$end->format('j M')),
                'hex' => \App\Support\Workflow::color('event_stage', $e->stage) ?: '#3B82F6',
            ];
        })->values()->all();

        return ['months' => $months, 'rows' => $rows];
    }

    /**
     * Today's actions: what is dated today or already late, across the book.
     * Approvals first — somebody else is blocked until one is decided.
     */
    private function todayActions(Collection $events): Collection
    {
        $today = Carbon::today();
        $ids = $events->pluck('id')->all();

        $approvals = EventApproval::whereIn('event_id', $ids)->where('status', 'pending')->with('event')
            ->orderBy('created_at')->get()
            ->map(fn (EventApproval $a) => [
                'title' => $a->title,
                'where' => $a->event?->name ?? 'Unassigned',
                'when' => $a->created_at->diffForHumans(null, true).' waiting',
                'late' => $a->created_at->lt($today->copy()->subDays(3)),
                'icon' => 'identification',
                'href' => $a->event ? route('events.hub', [$a->event, 'tab' => 'approvals']) : null,
            ]);

        $tasks = Task::whereIn('event_id', $ids)
            ->whereNotIn('status', ['done', 'approved', 'cancelled'])
            ->whereNotNull('due_on')->whereDate('due_on', '<=', $today)
            ->with('event')->orderBy('due_on')->get()
            ->map(fn (Task $t) => [
                'title' => $t->title,
                'where' => $t->event?->name ?? 'No event',
                'when' => $t->due_on->isToday() ? 'Due today' : (int) $t->due_on->diffInDays($today).'d overdue',
                'late' => $t->due_on->isPast() && ! $t->due_on->isToday(),
                'icon' => 'clipboard',
                'href' => $t->event ? route('events.hub', [$t->event, 'tab' => 'tasks']) : null,
            ]);

        return $approvals->concat($tasks)->take(6)->values();
    }

    /**
     * The next thirty days, in the order they land.
     */
    private function upcoming(Collection $events): Collection
    {
        $today = Carbon::today();
        $horizon = $today->copy()->addDays(30);

        return $events
            ->filter(fn (Event $e) => $e->starts_at
                && ($e->ends_at ?? $e->starts_at)->copy()->endOfDay()->gte($today)
                && $e->starts_at->copy()->startOfDay()->lte($horizon))
            ->sortBy('starts_at')
            ->map(fn (Event $e) => [
                'event' => $e,
                'when' => $e->starts_at->format('j M').($e->ends_at && ! $e->ends_at->isSameDay($e->starts_at) ? ' – '.$e->ends_at->format('j M') : ''),
                'where' => $e->venue?->name ?? $e->city ?? '—',
                'live' => $e->starts_at->copy()->startOfDay()->lte($today),
            ])
            ->take(6)->values();
    }

    /**
     * The book split by how each event is doing, for the donut.
     *
     * Unscored events are their own slice rather than folded into "on track" —
     * nothing is committed at proposal stage, so there is nothing to be on
     * track with, and counting them as healthy is the lie this avoids.
     */
    private function healthSplit(Collection $events, Collection $health): array
    {
        $rows = [
            ['key' => 'ok', 'label' => 'On track', 'hex' => '#10B981', 'count' => 0],
            ['key' => 'warn', 'label' => 'At watch', 'hex' => '#F59E0B', 'count' => 0],
            ['key' => 'risk', 'label' => 'At risk', 'hex' => '#EF4444', 'count' => 0],
            ['key' => 'planning', 'label' => 'Planning', 'hex' => '#94A3B8', 'count' => 0],
        ];

        foreach ($events as $event) {
            $key = EventHealthService::isScored($event)
                ? match ($health[$event->id]['group']) { 'risk' => 'risk', 'warn' => 'warn', default => 'ok' }
                : 'planning';

            foreach ($rows as $i => $row) {
                if ($row['key'] === $key) {
                    $rows[$i]['count']++;
                }
            }
        }

        return ['total' => $events->count(), 'rows' => $rows];
    }

    /** Short money, because a card has no room for 350,000.00. */
    public static function shortMoney(int $cents, ?string $currency): string
    {
        $symbol = Event::CURRENCIES[$currency ?? 'JOD'][0] ?? '';
        $value = $cents / 100;

        return $symbol.match (true) {
            abs($value) >= 1_000_000 => round($value / 1_000_000, 1).'M',
            abs($value) >= 1_000 => round($value / 1_000).'K',
            default => number_format($value),
        };
    }

    /**
     * The lifecycle board: a card's worth of facts, plus where the event is in
     * its own life. Reuses the wall's payload so a row and a card can never
     * disagree about the same event.
     */
    private function journeyRows(Collection $events, Collection $health): Collection
    {
        $journey = app(EventJourney::class);

        return $this->cards($events, $health)
            ->map(fn (array $card) => $card + ['track' => $journey->for($card['event'])]);
    }

    /**
     * The second line under a figure.
     *
     * Where a record carries a date we can compare two windows and say which
     * way it is going. Where it does not, the slot says something true rather
     * than a trend that was never measured.
     */
    private function trend(string $model, string $column = 'created_at'): ?array
    {
        $now = Carbon::today();
        $recent = $model::whereBetween($column, [$now->copy()->subDays(30), $now->copy()->endOfDay()])->count();
        $prior = $model::whereBetween($column, [$now->copy()->subDays(60), $now->copy()->subDays(30)])->count();

        if ($prior === 0) {
            return $recent > 0 ? ['up' => true, 'label' => '+'.$recent.' in 30d'] : null;
        }

        $delta = (int) round(($recent - $prior) / $prior * 100);

        return ['up' => $delta >= 0, 'label' => ($delta >= 0 ? '↑ ' : '↓ ').abs($delta).'%'];
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
            'venue', 'client', 'projectManager', 'tasks',
            'budgetItems', 'suppliers', 'rooms', 'agendaSessions', 'risks', 'approvals', 'sponsors', 'teamMembers',
            // The wall's cards count people and speakers, not just tasks.
            'attendees', 'speakers',
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
            // Unscored events sort last rather than as a zero.
            'health' => $all->sortByDesc(fn (Event $event) => $health[$event->id]['score'] ?? -1),
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
            // The board shows every matching event, not a page of them: lanes
            // only mean something when the whole pipeline is on screen.
            'lanes' => $this->view === 'lanes'
                ? collect(self::LANES)->map(fn ($lane, $key) => $lane + [
                    'key' => $key,
                    'events' => $sorted->filter(fn (Event $e) => self::laneFor($e->stage) === $key)->values(),
                ])->values()
                : null,
            'expandedId' => $this->expandedId,
            'expanded' => $this->expandedId && ($ex = $all->firstWhere('id', $this->expandedId))
                ? $this->cardDetail($ex, $health[$ex->id])
                : null,
            // The grid shows every matching event: a wall of five cards paged
            // at eight is a page-two nobody reaches.
            'cards' => $this->view === 'grid' ? $this->cards($sorted->values(), $health) : null,
            'journey' => $this->view === 'journey' ? $this->journeyRows($sorted->values(), $health) : null,
            'phases' => EventJourney::PHASES,
            'regions' => $this->view === 'journey' ? app(EventJourney::class)->regions($all) : null,
            'timeline' => in_array($this->view, ['grid', 'journey'], true) ? $this->timeline($all) : null,
            'todayActions' => $this->todayActions($all),
            'upcoming' => $this->upcoming($all),
            'healthSplit' => $this->healthSplit($all, $health),
            'kpis' => [
                ['label' => 'Total Events', 'note' => 'All time', 'icon' => 'calendar', 'tone' => 'navy',
                    'value' => Event::whereNull('archived_at')->count(), 'trend' => null],
                ['label' => 'Active Events', 'note' => 'Running now', 'icon' => 'folder', 'tone' => 'green',
                    'value' => Event::whereNull('archived_at')->whereIn('stage', ['confirmed', 'planning', 'production', 'live'])->count(),
                    'trend' => $this->trend(Event::class)],
                ['label' => 'Open Tasks', 'note' => 'Across all events', 'icon' => 'clipboard', 'tone' => 'blue',
                    'value' => Task::whereNotIn('status', ['done', 'approved', 'cancelled'])->count(),
                    'trend' => $this->trend(Task::class)],
                // Computed health, not a stored flag — the same number the hub shows.
                ['label' => 'Events at Risk', 'note' => 'Needs attention', 'icon' => 'bell', 'tone' => 'red',
                    'value' => $this->atRiskCount($health),
                    'trend' => ($behind = $health->filter(fn ($h) => $h['status'] === 'behind')->count())
                        ? ['up' => false, 'label' => $behind.' behind'] : null],
                ['label' => 'Pending Approvals', 'note' => 'Awaiting your review', 'icon' => 'identification', 'tone' => 'gold',
                    'value' => EventApproval::where('status', 'pending')->count(),
                    'trend' => ($oldest = EventApproval::where('status', 'pending')->min('created_at'))
                        ? ['up' => false, 'label' => 'oldest '.(int) Carbon::parse($oldest)->diffInDays(now()).'d'] : null],
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
