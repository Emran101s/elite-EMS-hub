<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\EventContractPayment;
use App\Services\EventCommandHeader;
use App\Services\EventHealthService;
use App\Services\EventMission;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'Events', 'hideTitleRow' => true])]
class EventsIndex extends Component
{
    use WithPagination;

    /**
     * Filter tabs → event type groups.
     *
     * Split finer in Phase C: Summit, Corporate and Awards used to ride
     * inside Conference/Exhibition/Gala respectively, which was fine while
     * the only Type chip in the room was "Filters ▾" but reads as a bug once
     * they're named chips of their own. vip/outdoor stay registered — not
     * shown as Type chips any more (VIP moved to Smart Views), but a
     * ?tab=vip or ?tab=outdoor link from before Phase C still has to resolve
     * to something real rather than silently reset to "all".
     */
    public const TYPE_TABS = [
        'all' => null,
        'conference' => ['conference', 'hybrid_event', 'online_event'],
        'summit' => ['summit'],
        'exhibition' => ['exhibition', 'career_fair'],
        'workshop' => ['workshop', 'training_program'],
        'gala' => ['gala_dinner'],
        'corporate' => ['product_launch'],
        'awards' => ['awards_ceremony'],
        'vip' => ['vip_reception', 'embassy_event', 'private_dinner'],
        'outdoor' => ['outdoor_event', 'public_event'],
    ];

    public string $q = '';

    public string $tab = 'all';

    /** Rows per page, List View only — the deck and the path show everything. */
    public int $perPage = 10;

    public function setPerPage(int $n): void
    {
        $this->perPage = in_array($n, [10, 25, 50], true) ? $n : 10;
        $this->resetPage();
    }

    /**
     * The portfolio's four views, one workspace shell. Each shares the same
     * payload (EventMission), the same status vocabulary and the same
     * selected-event detail panel, so what changes between them is the
     * arrangement, never the facts.
     *
     *   board      Mission Board — the default. Grouped by what needs you first.
     *   path       Timeline — where each mission sits across the year.
     *   list       Table — every mission, dense and scannable.
     *   calendar   Month/week view. Not built yet — selectable, and the
     *              workspace says so honestly rather than pretending.
     *
     * The spatial Deck (one-at-a-time, drag-to-step) and Mission Radar are
     * both retired as live views — see mount()'s ?view= mapping, which
     * resolves every old ?view=deck or ?view=radar link straight to
     * Mission Board instead of rendering them.
     */
    public const VIEWS = ['board', 'path', 'list', 'calendar'];

    public string $view = 'board';

    /** The mission in the centre of the deck / open in the detail panel. */
    public ?int $activeId = null;

    public string $sort = 'date';

    public bool $starred = false;

    public ?string $stage = null;

    /**
     * Operational Smart View queues from the context sidebar.
     * at_risk | awaiting_approval | payment | this_week | high_value
     */
    public ?string $queue = null;

    /** Exact-type filter from ?type= (deep links keep working). */
    public ?string $exactType = null;

    /** The Archived Events core link — same portfolio, the other pile. */
    public bool $archived = false;

    public function mount(): void
    {
        $this->q = (string) request('q', '');
        $this->stage = request('stage') ?: null;
        $this->exactType = request('type') ?: null;
        $this->queue = request('queue') ?: null;
        $this->archived = request()->boolean('archived');
        $this->starred = request()->boolean('starred');
        $reqTab = (string) request('tab', 'all');
        if (array_key_exists($reqTab, self::TYPE_TABS)) {
            $this->tab = $reqTab;
        }
        $reqSort = (string) request('sort', 'date');
        if (in_array($reqSort, ['date', 'health', 'budget'], true)) {
            $this->sort = $reqSort;
        }
        // Every retired or renamed view name lands on the real view it means
        // now, rather than a blank page or a silent reset to Mission Board —
        // deck and radar included: both are retired, so a bookmarked
        // ?view=deck or ?view=radar now opens Mission Board instead of
        // rendering them.
        $this->view = match (request('view')) {
            'deck', 'board', 'mission-board', 'radar' => 'board',
            'path', 'flight-path', 'timeline' => 'path',
            'list', 'table' => 'list',
            'calendar' => 'calendar',
            default => 'board',
        };
        $this->activeId = request()->integer('selected') ?: request()->integer('active') ?: null;
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
        $this->authorize('duplicate', Event::class);
        $source = Event::whereNull('archived_at')->findOrFail($eventId);

        $copy = $source->replicate(['progress', 'registration_token', 'checkin_token']);
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
        $this->authorize('archive', Event::class);
        Event::findOrFail($eventId)->update(['archived_at' => now()]);

        if ($this->activeId === $eventId) {
            $this->activeId = null;
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
        $this->authorize('delete', Event::class);

        $ids = array_map('intval', $this->selectedIds);
        if ($ids === []) {
            return;
        }

        // Delete per model so each event's own cleanup/cascades still run.
        $events = Event::whereIn('id', $ids)->get();
        $n = $events->count();
        $events->each->delete();

        if (in_array($this->activeId, $ids, true)) {
            $this->activeId = null;
        }
        $this->selectedIds = [];
        $this->resetPage();

        session()->flash('status', $n.' '.str('event')->plural($n).' permanently deleted.');
    }

    public function deleteEvent(int $eventId): void
    {
        $this->authorize('delete', Event::class);
        $event = Event::findOrFail($eventId);
        $name = $event->name;
        $event->delete();

        if ($this->activeId === $eventId) {
            $this->activeId = null;
        }

        session()->flash('status', "“{$name}” was permanently deleted.");
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

    /** Select a mission — the one the detail panel shows, in every view. */
    public function activate(int $eventId): void
    {
        $this->activeId = $eventId;
    }

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->view = $view;
        }
    }

