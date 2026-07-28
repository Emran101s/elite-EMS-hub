<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventAgendaSession;
use Illuminate\Support\Carbon;

/**
 * Everything the event hub's header says about an event, in one place.
 *
 * The old header was one white bar: crest, name, three meters, dates. It told
 * you the event existed. What it did not tell you is the thing you open an
 * event to find out — how big it is, how close it is, whether it is ready, and
 * what the one thing is that needs you today.
 *
 * Four blocks, each answering one of those:
 *
 *   scale()      how big — the figures you would say out loud
 *   critical()   what needs a person today, worst first
 *   meters()     module by module, how far along, with the count behind the %
 *   readiness()  can this go live — the gates, and how many are met
 *
 * Every figure is counted off the event's own records rather than stored, so
 * the header cannot disagree with the tab you click through to.
 */
class EventCommandHeader
{
    /** Relations every block below reads. Eager-load these or pay per figure. */
    public const RELATIONS = [
        'tasks.assignee', 'risks.owner', 'approvals.requester', 'budgetItems',
        'agendaDays', 'agendaSessions.day', 'speakers', 'sponsors', 'rooms', 'suppliers',
        'venue', 'transport.manifest', 'attendees',
    ];

    public function __construct(private EventHealthService $health) {}

    /**
     * The whole header, in the order it is drawn.
     */
    public function for(Event $event): array
    {
        $health = $this->health->breakdown($event);

        return [
            'health' => $health,
            'title' => $this->title($event),
            'scale' => $this->scale($event),
            'critical' => $this->critical($event),
            'meters' => $this->meters($event, $health),
            'readiness' => $this->readiness($event),
            'live' => $this->live($event),
        ];
    }

    /**
     * The name, split where it names a sub-brand.
     *
     * "The First World Public Summit . Arab World" is one name doing two jobs:
     * the summit, and which edition of it. Where a separator says so, the tail
     * is set under a rule rather than run on, which is how the thing is
     * actually written down. No separator, no split.
     *
     * @return array{lead:string,tail:?string}
     */
    public function title(Event $event): array
    {
        foreach ([' . ', ' — ', ' – ', ' | '] as $separator) {
            if (str_contains($event->name, $separator)) {
                [$lead, $tail] = explode($separator, $event->name, 2);

                return ['lead' => trim($lead), 'tail' => trim($tail)];
            }
        }

        return ['lead' => trim($event->name), 'tail' => null];
    }

    /**
     * How big, and how close.
     *
     * Participants is the registered count where anybody has registered, and
     * the expected number before that — an event with 0 registered and 650
     * expected is 650 people's worth of work either way.
     *
     * @return array<int,array{label:string,value:string,icon:string,tone:?string}>
     */
    public function scale(Event $event): array
    {
        $registered = $event->attendees->count();
        $days = $event->agendaDays->count()
            ?: ($event->starts_at && $event->ends_at ? $event->starts_at->diffInDays($event->ends_at) + 1 : 0);

        $stats = [
            ['label' => $registered > 0 ? 'Participants' : 'Expected',
                'value' => number_format($registered > 0 ? $registered : (int) $event->expected_participants),
                'icon' => 'users', 'tone' => null],
            ['label' => 'Speakers', 'value' => (string) $event->speakers->count(), 'icon' => 'sparkles', 'tone' => null],
            ['label' => 'Sponsors', 'value' => (string) $event->sponsors->count(), 'icon' => 'star', 'tone' => null],
            ['label' => 'Venues', 'value' => (string) $event->rooms->count(), 'icon' => 'building', 'tone' => null],
            ['label' => 'Days', 'value' => (string) $days, 'icon' => 'calendar', 'tone' => null],
        ];

        if ($countdown = $this->countdown($event)) {
            $stats[] = $countdown;
        }

        return $stats;
    }

