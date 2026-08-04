<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventAgendaSession;
use App\Models\EventTransport;
use App\Models\Task;
use App\Services\EventCommandHeader;
use App\Services\EventHealthService;
use App\Services\PortfolioAdvisor;
use App\Services\PortfolioFinance;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The dashboard. One of them.
 *
 * There used to be two — a Dashboard and an Operations Room — and neither knew
 * what it was for, so both drifted into being a worse version of the Events
 * page: a list of events with figures around it.
 *
 * This one has a job the other screens do not: it answers **when**. Events is
 * the book event by event, Reports is the book counted, the Assistant is what
 * needs you. This is *today, then this week, then the year* — the first screen
 * of the morning, and the only one on the platform organised by time.
 *
 *   spotlight   the event on the floor right now, or the next one in
 *   today       every session, movement, arrival and deadline dated today
 *   week        the next seven days, so nothing lands as a surprise
 *   signals     the worst few from the briefing, with the rest one click away
 *   book        where the whole portfolio stands
 */
#[Layout('components.layouts.app', ['title' => 'Dashboard', 'hideTitleRow' => true])]
class Dashboard extends Component
{
    public function render()
    {
        $today = Carbon::today();
        $health = app(EventHealthService::class);

        $events = Event::whereNull('archived_at')
            ->with(array_merge(EventHealthService::RELATIONS, PortfolioAdvisor::RELATIONS, [
                'client', 'venue', 'agendaDays', 'attendees',
            ]))
            ->orderBy('starts_at')
            ->get();

        $ids = $events->pluck('id')->all();

        // ── the event on the floor, or the next one in ──
        $live = $events->first(fn (Event $e) => $e->starts_at
            && $e->starts_at->copy()->startOfDay()->lte($today)
            && ($e->ends_at ?? $e->starts_at)->copy()->endOfDay()->gte($today));

        $spotlight = $live ?? $events
            ->filter(fn (Event $e) => $e->starts_at?->copy()->startOfDay()->gte($today))
            ->sortBy('starts_at')->first();

        $advisor = app(PortfolioAdvisor::class);
        $signals = $advisor->attention($events);

        // One pass over each table for the whole seven-day window, instead of
        // dayAt()'s old one-query-per-table-per-day (8 days × ~4 queries).
        $window = $this->loadWindow($today, $today->copy()->addDays(6), $ids);

        return view('livewire.dashboard', [
            'now' => $today,
            'events' => $events,

            'spotlight' => $spotlight,
            'spotlightLive' => (bool) $live,
            'spotlightHeader' => $spotlight
                ? app(EventCommandHeader::class)->for($spotlight->loadMissing(EventCommandHeader::RELATIONS))
                : null,

            'headline' => $advisor->headline($events, $signals),
            'signals' => $signals->take(5),
            'signalCount' => $signals->count(),

            'today' => $window[$today->toDateString()],
            'week' => collect(range(0, 6))->map(function (int $offset) use ($today, $events, $window) {
                $day = $today->copy()->addDays($offset);

                return $window[$day->toDateString()] + [
                    'date' => $day,
                    'today' => $offset === 0,
                    // An event starting or ending is the thing you would circle
                    // on a paper calendar, so it is called out separately.
                    'starting' => $events->filter(fn (Event $e) => $e->starts_at?->isSameDay($day))->values(),
                    'ending' => $events->filter(fn (Event $e) => $e->ends_at?->isSameDay($day) && ! $e->starts_at?->isSameDay($day))->values(),
                ];
            }),

            'figures' => $this->figures($events, $health),
            // The lifecycle, from the same locked state set the rest of the
            // platform reads — not a second list that can drift from it.
            'stages' => collect(\App\Support\Workflow::SETS['event_stage']['states'])
                ->map(fn ($_, string $key) => [
                    'key' => $key,
                    'label' => \App\Support\Workflow::label('event_stage', $key),
                    'hex' => \App\Support\Workflow::color('event_stage', $key) ?: '#94A3B8',
                    'count' => $events->where('stage', $key)->count(),
                ])
                ->values()->all(),
            'money' => app(PortfolioFinance::class)->totals(),
        ]);
    }