    private function baseQuery()
    {
        return Event::query()
            ->when($this->archived, fn ($query) => $query->whereNotNull('archived_at'), fn ($query) => $query->whereNull('archived_at'))
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
        $missions = app(EventMission::class);

        // Board and Path show the whole filtered book by design — they
        // cannot page, so they still need every matching event's full
        // mission data and always have. List is the one view with page
        // controls, and it's the one place the audit's "cosmetic
        // pagination" finding is real: every matching event was
        // mission-mapped through EventMission::for() — the expensive part,
        // narrative strings and per-event tallies across 16 relations —
        // even though only $perPage of them are ever shown.
        //
        // The eager-load itself is NOT split by view: EventMission::RELATIONS
        // and EventHealthService::RELATIONS name several of the same
        // relations differently (tasks vs tasks.assignee, risks vs
        // risks.owner, transport.manifest vs transport), so loading them in
        // two separate passes was measured to cost MORE queries than one
        // merged pass — not a small-dataset artifact, either: Laravel's
        // eager-loading is one query per relation regardless of row count,
        // so a split pays a fixed ~11-query tax at any scale it never earns
        // back in query count. What genuinely scales with row count instead
        // — and is what "maps every row into a mission" in the audit
        // actually costs — is running for() on every matching event. That
        // part is scoped to the current page below.
        $paginating = $this->view === 'list';

        $all = $this->baseQuery()
            ->with(array_merge(EventMission::RELATIONS, EventHealthService::RELATIONS))
            ->get();

        $health = $all->mapWithKeys(fn (Event $event) => [$event->id => $pulse->breakdown($event)]);

        // Smart View queues (context sidebar) — filter the book without new routes.
        if ($this->queue === 'at_risk') {
            $all = $all->filter(fn (Event $e) => in_array($health[$e->id]['status'] ?? '', ['at_risk', 'behind'], true))->values();
        } elseif ($this->queue === 'awaiting_approval') {
            $ids = EventApproval::where('status', 'pending')->pluck('event_id');
            $all = $all->whereIn('id', $ids)->values();
        } elseif ($this->queue === 'payment') {
            // Status is derived — outstanding = paid less than due.
            $ids = EventContractPayment::query()
                ->whereColumn('paid_cents', '<', 'amount_cents')
                ->pluck('event_id');
            $all = $all->whereIn('id', $ids)->values();
        } elseif ($this->queue === 'this_week') {
            $all = $all->filter(fn (Event $e) => $e->starts_at && $e->starts_at->between(now()->startOfWeek(), now()->endOfWeek()))->values();
        } elseif ($this->queue === 'high_value') {
            $all = $all->filter(fn (Event $e) => (int) ($e->budget_cents ?? 0) >= 10_000_000)->values();
        }

        if ($this->queue) {
            $health = $all->mapWithKeys(fn (Event $event) => [$event->id => $health[$event->id] ?? $pulse->breakdown($event)]);
        }

        $sorted = (match ($this->sort) {
            'health' => $all->sortByDesc(fn (Event $e) => $health[$e->id]['score'] ?? -1),
            'budget' => $all->sortByDesc(fn (Event $e) => (int) $e->budgetItems->sum('actual_cents')),
            default => $all->sortBy('starts_at'),
        })->values();

        if ($paginating) {
            // Real pagination of the expensive part: every matching event's
            // relations are already loaded above (one merged query, same as
            // any other view), but EventMission::for() — narrative strings,
            // per-event tallies, lane matching — now runs only for the
            // events on this page, not the whole matching book.
            $pageEvents = $sorted->forPage($this->getPage(), $this->perPage)->values();
            $deck = $missions->all($pageEvents);

            $rows = new LengthAwarePaginator($deck, $sorted->count(), $this->perPage, $this->getPage());

            // Cheap, relation-free status per event (see EventMission::statusFor)
            // for the header's whole-book Active/Upcoming counts below — the
            // one thing on this page that still has to know about every
            // matching event, not just the current page of them.
            $statuses = $sorted->mapWithKeys(fn (Event $e) => [$e->id => EventMission::statusFor($e)]);
            $activeCount = $statuses->filter(fn ($s) => $s === 'progress')->count();
            $upcomingCount = $statuses->filter(fn ($s) => $s === 'upcoming')->count();

            $board = null;
            $lanes = null;
            $months = null;

            // The selected mission may not be on the current page — a deep
            // link (?selected=X&page=2) still has to resolve. The event and
            // its relations are already loaded in $sorted regardless, so
            // this costs one extra for() call, not another query.
            $active = null;
            if ($this->activeId) {
                $active = $deck->firstWhere('id', $this->activeId);
                if (! $active) {
                    $activeEvent = $sorted->firstWhere('id', $this->activeId);
                    $active = $activeEvent ? $missions->for($activeEvent) : null;
                }
            }
        } else {
            // One description per event, shared by every view. A card and
            // the row beneath it cannot disagree if neither of them did
            // the counting.
            $deck = $missions->all($sorted);

            $board = $this->view === 'board' ? $this->boardGroups($deck) : null;
            $lanes = $this->view === 'path' ? $this->lanes($deck) : null;
            $months = $this->view === 'path' ? $this->months($sorted) : null;

            // Real readiness gates per event, for the Hub Grid's module
            // honeycomb. The deck's events are already eager-loaded with every
            // relation readiness() reads (EventMission::RELATIONS), so this
            // adds no queries — one hub, its modules, and which are ready.
            if ($this->view === 'board') {
                $headerService = app(EventCommandHeader::class);
                $gates = $deck->mapWithKeys(fn ($m) => [
                    $m['id'] => ($m['event'] ?? null) ? $headerService->readiness($m['event']) : null,
                ]);
            }

            $active = $this->activeId ? $deck->firstWhere('id', $this->activeId) : null;

            // Board and Path show the whole book (see the note on
            // $paginating above), so $rows here is a full-page-1 view of
            // the same $deck every other output already reflects — nothing
            // reads its page controls outside the List branch, but it is
            // still built the same way in case anything comes to.
            $rows = new LengthAwarePaginator(
                $deck->forPage($this->getPage(), $this->perPage)->values(),
                $deck->count(),
                $this->perPage,
                $this->getPage(),
            );

            $activeCount = $deck->where('status', 'progress')->count();
            $upcomingCount = $deck->where('status', 'upcoming')->count();
        }

        // Portfolio health: the average of every mission that has actually
        // been scored. An event still in draft with nothing to score yet
        // does not count as either healthy or at risk — it just doesn't
        // count, the same way it's excluded from every other health read
        // in the platform.
        $scored = $health->filter(fn ($h) => $h['score'] !== null);
        $portfolioHealth = $scored->isNotEmpty() ? (int) round($scored->avg('score')) : null;

        return view('livewire.events-index', [
            'deck' => $deck,
            'board' => $board,
            'gates' => $gates ?? collect(),
            'rows' => $rows,
            // Kept under its old name for anything that reads the paginator.
            'events' => $rows,
            'active' => $active,

            // Flight Path: lanes down, months across, cards placed by date.
            'lanes' => $lanes,
            'months' => $months,

            'favoriteIds' => auth()->user()->favoriteEvents()->pluck('events.id')->all(),
            'statuses' => EventMission::STATUSES,

            // The header's four figures — portfolio health, then the three
            // counts that say what's actually happening right now. Kept to
            // exactly these so the header reads at a glance rather than
            // repeating what the filter bar and the board already show.
            'figures' => [
                ['label' => 'Portfolio health', 'note' => $scored->isNotEmpty() ? $scored->count().' scored '.str('mission')->plural($scored->count()) : 'Nothing scored yet',
                    'value' => $portfolioHealth !== null ? $portfolioHealth.'%' : '—', 'href' => null],
                ['label' => 'Active', 'note' => 'Running now',
                    'value' => $activeCount, 'href' => null],
                ['label' => 'At risk', 'note' => 'Needs attention',
                    'value' => $this->atRiskCount($health), 'href' => route('events.index', ['queue' => 'at_risk', 'sort' => 'health'])],
                ['label' => 'Upcoming', 'note' => 'Not live yet',
                    'value' => $upcomingCount, 'href' => null],
            ],
        ]);
    }

