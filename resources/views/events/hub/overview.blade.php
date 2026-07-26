{{--
    Event Hub — Overview (session 5d). THE reference screen: every other module
    inherits this rhythm, so it is deliberately plain in structure.

    The shell already supplies the topbar, the KPI ribbon, the orbit, the Event
    Pulse and AI Director rails and the dock. This workspace therefore carries
    only what the shell does not:

        greeting + Quick Add   →  what to do next, and the fastest way to do it
        event timeline         →  where we are in the delivery
        my tasks               →  the work that is actually mine
        four summary cards     →  agenda · money · people · documents

    Live alerts are NOT repeated here — they live in the rail.
--}}
@php
    $firstName = \Illuminate\Support\Str::of(auth()->user()?->name ?? '')->explode(' ')->first();
    $cur = $event->currencySymbol();

    $open = $event->tasks->filter->isOpen();
    $overdue = $open->filter(fn ($t) => $t->due_on?->isPast());
    $mine = $open->where('assignee_id', auth()->id())->sortBy('due_on')->take(4);

    // The opening sentence: the single most useful thing we can say today.
    $daysOut = $event->starts_at ? (int) round(now()->startOfDay()->diffInDays($event->starts_at->copy()->startOfDay(), false)) : null;
    $headline = match (true) {
        $overdue->isNotEmpty() => $overdue->count().' '.str('task')->plural($overdue->count()).' '.($overdue->count() === 1 ? 'is' : 'are').' overdue.',
        $daysOut !== null && $daysOut <= 0 => 'The event is live.',
        $daysOut !== null => $daysOut.' days to go, and nothing is overdue.',
        default => 'No dates set yet — add them so the countdown and timeline can work.',
    };

    // Delivery bands across the run-up. Percentages are of the whole planning
    // window, so "today" lands where it really is rather than at a fixed point.
    $created = $event->created_at ?? now()->subMonths(6);
    $end = ($event->ends_at ?? $event->starts_at) ?? now();
    $span = max(1, $created->diffInDays($end));
    $at = fn ($date) => max(0, min(100, round($created->diffInDays($date) / $span * 100, 1)));

    $budget = (int) ($event->budget_cents ?? 0);
    $spent = (int) $event->budgetItems->sum('actual_cents');
    $income = $event->incomeSummary();

    $sessions = $event->agendaDays->sum(fn ($d) => $d->sessions->count());
    $confirmed = $event->speakers->filter(fn ($s) => ($s->pivot->status ?? null) === 'confirmed')->count();
    $openRisks = $event->risks->filter->isOpen()->count();
@endphp