    /**
     * What lands on each day of a window, across every module — one query per
     * table for the whole window rather than dayAt()'s old per-day queries,
     * which cost the week strip up to ~28 round trips on its own.
     *
     * @return array<string, array{sessions:int,movements:int,tasks:int,arrivals:int,load:int}>
     */
    private function loadWindow(Carbon $start, Carbon $end, array $ids): array
    {
        $days = [];
        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $days[$cursor->toDateString()] = ['sessions' => 0, 'movements' => 0, 'tasks' => 0, 'arrivals' => 0];
        }

        // whereDate() (not whereBetween on the raw column) throughout: these
        // date/datetime columns can carry a stored time part, and dayAt() used
        // whereDate() per day precisely to ignore it — a plain whereBetween
        // string-compares the column and silently drops the end-of-range day.
        EventAgendaSession::whereIn('event_id', $ids)
            ->whereHas('day', fn ($q) => $q->whereDate('date', '>=', $start->toDateString())
                ->whereDate('date', '<=', $end->toDateString()))
            ->with('day:id,date')
            ->get(['id', 'agenda_day_id'])
            ->each(function (EventAgendaSession $session) use (&$days) {
                $key = $session->day->date->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['sessions']++;
                }
            });

        // Movements and arrivals both read from the same rows: a movement
        // counts unless cancelled, an arrival's passengers count regardless
        // (an arrival that got cancelled after people already boarded still
        // happened) — the same two rules dayAt() applied per day.
        EventTransport::whereIn('event_id', $ids)
            ->whereDate('depart_at', '>=', $start->toDateString())
            ->whereDate('depart_at', '<=', $end->toDateString())
            ->get(['id', 'event_id', 'depart_at', 'type', 'status', 'passengers'])
            ->each(function (EventTransport $movement) use (&$days) {
                $key = $movement->depart_at->toDateString();
                if (! isset($days[$key])) {
                    return;
                }
                if ($movement->status !== 'cancelled') {
                    $days[$key]['movements']++;
                }
                if ($movement->type === 'arrival') {
                    $days[$key]['arrivals'] += (int) $movement->passengers;
                }
            });

        Task::whereIn('event_id', $ids)
            ->whereNotIn('status', ['done', 'approved', 'cancelled'])
            ->whereDate('due_on', '>=', $start->toDateString())
            ->whereDate('due_on', '<=', $end->toDateString())
            ->get(['id', 'due_on'])
            ->each(function (Task $task) use (&$days) {
                $key = $task->due_on->toDateString();
                if (isset($days[$key])) {
                    $days[$key]['tasks']++;
                }
            });

        foreach ($days as $key => $counts) {
            $days[$key]['load'] = $counts['sessions'] + $counts['movements'] + $counts['tasks'];
        }

        return $days;
    }

    /** The strip, in the language every other page opens with. */
    private function figures(Collection $events, EventHealthService $health): array
    {
        $today = Carbon::today();
        $ids = $events->pluck('id')->all();

        $live = $events->filter(fn (Event $e) => $e->starts_at
            && $e->starts_at->copy()->startOfDay()->lte($today)
            && ($e->ends_at ?? $e->starts_at)->copy()->endOfDay()->gte($today))->count();

        $atRisk = $events->filter(fn (Event $e) => in_array($health->breakdown($e)['status'], ['at_risk', 'behind'], true))->count();

        $open = Task::whereIn('event_id', $ids)->whereNotIn('status', ['done', 'approved', 'cancelled']);
        $overdue = (clone $open)->whereDate('due_on', '<', $today)->count();

        return [
            ['label' => 'In the book', 'note' => 'Not archived', 'value' => $events->count(),
                'icon' => 'calendar', 'tone' => 'navy', 'href' => route('events.index')],
            ['label' => 'Live now', 'note' => $live ? 'On the floor today' : 'Nothing running', 'value' => $live,
                'icon' => 'sparkles', 'tone' => 'green', 'href' => route('events.index')],
            ['label' => 'Open tasks', 'note' => $overdue ? $overdue.' past their date' : 'Nothing overdue', 'value' => $open->count(),
                'icon' => 'clipboard', 'tone' => 'blue', 'href' => route('tasks.index')],
            ['label' => 'At risk', 'note' => $atRisk ? 'Needs attention' : 'All clear', 'value' => $atRisk,
                'icon' => 'bell', 'tone' => 'red', 'href' => route('reports.index')],
            ['label' => 'Signals', 'note' => 'On the briefing', 'value' => app(PortfolioAdvisor::class)->attention($events)->count(),
                'icon' => 'chart', 'tone' => 'gold', 'href' => route('ai.index')],
        ];
    }
}