    /**
     * Days to go — or, once it starts, which day of it you are on.
     */
    private function countdown(Event $event): ?array
    {
        if (! $event->starts_at) {
            return null;
        }

        $today = Carbon::today();

        if ($event->starts_at->isFuture()) {
            return ['label' => 'To go', 'value' => (int) $today->diffInDays($event->starts_at).'d',
                'icon' => 'clock', 'tone' => 'text-gold-600'];
        }

        if ($event->ends_at && $event->ends_at->endOfDay()->isFuture()) {
            return ['label' => 'Live now', 'value' => 'Day '.((int) $event->starts_at->diffInDays($today) + 1),
                'icon' => 'clock', 'tone' => 'text-emerald-600'];
        }

        return ['label' => 'Delivered', 'value' => $event->ends_at?->format('M Y') ?? '—',
            'icon' => 'clock', 'tone' => 'text-muted'];
    }

    /**
     * The one thing that needs a person today.
     *
     * Worst first, and each kind knows where it lives: a severe open risk beats
     * a pending approval beats the task that is most overdue. Nothing pressing
     * returns null and the card says so rather than inventing an errand.
     */
    public function critical(Event $event): ?array
    {
        $risk = $event->risks
            ->filter(fn ($r) => $r->isOpen() && $r->severity() >= 12)
            ->sortByDesc(fn ($r) => $r->severity())
            ->first();

        if ($risk) {
            return [
                'title' => $risk->title,
                'where' => str($risk->category ?: 'Risk register')->replace('_', ' ')->title()->toString(),
                'due' => $risk->due_on ? $this->when($risk->due_on) : 'Unscheduled',
                'owner' => $risk->owner?->name ?? 'Unassigned',
                'level' => $risk->severity() >= 20 ? 'Critical' : 'High',
                'tab' => 'risks',
                'cta' => 'Open risk',
            ];
        }

        $approval = $event->approvals->where('status', 'pending')
            ->sortBy('created_at')->first();

        if ($approval) {
            return [
                'title' => $approval->title,
                'where' => str($approval->type)->replace('_', ' ')->title()->toString().' approval',
                'due' => 'Requested '.$approval->created_at->diffForHumans(),
                'owner' => $approval->requester?->name ?? 'Unassigned',
                'level' => 'Medium',
                'tab' => 'approvals',
                'cta' => 'Review now',
            ];
        }

        $task = $event->tasks
            ->filter(fn ($t) => $t->isOpen() && $t->due_on)
            ->sortBy('due_on')
            ->first();

        if ($task) {
            return [
                'title' => $task->title,
                'where' => $task->area
                    ? (\App\Models\Task::MODULES[$task->area][0] ?? str($task->area)->title()->toString())
                    : 'Task board',
                'due' => $this->when($task->due_on),
                'owner' => $task->assignee?->name ?? 'Unassigned',
                'level' => match ($task->priority) {
                    'urgent' => 'Critical', 'high' => 'High', 'low' => 'Low', default => 'Medium',
                },
                'tab' => 'tasks',
                'cta' => 'Open task',
            ];
        }

        return null;
    }

    private function when(Carbon $date): string
    {
        return match (true) {
            $date->isToday() => 'Today',
            $date->isTomorrow() => 'Tomorrow',
            $date->isPast() => (int) $date->diffInDays(Carbon::today()).'d overdue',
            default => $date->format('D, j M'),
        };
    }