    /**
     * Mission Board's four groups. Existing facts only — every signal here
     * (health, pending approvals, outstanding payments, next-action urgency)
     * is already computed elsewhere; this just sorts one mission into one
     * bucket by what's true about it right now.
     *
     * Priority order matters: an event can be both "live" and "at risk," and
     * it belongs in Needs Attention when that happens — the board is a
     * worklist, and a live-but-burning mission is not a status update, it's
     * the thing to open first.
     *
     * @return array<string,array{label:string,missions:Collection}>
     */
    private function boardGroups(Collection $deck): array
    {
        $awaitingApprovalIds = EventApproval::where('status', 'pending')->pluck('event_id')->all();
        $paymentDueIds = EventContractPayment::query()
            ->whereColumn('paid_cents', '<', 'amount_cents')
            ->pluck('event_id')->all();

        $groups = ['needs_attention' => collect(), 'live' => collect(), 'upcoming' => collect(), 'recent' => collect()];

        foreach ($deck as $m) {
            $needsAttention = ! ($m['past'] ?? false) && (
                ($m['healthGroup'] ?? null) === 'risk'
                || in_array($m['id'], $awaitingApprovalIds, true)
                || in_array($m['id'], $paymentDueIds, true)
                || ($m['milestone']['tone'] ?? null) === 'risk'
            );

            $key = match (true) {
                $needsAttention => 'needs_attention',
                ($m['live'] ?? false) || ($m['status'] ?? null) === 'progress' => 'live',
                // Recently completed: closed out, and inside the window —
                // an event that wrapped up six months ago is portfolio
                // history, not something the board is still tracking.
                ($m['past'] ?? false) && $m['event']->ends_at
                    && $m['event']->ends_at->gte(now()->subDays(30)) => 'recent',
                $m['past'] ?? false => null, // older closeouts: not on the board at all
                default => 'upcoming',
            };

            if ($key) {
                $groups[$key]->push($m);
            }
        }

        return [
            'needs_attention' => ['label' => 'Needs Attention', 'missions' => $groups['needs_attention']],
            'live' => ['label' => 'Live / Active', 'missions' => $groups['live']],
            'upcoming' => ['label' => 'Upcoming', 'missions' => $groups['upcoming']],
            'recent' => ['label' => 'Recently Completed', 'missions' => $groups['recent']],
        ];
    }

