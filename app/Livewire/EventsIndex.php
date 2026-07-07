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

    public string $view = 'grid';

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
        $this->view = in_array(request('view'), ['grid', 'list', 'calendar', 'kanban'], true) ? request('view') : 'grid';
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

    /** Pipeline stage buckets: label, which stages fall in, the canonical stage to set, colors. */
    public const PIPELINE = [
        'lead' => ['label' => 'Lead', 'stages' => ['draft'], 'set' => 'draft', 'dot' => 'bg-navy-300', 'accent' => 'navy', 'next' => 'proposal', 'nextLabel' => 'Proposal'],
        'proposal' => ['label' => 'Proposal', 'stages' => ['proposal'], 'set' => 'proposal', 'dot' => 'bg-gold-500', 'accent' => 'gold', 'next' => 'confirmed', 'nextLabel' => 'Confirmed'],
        'confirmed' => ['label' => 'Confirmed', 'stages' => ['confirmed', 'planning'], 'set' => 'confirmed', 'dot' => 'bg-track', 'accent' => 'track', 'next' => 'delivery', 'nextLabel' => 'In delivery'],
        'delivery' => ['label' => 'In delivery', 'stages' => ['production', 'live'], 'set' => 'production', 'dot' => 'bg-[#3B82F6]', 'accent' => 'blue', 'next' => 'completed', 'nextLabel' => 'Completed'],
        'completed' => ['label' => 'Completed', 'stages' => ['completed', 'closed'], 'set' => 'completed', 'dot' => 'bg-navy-900', 'accent' => 'ink', 'next' => null, 'nextLabel' => null],
    ];

    /** Move an event into a pipeline bucket (drag drop or the Move button). */
    public function moveStage(int $eventId, string $bucket): void
    {
        if (! isset(self::PIPELINE[$bucket])) {
            return;
        }

        $event = Event::whereNull('archived_at')->find($eventId);
        $event?->update(['stage' => self::PIPELINE[$bucket]['set']]);
    }

    public function duplicate(int $eventId)
    {
        $source = Event::whereNull('archived_at')->findOrFail($eventId);

        $copy = $source->replicate(['progress']);
        $copy->name = $source->name.' (Copy)';
        $copy->stage = 'draft';
        $copy->status = 'planning';
        $copy->progress = 0;
        $copy->archived_at = null;
        $copy->save();

        session()->flash('status', "“{$copy->name}” created as a draft — open its hub to set it up.");

        return $this->redirectRoute('events.index', ['selected' => $copy->id]);
    }

    public function archive(int $eventId): void
    {
        Event::findOrFail($eventId)->update(['archived_at' => now()]);

        if ($this->selectedId === $eventId) {
            $this->selectedId = null;
        }

        session()->flash('status', 'Event archived — it no longer appears in lists or the Operations Hub.');
    }

    public function prevMonth(): void
    {
        $this->calMonth = Carbon::createFromFormat('Y-m', $this->calMonth)->subMonth()->format('Y-m');
    }

    public function nextMonth(): void
    {
        $this->calMonth = Carbon::createFromFormat('Y-m', $this->calMonth)->addMonth()->format('Y-m');
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
            'budgetItems', 'suppliers', 'rooms', 'agendaSessions', 'risks', 'approvals', 'sponsors',
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

        return view('livewire.events-index', [
            'events' => $events,
            'health' => $health,
            'metrics' => $metrics,
            'favoriteIds' => $favoriteIds,
            'selected' => $selected,
            'selectedHealth' => $selected ? $health[$selected->id] : null,
            'ai' => $selected ? $pulse->aiSummary($selected) : null,
            'calendar' => $this->view === 'calendar' ? $this->buildCalendar($all) : null,
            'pipeline' => $this->view === 'kanban' ? $this->buildPipeline($all) : null,
            'kpis' => [
                ['label' => 'Total Events', 'value' => Event::whereNull('archived_at')->count(), 'icon' => 'calendar', 'tone' => 'blue',
                    'trend' => '↑ '.Event::whereNull('archived_at')->whereMonth('created_at', now()->month)->count().' added this month', 'up' => true],
                ['label' => 'Active Events', 'value' => Event::whereNull('archived_at')->whereIn('stage', ['confirmed', 'planning', 'production'])->count(), 'icon' => 'folder', 'tone' => 'green',
                    'trend' => '↑ '.Event::whereNull('archived_at')->where('stage', 'production')->count().' in production', 'up' => true],
                ['label' => 'Live Events', 'value' => Event::whereNull('archived_at')->where('stage', 'live')->count(), 'icon' => 'sparkles', 'tone' => 'gold',
                    'trend' => 'happening today', 'up' => true],
                ['label' => 'At Risk', 'value' => Event::whereNull('archived_at')->whereIn('status', ['at_risk', 'behind'])->count(), 'icon' => 'bell', 'tone' => 'red',
                    'trend' => '↓ needs attention', 'up' => false],
                ['label' => 'Open Tasks', 'value' => Task::whereNot('status', 'completed')->count(), 'icon' => 'clipboard', 'tone' => 'green',
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

    /**
     * Pipeline columns keyed by bucket, each carrying its events (by stage).
     */
    private function buildPipeline($all): array
    {
        $columns = [];
        foreach (self::PIPELINE as $key => $meta) {
            $columns[$key] = $meta + [
                'events' => $all->filter(fn (Event $e) => in_array($e->stage, $meta['stages'], true))
                    ->sortBy('starts_at')->values(),
            ];
        }

        return $columns;
    }
}