    /**
     * Module by module: how far along, and the count that number came from.
     *
     * The percentage is the health engine's — one definition of "how is Budget
     * doing" across the platform — but a bare percentage is a number you have
     * to trust. The detail line is the count behind it, so you can check.
     *
     * @return array<int,array{key:string,label:string,pct:?int,detail:string,icon:string}>
     */
    public function meters(Event $event, array $health): array
    {
        $components = $health['components'];

        $spent = $event->budgetItems->sum('actual_cents');
        $budget = $event->budget_cents ?: $event->budgetItems->sum('estimate_cents');

        $tasksDone = $event->tasks->whereIn('status', ['done', 'approved'])->count();
        $tasksTotal = $event->tasks->where('status', '!=', 'cancelled')->count();

        $sessions = $event->agendaSessions;
        $sessionsSet = $sessions->filter->isSettled()->count();

        $speakersConfirmed = $event->speakers->where('status', 'confirmed')->count();
        $sponsorsSettled = $event->sponsors->whereIn('payment_status', ['paid', 'partial'])->count();

        // Logistics is one line for the three things that move: the venue, the
        // suppliers bringing things to it, and the transport bringing people.
        $logistics = collect([$components['venue'], $components['suppliers'], $components['transport']])
            ->filter(fn ($v) => $v !== null);

        return [
            ['key' => 'budget', 'label' => 'Budget', 'icon' => 'currency',
                'pct' => $components['budget'],
                'detail' => $budget > 0
                    ? $this->money($spent, $event->currency).' / '.$this->money($budget, $event->currency)
                    : 'No budget set'],

            ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'clipboard',
                'pct' => $components['tasks'],
                'detail' => $tasksTotal ? $tasksDone.' / '.$tasksTotal : 'No tasks yet'],

            ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'calendar',
                'pct' => $components['agenda'],
                'detail' => $sessions->count() ? $sessionsSet.' / '.$sessions->count().' sessions' : 'No sessions yet'],

            ['key' => 'speakers', 'label' => 'Speakers', 'icon' => 'sparkles',
                'pct' => $event->speakers->count()
                    ? (int) round($speakersConfirmed / $event->speakers->count() * 100)
                    : null,
                'detail' => $event->speakers->count()
                    ? $speakersConfirmed.' / '.$event->speakers->count().' confirmed'
                    : 'No speakers yet'],

            ['key' => 'sponsors', 'label' => 'Sponsors', 'icon' => 'star',
                'pct' => $event->sponsors->count()
                    ? (int) round($sponsorsSettled / $event->sponsors->count() * 100)
                    : null,
                'detail' => $event->sponsors->count()
                    ? $sponsorsSettled.' / '.$event->sponsors->count().' committed'
                    : 'No sponsors yet'],