    /**
     * Flight Path lanes: a lane per category, dropped when it is empty. A row
     * of nothing is a row you have to read to dismiss.
     *
     * @return Collection<int,array{key:string,label:string,icon:string,missions:Collection}>
     */
    private function lanes(Collection $deck): Collection
    {
        return collect(EventMission::LANES)
            ->map(fn (array $lane, string $key) => [
                'key' => $key,
                'label' => $lane[0],
                'icon' => $lane[1],
                'missions' => $deck->where('lane', $key)->values(),
            ])
            ->filter(fn (array $lane) => $lane['missions']->isNotEmpty())
            ->values();
    }

    /**
     * The months the Flight Path spans, and where today sits on them.
     *
     * @return array{list:array,todayLeft:?float,label:string}|null
     */
    private function months(Collection $events): ?array
    {
        $dated = $events->filter->starts_at;

        if ($dated->isEmpty()) {
            return null;
        }

        $from = $dated->min(fn (Event $e) => $e->starts_at)->copy()->startOfMonth();
        $to = $dated->max(fn (Event $e) => $e->ends_at ?? $e->starts_at)->copy()->endOfMonth();

        // A canvas narrower than three months has nothing to compare across.
        if ($from->diffInMonths($to) < 3) {
            $to = $from->copy()->addMonths(3)->endOfMonth();
        }

        $span = max(1, $from->diffInDays($to));
        $months = [];

        for ($m = $from->copy(); $m <= $to; $m->addMonth()) {
            $months[] = [
                'label' => $m->format('M'),
                'year' => $m->format('Y'),
                'left' => round($from->diffInDays($m) / $span * 100, 3),
                'width' => round($m->daysInMonth / $span * 100, 3),
                'current' => $m->isSameMonth(Carbon::today()),
            ];
        }

        $today = Carbon::today();

        return [
            'list' => $months,
            'from' => $from,
            'to' => $to,
            'span' => $span,
            'label' => $from->format('M Y').' — '.$to->format('M Y'),
            'todayLeft' => $today->between($from, $to)
                ? round($from->diffInDays($today) / $span * 100, 3)
                : null,
        ];
    }
}
