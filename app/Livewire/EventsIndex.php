<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventApproval;
use App\Models\Task;
use App\Services\EventHealthService;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app', ['title' => 'Events', 'subtitle' => 'Manage all events, venues, suppliers, budgets and operations.'])]
class EventsIndex extends Component
{
    use WithPagination;

    public const PER_PAGE = 6;

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

    public ?string $stage = null;

    /** Exact-type filter from ?type= (deep links keep working). */
    public ?string $exactType = null;

    public ?int $selectedId = null;

    public function mount(): void
    {
        $this->q = (string) request('q', '');
        $this->stage = request('stage') ?: null;
        $this->exactType = request('type') ?: null;
        $this->view = in_array(request('view'), ['grid', 'list'], true) ? request('view') : 'grid';
        $this->selectedId = request()->integer('selected') ?: null;
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

    private function baseQuery()
    {
        return Event::query()
            ->when($this->q, fn ($query, $q) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('city', 'like', "%{$q}%")
                ->orWhere('country', 'like', "%{$q}%")
                ->orWhereHas('client', fn ($c) => $c->where('name', 'like', "%{$q}%"))
                ->orWhereHas('venue', fn ($v) => $v->where('name', 'like', "%{$q}%"))))
            ->when($this->exactType, fn ($query, $type) => $query->where('type', $type))
            ->when(! $this->exactType && self::TYPE_TABS[$this->tab], fn ($query) => $query->whereIn('type', self::TYPE_TABS[$this->tab]))
            ->when($this->stage, fn ($query, $stage) => $query->where('stage', $stage));
    }

    public function render()
    {
        $pulse = app(EventHealthService::class);

        $relations = [
            'venue', 'avatar', 'client', 'projectManager', 'tasks',
            'budgetItems', 'suppliers', 'rooms', 'agendaSessions', 'risks', 'approvals', 'sponsors',
        ];

        $events = $this->baseQuery()->with($relations)->orderBy('starts_at')->paginate(self::PER_PAGE);

        $health = collect($events->items())->mapWithKeys(fn (Event $event) => [$event->id => $pulse->breakdown($event)]);

        $metrics = collect($events->items())->mapWithKeys(function (Event $event) {
            $estimated = $event->budgetItems->sum('estimated_cents');
            $actual = $event->budgetItems->sum('actual_cents');
            $denominator = $event->budget_cents > 0 ? $event->budget_cents : $estimated;

            return [$event->id => [
                'participants' => $event->expected_participants,
                'sponsors' => $event->sponsors->count(),
                'budget_used' => $denominator > 0 ? (int) round($actual / $denominator * 100) : null,
            ]];
        });

        $selected = ($this->selectedId ? Event::with($relations)->find($this->selectedId) : null)
            ?? collect($events->items())->first();

        return view('livewire.events-index', [
            'events' => $events,
            'health' => $health,
            'metrics' => $metrics,
            'selected' => $selected,
            'selectedHealth' => $selected ? ($health[$selected->id] ?? $pulse->breakdown($selected)) : null,
            'ai' => $selected ? $pulse->aiSummary($selected) : null,
            'kpis' => [
                ['label' => 'Total Events', 'value' => Event::count(), 'icon' => 'calendar', 'tone' => 'blue',
                    'trend' => '↑ '.Event::whereMonth('created_at', now()->month)->count().' added this month', 'up' => true],
                ['label' => 'Active Events', 'value' => Event::whereIn('stage', ['confirmed', 'planning', 'production'])->count(), 'icon' => 'folder', 'tone' => 'green',
                    'trend' => '↑ '.Event::where('stage', 'production')->count().' in production', 'up' => true],
                ['label' => 'Live Events', 'value' => Event::where('stage', 'live')->count(), 'icon' => 'sparkles', 'tone' => 'gold',
                    'trend' => 'happening today', 'up' => true],
                ['label' => 'At Risk', 'value' => Event::whereIn('status', ['at_risk', 'behind'])->count(), 'icon' => 'bell', 'tone' => 'red',
                    'trend' => '↓ needs attention', 'up' => false],
                ['label' => 'Open Tasks', 'value' => Task::whereNot('status', 'completed')->count(), 'icon' => 'clipboard', 'tone' => 'green',
                    'trend' => '↑ across all events', 'up' => true],
                ['label' => 'Pending Approvals', 'value' => EventApproval::where('status', 'pending')->count(), 'icon' => 'identification', 'tone' => 'gold',
                    'trend' => '↑ awaiting decision', 'up' => true],
            ],
        ]);
    }
}