            ['key' => 'logistics', 'label' => 'Logistics', 'icon' => 'truck',
                'pct' => $logistics->isNotEmpty() ? (int) round($logistics->avg()) : null,
                'detail' => $logistics->isEmpty() ? 'Nothing booked yet'
                    : ($event->venue?->name ? str($event->venue->name)->limit(22)->toString() : 'Venue TBC')],
        ];
    }

    /**
     * Can this go live?
     *
     * Not another average of the same numbers — a list of gates, each of which
     * is either met or not, and the share of them that are. A gate with no data
     * is not counted rather than counted as failed, so an event that has not
     * reached a module yet is not marked down for it.
     *
     * @return array{pct:int,gates:array<int,array{label:string,met:bool,note:string}>}
     */
    public function readiness(Event $event): array
    {
        $gates = [];

        $sessions = $event->agendaSessions;
        if ($sessions->isNotEmpty()) {
            $open = $sessions->reject->isSettled()->count();
            $gates[] = ['label' => 'Agenda confirmed', 'met' => $open === 0,
                'note' => $open === 0 ? 'Every session settled' : $open.' still unconfirmed'];
        }

        if ($event->speakers->isNotEmpty()) {
            $waiting = $event->speakers->where('status', '!=', 'confirmed')->count();
            $gates[] = ['label' => 'Speakers confirmed', 'met' => $waiting === 0,
                'note' => $waiting === 0 ? 'All confirmed' : $waiting.' awaiting reply'];
        }

        if ($event->suppliers->isNotEmpty()) {
            $unsigned = $event->suppliers->filter(fn ($s) => in_array($s->pivot->status, ['requested', 'quoted', 'issue'], true))->count();
            $gates[] = ['label' => 'Suppliers contracted', 'met' => $unsigned === 0,
                'note' => $unsigned === 0 ? 'All contracted' : $unsigned.' not contracted'];
        }

        $gates[] = ['label' => 'Venue assigned', 'met' => (bool) $event->venue_id,
            'note' => $event->venue?->name ?? 'No venue set'];

        if ($event->transport->isNotEmpty()) {
            $unready = $event->transport->reject(fn ($m) => in_array($m->status, ['completed', 'cancelled'], true))
                ->reject->isReady()->count();
            $gates[] = ['label' => 'Transport ready', 'met' => $unready === 0,
                'note' => $unready === 0 ? 'Every movement crewed' : $unready.' missing a driver or vehicle'];
        }

        $pending = $event->approvals->where('status', 'pending')->count();
        $gates[] = ['label' => 'Approvals cleared', 'met' => $pending === 0,
            'note' => $pending === 0 ? 'Nothing pending' : $pending.' awaiting a decision'];

        $severe = $event->risks->filter(fn ($r) => $r->isOpen() && $r->severity() >= 12)->count();
        $gates[] = ['label' => 'No open severe risk', 'met' => $severe === 0,
            'note' => $severe === 0 ? 'Register clear' : $severe.' open'];

        $met = collect($gates)->where('met', true)->count();

        return [
            'pct' => $gates ? (int) round($met / count($gates) * 100) : 0,
            'met' => $met,
            'total' => count($gates),
            'gates' => $gates,
        ];
    }

    /**
     * The live strip: what is true right now, on the day you are reading it.
     *
     * @return array{label:string,tone:string,items:array<int,array{label:string,value:string,icon:string,tone:string}>}
     */
    public function live(Event $event): array
    {
        $today = Carbon::today();

        $sessionsToday = $event->agendaSessions
            ->filter(fn (EventAgendaSession $s) => $s->day?->date?->isSameDay($today))
            ->count();

        $speakersPending = $event->speakers->where('status', '!=', 'confirmed')->count();
        $risksOpen = $event->risks->filter(fn ($r) => $r->isOpen())->count();
        $overdue = $event->tasks->filter(fn ($t) => $t->isOpen() && $t->due_on?->isPast())->count();

        $trouble = $risksOpen + $overdue + $event->approvals->where('status', 'pending')->count();

        return [
            'label' => $trouble === 0 ? 'Everything on track' : $trouble.' '.str('item')->plural($trouble).' need attention',
            'tone' => $trouble === 0 ? 'bg-track' : ($trouble > 4 ? 'bg-risk' : 'bg-warn'),
            'items' => [
                ['label' => 'Registration', 'icon' => 'identification',
                    'value' => $event->registration_open ? 'Open' : 'Closed',
                    'tone' => $event->registration_open ? 'text-emerald-700' : 'text-muted'],
                ['label' => 'Sessions today', 'icon' => 'calendar',
                    'value' => (string) $sessionsToday, 'tone' => 'text-navy-900'],
                ['label' => 'Speakers pending', 'icon' => 'sparkles',
                    'value' => (string) $speakersPending,
                    'tone' => $speakersPending > 0 ? 'text-amber-700' : 'text-navy-900'],
                ['label' => 'Open risks', 'icon' => 'bell',
                    'value' => (string) $risksOpen,
                    'tone' => $risksOpen > 0 ? 'text-red-700' : 'text-navy-900'],
                ['label' => 'Overdue tasks', 'icon' => 'clipboard',
                    'value' => (string) $overdue,
                    'tone' => $overdue > 0 ? 'text-red-700' : 'text-navy-900'],
            ],
        ];
    }

    /** Short money, because a header has no room for 35,000,000.00. */
    private function money(int $cents, ?string $currency): string
    {
        $symbol = Event::CURRENCIES[$currency ?? 'JOD'][0] ?? '';
        $value = $cents / 100;

        return $symbol.match (true) {
            abs($value) >= 1_000_000 => round($value / 1_000_000, 1).'M',
            abs($value) >= 1_000 => round($value / 1_000).'K',
            default => number_format($value),
        };
    }
}
