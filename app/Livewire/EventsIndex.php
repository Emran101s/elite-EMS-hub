<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\EventContractPayment;
use App\Models\Task;
use App\Services\EventHealthService;
use App\Services\EventJourney;
use App\Services\EventMission;
use Carbon\Carbon;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'Events', 'hideTitleRow' => true])]
class EventsIndex extends Component
{
    use WithPagination;

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

    /** Rows per page, List View only — the deck and the path show everything. */
    public int $perPage = 10;

    public function setPerPage(int $n): void
    {
        $this->perPage = in_array($n, [10, 25, 50], true) ? $n : 10;
        $this->resetPage();
    }

    /**
     * The portfolio has three views and no more:
     *
     *   deck   premium portfolio browsing — one mission at a time
     *   list   operational management — every mission, dense and scannable
     *   path   strategic timeline — where each mission sits in the year
     *
     * They are three ways of looking at one thing, so they share a payload
     * (EventMission), a status vocabulary and a detail panel. What changes
     * between them is the arrangement, never the facts.
     */
    public const VIEWS = ['deck', 'list', 'path'];

    public string $view = 'deck';

    /** The mission in the centre of the deck / open in the detail panel. */
    public ?int $activeId = null;

    public string $sort = 'date';

    public bool $starred = false;

    public ?string $stage = null;

    /** Exact-type filter from ?type= (deep links keep working). */
    public ?string $exactType = null;

    public function mount(): void
    {
        $this->q = (string) request('q', '');
        $this->stage = request('stage') ?: null;
        $this->exactType = request('type') ?: null;
        // Every retired view name lands on the nearest survivor rather than a
        // blank page: the deck replaced the grid and the cards, the path
        // replaced the calendar and the timeline.
        $this->view = match (request('view')) {
            'list' => 'list',
            'path', 'calendar', 'timeline', 'flight-path' => 'path',
            default => 'deck',
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
        Gate::authorize('manage-events');

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
        Gate::authorize('manage-events');
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

    /** Put a mission in the centre of the deck / open it in the detail panel. */
    public function activate(int $eventId): void
    {
        $this->activeId = $eventId;
    }

    /**
     * Step the deck. The order is the order on screen, so "next" means the card
     * to the right of the one you are looking at, not the next database row.
     */
    public function step(int $direction): void
    {
        $ids = $this->orderedIds();

        if ($ids->isEmpty()) {
            return;
        }

        $at = $ids->search($this->activeId);
        $at = $at === false ? 0 : $at + $direction;

        $this->activeId = $ids[max(0, min($ids->count() - 1, $at))];
    }

    public function setView(string $view): void
    {
        if (in_array($view, self::VIEWS, true)) {
            $this->view = $view;
        }
    }

    /** The missions in screen order, which is the order the deck steps through. */
    private function orderedIds(): Collection
    {
        return $this->baseQuery()->orderBy('starts_at')->pluck('id');
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
        $missions = app(EventMission::class);

        $all = $this->baseQuery()
            ->with(array_merge(EventMission::RELATIONS, EventHealthService::RELATIONS))
            ->get();

        $health = $all->mapWithKeys(fn (Event $event) => [$event->id => $pulse->breakdown($event)]);

        $sorted = (match ($this->sort) {
            'health' => $all->sortByDesc(fn (Event $e) => $health[$e->id]['score'] ?? -1),
            'budget' => $all->sortByDesc(fn (Event $e) => (int) $e->budgetItems->sum('actual_cents')),
            default => $all->sortBy('starts_at'),
        })->values();

        // One description per event, shared by all three views. A card and the
        // row beneath it cannot disagree if neither of them did the counting.
        $deck = $missions->all($sorted);

        // The active mission: what you picked, else what is on the floor, else
        // the next one in. Never nothing, while there is anything.
        $active = $deck->firstWhere('id', $this->activeId)
            ?? $deck->first(fn (array $m) => $m['live'])
            ?? $deck->first(fn (array $m) => ($m['daysOut'] ?? -1) >= 0)
            ?? $deck->first();

        $at = $active ? $deck->search(fn (array $m) => $m['id'] === $active['id']) : null;

        // The List paginates because it is the operational view and a hundred
        // rows is a scroll; the deck and the path show the whole book, which is
        // the point of both.
        $rows = new LengthAwarePaginator(
            $deck->forPage($this->getPage(), $this->perPage)->values(),
            $deck->count(),
            $this->perPage,
            $this->getPage(),
        );

        return view('livewire.events-index', [
            'deck' => $deck,
            'rows' => $rows,
            // Kept under its old name for anything that reads the paginator.
            'events' => $rows,
            'active' => $active,
            'activeAt' => $at,
            'past' => $at === null ? collect() : $deck->slice(max(0, $at - 2), min(2, $at))->values(),
            'future' => $at === null ? collect() : $deck->slice($at + 1, 2)->values(),

            // Flight Path: lanes down, months across, cards placed by date.
            'lanes' => $this->view === 'path' ? $this->lanes($deck) : null,
            'months' => $this->view === 'path' ? $this->months($sorted) : null,

            'favoriteIds' => auth()->user()->favoriteEvents()->pluck('events.id')->all(),
            'statuses' => EventMission::STATUSES,

            'figures' => [
                ['label' => 'In the book', 'note' => 'Not archived', 'icon' => 'calendar', 'tone' => 'navy',
                    'value' => Event::whereNull('archived_at')->count(), 'trend' => null],
                ['label' => 'In progress', 'note' => 'Running now', 'icon' => 'sparkles', 'tone' => 'blue',
                    'value' => $deck->where('status', 'progress')->count(),
                    'trend' => $this->trend(Event::class)],
                ['label' => 'Open tasks', 'note' => 'Across all events', 'icon' => 'clipboard', 'tone' => 'green',
                    'value' => Task::whereNotIn('status', ['done', 'approved', 'cancelled'])->count(),
                    'trend' => $this->trend(Task::class)],
                ['label' => 'At risk', 'note' => 'Needs attention', 'icon' => 'bell', 'tone' => 'red',
                    'value' => $this->atRiskCount($health),
                    'trend' => ($behind = $health->filter(fn ($h) => $h['status'] === 'behind')->count())
                        ? ['up' => false, 'label' => $behind.' behind'] : null],
                ['label' => 'Pending approvals', 'note' => 'Awaiting your review', 'icon' => 'identification', 'tone' => 'gold',
                    'value' => EventApproval::where('status', 'pending')->count(),
                    'trend' => ($oldest = EventApproval::where('status', 'pending')->min('created_at'))
                        ? ['up' => false, 'label' => 'oldest '.(int) Carbon::parse($oldest)->diffInDays(now()).'d'] : null],
            ],
        ]);
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
