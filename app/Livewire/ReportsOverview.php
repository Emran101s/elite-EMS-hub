<?php

namespace App\Livewire;

use App\Livewire\Hub\AgendaTab;
use App\Models\Event;
use App\Models\EventAgendaSession;
use App\Models\EventAttendee;
use App\Models\Task;
use App\Services\EventHealthService;
use App\Services\EventJourney;
use App\Services\PortfolioFinance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Reports: the book read across, rather than one event at a time.
 *
 * Nothing here is stored. Every figure is the same count the module it came
 * from would give you, which is the only reason a report is worth reading — a
 * number that agrees with the screen you clicked through from is a number you
 * can take to a client.
 *
 * Four questions, in the order anybody asks them:
 *
 *   Delivery      is the work getting done, and what is late
 *   Money         what it costs, what it sells for, what is unbilled
 *   Programme     how much stage there is, and who is on it
 *   People        who registered, and who actually came
 */
#[Layout('components.layouts.app', ['title' => 'Reports', 'hideTitleRow' => true])]
class ReportsOverview extends Component
{
    /** all · live · upcoming · delivered — which slice of the book to read. */
    public string $window = 'all';

    public function setWindow(string $window): void
    {
        if (in_array($window, ['all', 'live', 'upcoming', 'delivered'], true)) {
            $this->window = $window;
        }
    }

    /**
     * The events this report covers.
     */
    private function events(): Collection
    {
        $today = Carbon::today();

        return Event::whereNull('archived_at')
            ->with(array_merge(EventHealthService::RELATIONS, EventJourney::RELATIONS, [
                'client', 'venue', 'speakers', 'attendees', 'agendaSessions',
            ]))
            ->orderBy('starts_at')
            ->get()
            ->filter(fn (Event $e) => match ($this->window) {
                'live' => $e->starts_at && $e->starts_at->copy()->startOfDay()->lte($today)
                    && ($e->ends_at ?? $e->starts_at)->copy()->endOfDay()->gte($today),
                'upcoming' => $e->starts_at && $e->starts_at->copy()->startOfDay()->gt($today),
                'delivered' => ($e->ends_at ?? $e->starts_at)?->copy()->endOfDay()->lt($today) ?? false,
                default => true,
            })
            ->values();
    }

    public function render()
    {
        $events = $this->events();
        $health = app(EventHealthService::class);
        $journey = app(EventJourney::class);
        $finance = app(PortfolioFinance::class);

        $ids = $events->pluck('id')->all();
        $totals = $finance->totals();
        $money = $finance->statement()->keyBy(fn ($row) => $row['event']->id);

        // ── Delivery: one row per event, every column counted here ──
        $delivery = $events->map(function (Event $event) use ($health, $journey, $money) {
            $h = $health->breakdown($event);
            $track = collect($journey->for($event));
            $now = $track->first(fn ($p) => in_array($p['state'], ['in_progress', 'live'], true));

            $tasks = $event->tasks->where('status', '!=', 'cancelled');
            $overdue = $tasks->filter(fn (Task $t) => $t->isOpen() && $t->due_on?->isPast())->count();

            return [
                'event' => $event,
                'score' => $h['score'],
                'group' => $h['group'],
                'phase' => $now['label'] ?? 'Closed',
                'phaseHex' => $now['hex'] ?? '#94A3B8',
                'phasePct' => $now['pct'] ?? 100,
                'tasksDone' => $tasks->whereIn('status', ['done', 'approved'])->count(),
                'tasksTotal' => $tasks->count(),
                'overdue' => $overdue,
                'risks' => $event->risks->filter->isOpen()->count(),
                'approvals' => $event->approvals->where('status', 'pending')->count(),
                'margin' => $money[$event->id]['pricedMargin'] ?? null,
            ];
        });

        // ── Programme: how much stage, and who is on it ──
        $sessions = EventAgendaSession::whereIn('event_id', $ids)->with('day')->get();

        // ── People: registered against arrived ──
        $attendees = EventAttendee::whereIn('event_id', $ids)->get();
        $registered = $attendees->count();
        $arrived = $attendees->where('status', 'checked_in')->count();

        $tasks = Task::whereIn('event_id', $ids)->where('status', '!=', 'cancelled')->get();

        return view('livewire.reports-overview', [
            'events' => $events,
            'delivery' => $delivery->sortByDesc(fn ($r) => $r['overdue'] + $r['risks'] + $r['approvals'])->values(),
            'money' => $money,
            'totals' => $totals,

            'figures' => [
                ['label' => 'Events', 'note' => ucfirst($this->window === 'all' ? 'in the book' : $this->window),
                    'value' => $events->count(), 'icon' => 'calendar', 'tone' => 'navy'],
                ['label' => 'Contracted', 'note' => 'Client, sponsors, exhibition',
                    'value' => EventsIndex::shortMoney($totals['income'], $totals['currency']), 'icon' => 'currency', 'tone' => 'gold'],
                ['label' => 'Margin', 'note' => 'On what the work is priced at',
                    'value' => $totals['pricedMargin'] === null ? '—' : $totals['pricedMargin'].'%', 'icon' => 'chart', 'tone' => 'green'],
                ['label' => 'Sessions', 'note' => $sessions->filter->isSettled()->count().' confirmed',
                    'value' => $sessions->count(), 'icon' => 'clipboard', 'tone' => 'blue'],
                ['label' => 'Registered', 'note' => $registered ? $arrived.' checked in' : 'Nobody yet',
                    'value' => number_format($registered), 'icon' => 'users', 'tone' => 'violet'],
            ],

            'programme' => [
                'sessions' => $sessions->count(),
                'settled' => $sessions->filter->isSettled()->count(),
                'hours' => round($sessions->sum(function (EventAgendaSession $s) {
                    $min = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);

                    return max($min($s->ends_at) - $min($s->starts_at), 0);
                }) / 60, 1),
                'speakers' => $events->sum(fn (Event $e) => $e->speakers->count()),
                'rooms' => $events->sum(fn (Event $e) => $e->rooms->count()),
                'byType' => collect(AgendaTab::PALETTE)
                    ->map(fn ($meta, $type) => [
                        'label' => $meta[0], 'hex' => $meta[1],
                        'count' => $sessions->where('type', $type)->count(),
                    ])
                    ->filter(fn ($row) => $row['count'] > 0)
                    ->sortByDesc('count')->values(),
            ],

            'people' => [
                'registered' => $registered,
                'arrived' => $arrived,
                'pct' => $registered ? (int) round($arrived / $registered * 100) : 0,
                'vip' => $attendees->where('vip', true)->count(),
                'byEvent' => $events->map(fn (Event $e) => [
                    'event' => $e,
                    'registered' => $e->attendees->count(),
                    'arrived' => $e->attendees->where('status', 'checked_in')->count(),
                ])->filter(fn ($row) => $row['registered'] > 0)->sortByDesc('registered')->values(),
            ],

            'work' => [
                'total' => $tasks->count(),
                'done' => $tasks->whereIn('status', ['done', 'approved'])->count(),
                'overdue' => $tasks->filter(fn (Task $t) => $t->isOpen() && $t->due_on?->isPast())->count(),
                'unassigned' => $tasks->filter(fn (Task $t) => $t->isOpen() && ! $t->assignee_id)->count(),
            ],
        ]);
    }
}