<div style="display:grid;gap:var(--o-4)">

    {{-- ══ HERO — the one card that answers "why did I open this?" ══ --}}
    <x-orbit.card gravity="hero">
        <x-orbit.greeting :heading="'Good '.(now()->hour < 12 ? 'morning' : (now()->hour < 18 ? 'afternoon' : 'evening')).', '.$firstName">
            <x-slot:summary><b>{{ $headline }}</b> {{ $open->count() }} open {{ str('task')->plural($open->count()) }} across the event.</x-slot:summary>
            <x-slot:aside>
                <x-orbit.btn variant="gold" :href="route('events.hub', [$event, 'tab' => 'tasks'])">Open the board</x-orbit.btn>
            </x-slot:aside>
        </x-orbit.greeting>

        <div style="margin-top:var(--o-5)">
            <x-orbit.quick-add :items="[
                ['label' => 'Task', 'icon' => 'task', 'href' => route('events.hub', [$event, 'tab' => 'tasks'])],
                ['label' => 'Session', 'icon' => 'cal', 'href' => route('events.hub', [$event, 'tab' => 'agenda'])],
                ['label' => 'Supplier', 'icon' => 'truck', 'href' => route('events.hub', [$event, 'tab' => 'suppliers'])],
                ['label' => 'Expense', 'icon' => 'money', 'href' => route('events.hub', [$event, 'tab' => 'budget'])],
                ['label' => 'Document', 'icon' => 'doc', 'href' => route('events.hub', [$event, 'tab' => 'files'])],
            ]" />
        </div>
    </x-orbit.card>

    {{-- ══ EVENT TIMELINE ══ --}}
    <x-orbit.card title="Event timeline">
        @if ($event->starts_at)
            <x-orbit.gantt :today="$at(now())"
                :scale="collect(range(0, 4))->map(fn ($i) => $created->copy()->addDays($span * $i / 4)->format('M'))->all()"
                :bands="[
                    ['label' => 'Planning', 'start' => 0, 'end' => $at($event->starts_at->copy()->subMonths(2)), 'tone' => 'plasma'],
                    ['label' => 'Production', 'start' => $at($event->starts_at->copy()->subMonths(2)), 'end' => $at($event->starts_at), 'tone' => 'ion'],
                    ['label' => 'Show days', 'start' => $at($event->starts_at), 'end' => $at($end), 'tone' => 'gold', 'note' => $event->starts_at->format('j M').' – '.$end->format('j M')],
                ]" />
        @else
            <p class="o-mute" style="margin:0">Set the event dates and the delivery timeline appears here.</p>
        @endif
    </x-orbit.card>

    {{-- ══ MY TASKS ══ --}}
    <x-orbit.card :pad="false">
        <x-slot:head>
            <h3 class="o-card__title">My tasks</h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'tasks']) }}" class="o-btn o-btn--quiet o-btn--sm">All tasks →</a>
        </x-slot:head>
        <div class="o-card__pad">
            @if ($mine->isNotEmpty())
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(230px,1fr));gap:var(--o-3)">
                    @foreach ($mine as $task)
                        <x-orbit.task-card
                            :title="$task->title"
                            :module="\App\Models\Event::moduleLabel($task->area)"
                            :due="$task->due_on?->format('M j, Y')"
                            :status="$task->status"
                            :overdue="(bool) $task->due_on?->isPast()"
                            :more="false" />
                    @endforeach
                </div>
            @else
                <x-orbit.empty icon="task" title="Nothing is assigned to you here"
                               body="Tasks you own on this event appear in this row, so you can start without opening the board.">
                    <x-orbit.btn variant="ghost" :href="route('events.hub', [$event, 'tab' => 'tasks'])">Open the board</x-orbit.btn>
                </x-orbit.empty>
            @endif
        </div>
    </x-orbit.card>

    {{-- ══ FOUR SUMMARY CARDS ══ --}}
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(268px,1fr));gap:var(--o-4)">

        <x-orbit.card accent="ion" title="Agenda">
            <div class="o-metric">{{ $sessions }}</div>
            <p class="o-mute" style="margin:5px 0 var(--o-4)">{{ str('session')->plural($sessions) }} across {{ $event->agendaDays->count() }} {{ str('day')->plural($event->agendaDays->count()) }}</p>
            <ul class="o-pulse">
                <li><i style="background:var(--ion-lit)"></i>Speakers<b>{{ $event->speakers->count() }}</b></li>
                <li><i style="background:var(--vital-lit)"></i>Confirmed<b>{{ $confirmed }}</b></li>
            </ul>
            <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" class="o-btn o-btn--ghost o-btn--sm" style="margin-top:var(--o-4)">Open agenda →</a>
        </x-orbit.card>

        <x-orbit.card accent="gold" title="Money">
            <div class="o-metric">{{ $cur }} {{ number_format($budget / 100) }}</div>
            <p class="o-mute" style="margin:5px 0 var(--o-4)">budget · {{ $cur }} {{ number_format(($income['collected'] ?? 0) / 100) }} collected</p>
            @if ($budget > 0)
                <x-orbit.meter tall legend :total="$budget" :segments="[
                    ['value' => $spent, 'tone' => 'plasma', 'label' => 'Spent', 'display' => $cur.' '.number_format($spent / 100)],
                    ['value' => max(0, $budget - $spent), 'tone' => 'vital', 'label' => 'Remaining', 'display' => $cur.' '.number_format(max(0, $budget - $spent) / 100)],
                ]" />
            @else
                <p class="o-mute" style="margin:0">No budget set yet.</p>
            @endif
            <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" class="o-btn o-btn--ghost o-btn--sm" style="margin-top:var(--o-4)">Open budget →</a>
        </x-orbit.card>

        <x-orbit.card accent="vital" title="People &amp; suppliers">
            <div class="o-metric">{{ $event->suppliers->count() }}</div>
            <p class="o-mute" style="margin:5px 0 var(--o-4)">suppliers engaged</p>
            <ul class="o-pulse">
                <li><i style="background:var(--plasma-lit)"></i>Sponsors<b>{{ $event->sponsors->count() }}</b></li>
                <li><i style="background:var(--ion-lit)"></i>Expected guests<b>{{ number_format((int) ($event->expected_participants ?? 0)) }}</b></li>
            </ul>
            <a href="{{ route('events.hub', [$event, 'tab' => 'suppliers']) }}" class="o-btn o-btn--ghost o-btn--sm" style="margin-top:var(--o-4)">Open suppliers →</a>
        </x-orbit.card>

        <x-orbit.card accent="plasma" title="Documents">
            <ul class="o-pulse">
                <li>
                    <i style="background:{{ $event->brief ? 'var(--vital-lit)' : 'var(--rim-hi)' }}"></i>
                    Event Brief<b>{{ $event->brief ? 'Ready' : '—' }}</b>
                </li>
                <li>
                    <i style="background:{{ $event->contract ? 'var(--vital-lit)' : 'var(--rim-hi)' }}"></i>
                    Contract<b>{{ $event->contract ? str($event->contract->status)->replace('_', ' ')->title() : '—' }}</b>
                </li>
                <li>
                    <i style="background:{{ $openRisks ? 'var(--critical-lit)' : 'var(--vital-lit)' }}"></i>
                    Open risks<b>{{ $openRisks }}</b>
                </li>
            </ul>
            <a href="{{ route('events.hub', [$event, 'tab' => 'files']) }}" class="o-btn o-btn--ghost o-btn--sm" style="margin-top:var(--o-4)">Open documents →</a>
        </x-orbit.card>
    </div>

    {{-- ══ RECENT ACTIVITY — the audit trail, kept from the previous overview ══ --}}
    @php $activity = $event->auditLogs()->with('user')->limit(8)->get(); @endphp
    @if ($activity->isNotEmpty())
        <x-orbit.card :pad="false">
            <x-slot:head>
                <h3 class="o-card__title">Recent Activity</h3>
                <span class="o-eyebrow">Audit trail</span>
            </x-slot:head>
            <x-orbit.feed style="padding:var(--o-3)">
                @foreach ($activity as $log)
                    <x-orbit.alert
                        :tone="match ($log->action) { 'deleted' => 'critical', 'created' => 'vital', default => 'ion' }"
                        :title="($log->user?->name ?? 'System').' '.(['created' => 'created', 'updated' => 'changed', 'deleted' => 'deleted'][$log->action] ?? $log->action).' '.$log->label"
                        :sub="$log->summary() ?: null"
                        :time="$log->created_at->diffForHumans(short: true)" />
                @endforeach
            </x-orbit.feed>
        </x-orbit.card>
    @endif
</div>
