@php
    $accent = $event->theme()['accent'];
    $openRisks = $event->risks->filter->isOpen();
    $riskWord = $openRisks->isEmpty() ? 'Low' : ($openRisks->max(fn ($r) => $r->severity()) >= 15 ? 'High' : 'Medium');
    $taskTotal = max($event->tasks->count(), 1);
    $done = $event->tasks->where('status', 'done')->count();
    $doing = $event->tasks->where('status', 'doing')->count();
    $todo = $event->tasks->whereIn('status', ['todo', 'review', 'approved'])->count();
    $estimated = $event->budgetItems->sum('estimated_cents');
    $actual = $event->budgetItems->sum('actual_cents');
    $budgetTotal = max($event->budget_cents, 1);
    $committed = max($estimated - $actual, 0);
    $remaining = max($event->budget_cents - $estimated, 0);
    // One definition of "used", shared with the Budget tab — see
    // Event::budgetUsedPct(). This tile used to divide what had been invoiced
    // by the cap and read 0% while the Budget tab read 100%.
    $budgetUsedPct = $event->budgetUsedPct();

    // Money must follow the event's currency — never a hard-coded "$".
    $sym = $event->currencySymbol();
    $gap = strlen($sym) > 1 ? ' ' : '';

    // ── Delivery status: the modules the overview was previously blind to ──
    $contract = $event->contract;
    $brief = $event->brief;
    $speakerCount = $event->speakers->count();
    $speakersConfirmed = $event->speakers->where('status', 'confirmed')->count();

    // The agenda card should open on the day that matters: the next upcoming day
    // that actually has delegate content — never a pure build day.
    $today = now()->startOfDay();
    $upcoming = $event->agendaDays->filter(fn ($d) => $d->date && $d->date->gte($today));
    $hasProgramme = fn ($d) => $d->sessions->contains(
        fn ($s) => ! in_array($s->track, \App\Services\AgendaProgram::CREW_ONLY, true)
    );
    $agendaDay = $upcoming->first($hasProgramme)
        ?? $upcoming->first()
        ?? $event->agendaDays->last();

    // A card that opens onto a module borrows that module's own colour — the
    // same one the hub nav dots and document folders already use — so the
    // overview reads as a set of doors rather than a wall of grey headings.
    $badge = fn (?string $key = null) => \App\Models\Event::moduleColor($key);
@endphp

{{-- Soft Command Event Hub Overview —
     Phase E removed the top block that used to live here: a second Mission
     Radar and a second event-health/readiness/operations/commercial grid,
     both repeating figures the Event Hub header's own hub card already
     shows (same health score, same readiness %). The wrapper that carried
     that block is gone with it — Row 1 below was always a sibling, not its
     child. Nothing in Row 1 is said anywhere else on the page.

     Phase 4.1 retokened this page onto eo-* — the design system Orbit
     Journey already sits on directly above it in hub.blade.php. Every value
     computed above is unchanged; only the chrome around it moved off the
     legacy .card/.pf/navy-900 palette. Per-module accent colours ($badge)
     and the workflow-driven status chips ($docChip/$readiness) are real
     data, not legacy classes, and are untouched. --}}

{{-- ══ Row 1: Event Overview · Agenda Overview · Live Alerts ══ --}}
<div class="grid gap-5 xl:grid-cols-3">

    <div class="eo-soft-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 text-base font-bold text-eo-text">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-eo-teal-soft text-eo-teal">
                    <x-icon name="home" class="h-4 w-4" />
                </span>
                Event Overview
            </h3>
            <x-eo.status-pill tone="live">
                {{ str($event->stage)->replace('_', ' ')->title() }}
            </x-eo.status-pill>
        </div>

        {{-- Lifecycle timeline --}}
        @php
            $stages = ['draft' => 'Lead', 'proposal' => 'Proposal', 'confirmed' => 'Confirmed', 'planning' => 'Planning', 'production' => 'Production', 'live' => 'Live', 'completed' => 'Completed'];
            $stageKeys = array_keys($stages);
            $currentIndex = array_search($event->stage, $stageKeys);
        @endphp
        <div class="flex items-center">
            @foreach ($stages as $key => $label)
                @php $i = array_search($key, $stageKeys); @endphp
                <div class="flex min-w-0 {{ $loop->last ? '' : 'flex-1' }} items-center">
                    <div class="flex flex-col items-center gap-1">
                        <span @class([
                                'flex items-center justify-center rounded-full',
                                'h-5 w-5 bg-gold-500 ring-4 ring-gold-100' => $i === $currentIndex,
                                'h-4 w-4 bg-eo-navy' => $currentIndex !== false && $i < $currentIndex,
                                'h-4 w-4 border-2 border-eo-line bg-white' => $currentIndex === false || $i > $currentIndex,
                            ])></span>
                        <span class="{{ $i === $currentIndex ? 'font-bold text-eo-text' : 'text-eo-muted' }} whitespace-nowrap text-[0.55rem]">{{ $label }}</span>
                    </div>
                    @unless ($loop->last)
                        <div class="mx-1 mb-4 h-px flex-1 {{ $currentIndex !== false && $i < $currentIndex ? 'bg-eo-navy' : 'bg-eo-line' }}"></div>
                    @endunless
                </div>
            @endforeach
        </div>

        {{-- Metrics row --}}
        <dl class="mt-5 grid grid-cols-3 gap-x-2 gap-y-4 border-t border-eo-line pt-4">
            @foreach ([
                ['icon' => 'users', 'value' => $event->expected_participants ? number_format($event->expected_participants) : '—', 'label' => 'Participants'],
                ['icon' => 'star', 'value' => $event->sponsors->count(), 'label' => 'Sponsors'],
                ['icon' => 'currency', 'value' => $budgetUsedPct !== null ? $budgetUsedPct.'%' : '—', 'label' => 'Budget Used'],
                ['icon' => 'clipboard', 'value' => $health['components']['tasks'] !== null ? $health['components']['tasks'].'%' : '—', 'label' => 'Tasks Done'],
                ['icon' => 'truck', 'value' => $health['components']['suppliers'] !== null ? $health['components']['suppliers'].'%' : '—', 'label' => 'Supplier Ready'],
            ] as $metric)
                <div class="flex flex-col items-center">
                    <dd class="flex items-center gap-1.5 text-sm font-bold text-eo-text">
                        <span class="text-eo-muted"><x-icon :name="$metric['icon']" class="h-3.5 w-3.5" /></span> {{ $metric['value'] }}
                    </dd>
                    <dt class="mt-0.5 text-3xs text-eo-muted">{{ $metric['label'] }}</dt>
                </div>
            @endforeach
            <div class="flex flex-col items-center">
                <dd>
                    <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-bold ring-1',
                            'bg-eo-ok-soft text-eo-text ring-eo-ok/30' => $riskWord === 'Low',
                            'bg-eo-warn-soft text-eo-text ring-eo-warn/30' => $riskWord === 'Medium',
                            'bg-eo-risk-soft text-eo-text ring-eo-risk/30' => $riskWord === 'High',
                        ])>{{ $riskWord }}</span>
                </dd>
                <dt class="mt-1 text-3xs text-eo-muted">Risk Level</dt>
            </div>
        </dl>

        @if ($health['critical_risk'])
            <p class="mt-4 rounded-xl bg-eo-risk-soft px-3.5 py-2 text-[0.68rem] font-medium text-eo-text ring-1 ring-eo-risk/30">
                Health capped at “At Risk”: {{ $health['critical_risk'] }}
            </p>
        @endif
    </div>

    <div class="eo-soft-card p-5">
        @php $firstDay = $agendaDay; @endphp
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 text-base font-bold text-eo-text">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('agenda') }}; background: {{ $badge('agenda') }}15">
                    <x-icon name="calendar" class="h-4 w-4" />
                </span>
                Upcoming Sessions
            </h3>
            @if ($firstDay)
                <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}"
                   class="rounded-xl border border-eo-line px-2.5 py-1 text-[0.62rem] font-semibold text-eo-text transition hover:border-gold-300">
                    {{ $firstDay->date->format('j M Y') }}
                </a>
            @endif
        </div>
        @if ($firstDay && $firstDay->sessions->isNotEmpty())
            <p class="-mt-2 mb-3 truncate text-[0.62rem] text-eo-muted">{{ $firstDay->label }}</p>
            <ul class="space-y-0">
                @foreach ($firstDay->sessions->sortBy('starts_at')->take(5) as $session)
                    <li class="relative flex items-center gap-3 pb-4 text-xs {{ $loop->last ? 'pb-0' : '' }}">
                        @unless ($loop->last)
                            <span class="absolute left-[3px] top-4 h-full w-px bg-eo-line"></span>
                        @endunless
                        <span class="relative h-[7px] w-[7px] shrink-0 rounded-full" style="background: {{ $loop->first ? '#3B82F6' : ($loop->iteration === 2 ? $accent : '#CBD5E1') }}"></span>
                        <span class="w-[5.5rem] shrink-0 font-semibold text-eo-text">{{ substr($session->starts_at, 0, 5) }} – {{ substr($session->ends_at, 0, 5) }}</span>
                        <span class="min-w-0 flex-1 truncate font-medium text-eo-text">{{ $session->title }}</span>
                        <span class="shrink-0 text-[0.62rem] text-eo-muted">{{ $session->room?->name }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" class="mt-4 block text-center text-xs font-semibold text-eo-teal-ink hover:underline">View Full Agenda →</a>
        @else
            <p class="text-xs text-eo-muted">No sessions yet — the agenda contributes 10% of the health score.</p>
        @endif
    </div>

    <div class="eo-soft-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 text-base font-bold text-eo-text">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('risks') }}; background: {{ $badge('risks') }}15">
                    <x-icon name="bell" class="h-4 w-4" />
                </span>
                Live Alerts
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'risks']) }}" class="text-[0.65rem] font-semibold text-eo-teal-ink hover:underline">View all</a>
        </div>
        <ul class="space-y-3.5">
            @forelse ($alerts as $alert)
                <li class="flex gap-2.5">
                    <span @class([
                            'mt-1 h-2 w-2 shrink-0 rounded-full',
                            'bg-eo-risk' => $alert['tone'] === 'risk',
                            'bg-eo-warn' => $alert['tone'] === 'warn',
                            'bg-eo-teal' => $alert['tone'] === 'info',
                        ])></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-eo-text">{{ $alert['title'] }}</p>
                        <p class="truncate text-[0.62rem] text-eo-muted">{{ $alert['sub'] }}</p>
                    </div>
                    <span class="shrink-0 text-3xs text-eo-muted">{{ $alert['when']?->diffForHumans(short: true) }}</span>
                </li>
            @empty
                <li class="text-xs text-eo-muted">All quiet — no active alerts.</li>
            @endforelse
        </ul>
    </div>
</div>

{{-- ══ Delivery doors: one job each — status, then open the module ══ --}}
@php
    use App\Support\Workflow;

    $docChip = function (?string $status): array {
        if (! $status) {
            return ['Not started', 'color: #94A3B8; background: #94A3B815; box-shadow: inset 0 0 0 1px #94A3B833'];
        }

        $hex = Workflow::color('contract_status', $status);

        return [
            Workflow::label('contract_status', $status),
            'color: '.$hex.'; background: '.$hex.'15; box-shadow: inset 0 0 0 1px '.$hex.'33',
        ];
    };

    [$contractLabel, $contractStyle] = $docChip($contract?->status);
    [$briefLabel, $briefStyle] = $brief ? ['Ready', $docChip('signed')[1]] : $docChip(null);

    // The workflow spine's own readiness read, module by module — "where am
    // I, what's left" rather than a general risk/task feed. Reuses the same
    // components EventHealthService already scored, so this never disagrees
    // with the health tile above it: null (no data yet) is Not started, a
    // full 100 is Ready, anything between is In progress. Transport gets its
    // own override — a movement flagged 'issue' is not "in progress", it is
    // the thing that needs a person right now.
    $readiness = function (?int $score, bool $flagged = false): array {
        if ($flagged) {
            return ['Needs attention', 'color: #DC2626; background: #DC262615; box-shadow: inset 0 0 0 1px #DC262633'];
        }
        if ($score === null) {
            return ['Not started', 'color: #94A3B8; background: #94A3B815; box-shadow: inset 0 0 0 1px #94A3B833'];
        }
        if ($score >= 100) {
            return ['Ready', 'color: #22C55E; background: #22C55E15; box-shadow: inset 0 0 0 1px #22C55E33'];
        }

        return ['In progress', 'color: #F59E0B; background: #F59E0B15; box-shadow: inset 0 0 0 1px #F59E0B33'];
    };

    [$agendaLabel, $agendaStyle] = $readiness($health['components']['agenda']);
    [$venueLabel, $venueStyle] = $readiness($health['components']['venue']);
    [$transportLabel, $transportStyle] = $readiness(
        $health['components']['transport'],
        $event->transport->contains(fn ($m) => $m->status === 'issue'),
    );
    // Registration has no health component of its own (see EventHealthService
    // weights) — the public form being open, or attendees already on the
    // list, is what "started" means here.
    $registeredCount = $event->attendees->count();
    $registrationScore = $event->registration_open ? 100 : ($registeredCount > 0 ? 50 : null);
    [$registrationLabel, $registrationStyle] = $readiness($registrationScore);
@endphp
<div class="mt-5 grid gap-3 sm:grid-cols-3">
    @if ($event->moduleEnabled('speakers'))
        <a href="{{ route('events.hub', [$event, 'tab' => 'speakers']) }}" class="eo-soft-card relative overflow-hidden px-4 py-3.5 transition hover:-translate-y-0.5">
            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('speakers') }}" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('speakers') }}; background: {{ $badge('speakers') }}15">
                    <x-icon name="sparkles" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Speakers</h3>
                <span class="ms-auto text-lg font-bold tabular-nums text-eo-text">{{ $speakerCount }}</span>
            </div>
            <p class="mt-1.5 text-[0.62rem] text-eo-muted">{{ $speakersConfirmed }} confirmed · {{ max($speakerCount - $speakersConfirmed, 0) }} pending</p>
        </a>
    @endif

    @if ($event->moduleEnabled('brief'))
        <a href="{{ route('events.hub', [$event, 'tab' => 'brief']) }}" class="eo-soft-card relative overflow-hidden px-4 py-3.5 transition hover:-translate-y-0.5">
            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('brief') }}" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('brief') }}; background: {{ $badge('brief') }}15">
                    <x-icon name="clipboard" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Brief</h3>
                <span class="ms-auto inline-flex rounded-full px-2 py-0.5 text-[0.62rem] font-bold" style="{{ $briefStyle }}">{{ $briefLabel }}</span>
            </div>
        </a>
    @endif

    @if ($event->moduleEnabled('agenda'))
        <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" class="eo-soft-card relative overflow-hidden px-4 py-3.5 transition hover:-translate-y-0.5">
            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('agenda') }}" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('agenda') }}; background: {{ $badge('agenda') }}15">
                    <x-icon name="calendar" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Agenda</h3>
                <span class="ms-auto inline-flex rounded-full px-2 py-0.5 text-[0.62rem] font-bold" style="{{ $agendaStyle }}">{{ $agendaLabel }}</span>
            </div>
            <p class="mt-1.5 text-[0.62rem] text-eo-muted">{{ $event->agendaSessions->count() }} {{ str('session')->plural($event->agendaSessions->count()) }}</p>
        </a>
    @endif

    @if ($event->moduleEnabled('venue'))
        <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}" class="eo-soft-card relative overflow-hidden px-4 py-3.5 transition hover:-translate-y-0.5">
            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('venue') }}" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('venue') }}; background: {{ $badge('venue') }}15">
                    <x-icon name="building" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Venue</h3>
                <span class="ms-auto inline-flex rounded-full px-2 py-0.5 text-[0.62rem] font-bold" style="{{ $venueStyle }}">{{ $venueLabel }}</span>
            </div>
            <p class="mt-1.5 truncate text-[0.62rem] text-eo-muted">{{ $event->venue?->name ?? 'No venue selected' }}</p>
        </a>
    @endif

    @if ($event->moduleEnabled('transportation'))
        <a href="{{ route('events.hub', [$event, 'tab' => 'transportation']) }}" class="eo-soft-card relative overflow-hidden px-4 py-3.5 transition hover:-translate-y-0.5">
            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('transportation') }}" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('transportation') }}; background: {{ $badge('transportation') }}15">
                    <x-icon name="truck" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Transport</h3>
                <span class="ms-auto inline-flex rounded-full px-2 py-0.5 text-[0.62rem] font-bold" style="{{ $transportStyle }}">{{ $transportLabel }}</span>
            </div>
            <p class="mt-1.5 text-[0.62rem] text-eo-muted">{{ $event->transport->count() }} {{ str('movement')->plural($event->transport->count()) }}</p>
        </a>
    @endif

    @if ($event->moduleEnabled('contract'))
        <a href="{{ route('events.hub', [$event, 'tab' => 'contract']) }}" class="eo-soft-card relative overflow-hidden px-4 py-3.5 transition hover:-translate-y-0.5">
            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('contract') }}" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('contract') }}; background: {{ $badge('contract') }}15">
                    <x-icon name="identification" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Contract</h3>
                <span class="ms-auto inline-flex rounded-full px-2 py-0.5 text-[0.62rem] font-bold" style="{{ $contractStyle }}">{{ $contractLabel }}</span>
            </div>
            @if ($contract)
                <p class="mt-1.5 truncate text-[0.62rem] text-eo-muted">
                    {{ $contract->reference }}
                    · {{ $sym }}{{ $gap }}{{ number_format(($contract->data['financials']['estimated_total_cents'] ?? 0) / 100) }}
                </p>
            @endif
        </a>
    @endif

    @if ($event->moduleEnabled('attendees'))
        <a href="{{ route('events.hub', [$event, 'tab' => 'attendees']) }}" class="eo-soft-card relative overflow-hidden px-4 py-3.5 transition hover:-translate-y-0.5">
            <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('attendees') }}" aria-hidden="true"></span>
            <div class="flex items-center gap-2">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('attendees') }}; background: {{ $badge('attendees') }}15">
                    <x-icon name="users" class="h-3.5 w-3.5" />
                </span>
                <h3 class="text-sm font-bold text-eo-text">Registration</h3>
                <span class="ms-auto inline-flex rounded-full px-2 py-0.5 text-[0.62rem] font-bold" style="{{ $registrationStyle }}">{{ $registrationLabel }}</span>
            </div>
            <p class="mt-1.5 text-[0.62rem] text-eo-muted">{{ $registeredCount }} registered</p>
        </a>
    @endif
</div>

{{-- ══ Row 2: Tasks · Budget · Top Suppliers · Team Workload ══ --}}
<div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-4">

    <div class="eo-soft-card p-5">
        <h3 class="mb-4 flex items-center gap-2.5 text-base font-bold text-eo-text">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('tasks') }}; background: {{ $badge('tasks') }}15">
                <x-icon name="clipboard" class="h-4 w-4" />
            </span>
            Tasks Summary
        </h3>
        <div class="flex items-center gap-4">
            <x-donut :segments="[
                ['pct' => $done / $taskTotal * 100, 'class' => 'stroke-eo-ok'],
                ['pct' => $doing / $taskTotal * 100, 'class' => 'stroke-eo-warn'],
                ['pct' => $todo / $taskTotal * 100, 'class' => 'stroke-eo-risk'],
            ]" size="h-28 w-28" class="shrink-0">
                <span class="text-xl font-bold text-eo-text">{{ $event->tasks->count() }}</span>
                <span class="text-[0.55rem] text-eo-muted">Total Tasks</span>
            </x-donut>
            <ul class="space-y-2 text-[0.68rem]">
                @foreach ([
                    ['Completed', $done, 'bg-eo-ok'], ['In Progress', $doing, 'bg-eo-warn'], ['Pending', $todo, 'bg-eo-risk'],
                ] as [$label, $count, $dot])
                    <li class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                        <span class="text-eo-muted">{{ $label }}</span>
                        <span class="font-bold text-eo-text">{{ $count }} ({{ round($count / $taskTotal * 100) }}%)</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <a href="{{ route('events.hub', [$event, 'tab' => 'tasks']) }}" class="mt-3 block text-center text-xs font-semibold text-eo-teal-ink hover:underline">View All Tasks →</a>
    </div>

    <div class="eo-soft-card p-5">
        <h3 class="mb-4 flex items-center gap-2.5 text-base font-bold text-eo-text">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('budget') }}; background: {{ $badge('budget') }}15">
                <x-icon name="currency" class="h-4 w-4" />
            </span>
            Budget Summary
        </h3>
        <div class="flex items-center gap-4">
            <x-donut :segments="[
                ['pct' => min($actual / $budgetTotal * 100, 100), 'class' => 'stroke-eo-teal'],
                ['pct' => min($committed / $budgetTotal * 100, max(100 - $actual / $budgetTotal * 100, 0)), 'class' => 'stroke-eo-warn'],
                ['pct' => min($remaining / $budgetTotal * 100, max(100 - ($actual + $committed) / $budgetTotal * 100, 0)), 'class' => 'stroke-eo-ok'],
            ]" size="h-28 w-28" class="shrink-0">
                <span class="text-sm font-bold text-eo-text">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($event->budget_cents / 100) }}</span>
                <span class="text-[0.55rem] text-eo-muted">Total Budget</span>
            </x-donut>
            <ul class="space-y-2 text-[0.68rem]">
                @foreach ([
                    ['Spent', $actual, 'bg-eo-teal'], ['Committed', $committed, 'bg-eo-warn'], ['Remaining', $remaining, 'bg-eo-ok'],
                ] as [$label, $cents, $dot])
                    <li class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                        <span class="text-eo-muted">{{ $label }}</span>
                        <span class="font-bold text-eo-text">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($cents / 100) }} ({{ round($cents / $budgetTotal * 100) }}%)</span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- P&L strip: income (client contract + sponsors + exhibitors + other) vs cost --}}
        @php $inc = $event->incomeSummary(); $net = $inc['total'] - $estimated; @endphp
        <div class="mt-3 grid grid-cols-3 gap-2 border-t border-eo-line pt-3 text-center">
            <div>
                <p class="text-[0.55rem] font-bold uppercase tracking-wide text-eo-muted">Income</p>
                <p class="text-xs font-black text-eo-ok">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($inc['total'] / 100) }}</p>
            </div>
            <div>
                <p class="text-[0.55rem] font-bold uppercase tracking-wide text-eo-muted">Cost (est.)</p>
                <p class="text-xs font-black text-eo-text">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($estimated / 100) }}</p>
            </div>
            <div>
                <p class="text-[0.55rem] font-bold uppercase tracking-wide text-eo-muted">Net</p>
                <p class="text-xs font-black {{ $net < 0 ? 'text-eo-risk' : 'text-eo-ok' }}">{{ $net < 0 ? '−' : '+' }}{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate(abs($net) / 100) }}</p>
            </div>
        </div>

        <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" class="mt-3 block text-center text-xs font-semibold text-eo-teal-ink hover:underline">View Budget Details →</a>
    </div>

    <div class="eo-soft-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 text-base font-bold text-eo-text">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('suppliers') }}; background: {{ $badge('suppliers') }}15">
                    <x-icon name="truck" class="h-4 w-4" />
                </span>
                Top Suppliers
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'suppliers']) }}" class="text-[0.65rem] font-semibold text-eo-teal-ink hover:underline">View all</a>
        </div>
        <ul class="divide-y divide-eo-line">
            @forelse ($event->suppliers->sortByDesc('rating')->take(4) as $supplier)
                <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-eo-workspace text-[0.65rem] font-bold text-eo-text">{{ str($supplier->name)->substr(0, 1) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-semibold text-eo-text">{{ $supplier->name }}</span>
                        <span class="block text-[0.62rem] text-eo-muted">{{ str($supplier->category)->replace('_', ' & ')->title() }}</span>
                    </span>
                    <span class="shrink-0 text-xs font-bold text-gold-600">★ {{ number_format($supplier->rating, 1) }}</span>
                </li>
            @empty
                <li class="py-2 text-xs text-eo-muted">No suppliers assigned yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="eo-soft-card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 text-base font-bold text-eo-text">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-eo-workspace text-eo-muted">
                    <x-icon name="users" class="h-4 w-4" />
                </span>
                Team Workload
            </h3>
            <a href="{{ route('team.index') }}" class="text-[0.65rem] font-semibold text-eo-teal-ink hover:underline">View team</a>
        </div>
        <ul class="space-y-3.5">
            @forelse ($workload as $row)
                <li class="flex items-center gap-2.5">
                    <x-user-avatar :user="$row['user']" size="h-8 w-8" text="text-3xs" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-xs font-semibold text-eo-text">{{ $row['user']->name }}</p>
                            <span class="text-[0.65rem] font-bold text-eo-text">{{ $row['pct'] }}%</span>
                        </div>
                        <p class="truncate text-3xs text-eo-muted">{{ str($row['user']->pivot->role)->replace('_', ' ')->title() }}</p>
                        <div class="mt-1 h-1 overflow-hidden rounded-full bg-eo-workspace">
                            <div @class(['h-full rounded-full', 'bg-eo-risk' => $row['pct'] >= 80, 'bg-eo-warn' => $row['pct'] >= 60 && $row['pct'] < 80, 'bg-eo-ok' => $row['pct'] < 60]) style="width: {{ $row['pct'] }}%"></div>
                        </div>
                    </div>
                </li>
            @empty
                <li class="text-xs text-eo-muted">No team assigned yet.</li>
            @endforelse
        </ul>
    </div>
</div>

{{-- ══ Deadlines · Attention · Activity ══ --}}
<div class="mt-5 grid gap-5 xl:grid-cols-3">
    <div class="eo-soft-card p-4 xl:col-span-2">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="flex items-center gap-2 text-sm font-bold text-eo-text">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('tasks') }}; background: {{ $badge('tasks') }}15">
                    <x-icon name="calendar" class="h-3.5 w-3.5" />
                </span>
                Upcoming deadlines
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'tasks']) }}" class="text-[0.65rem] font-semibold text-eo-teal-ink hover:underline">All tasks →</a>
        </div>
        <div class="divide-y divide-eo-line">
            @forelse ($event->tasks->where('status', '!=', 'done')->whereNotNull('due_on')->sortBy('due_on')->take(5) as $task)
                @php $days = (int) now()->startOfDay()->diffInDays($task->due_on, false); @endphp
                <div class="flex items-center gap-3 py-2 first:pt-0 last:pb-0">
                    <span class="w-12 shrink-0 text-center">
                        <span class="block text-[0.55rem] font-bold uppercase text-gold-600">{{ $task->due_on->format('M') }}</span>
                        <span class="block text-sm font-bold leading-none text-eo-text">{{ $task->due_on->format('d') }}</span>
                    </span>
                    <span class="min-w-0 flex-1 truncate text-xs font-semibold text-eo-text">{{ $task->title }}</span>
                    <span class="shrink-0 text-3xs font-semibold {{ $days < 3 ? 'text-eo-risk' : 'text-eo-muted' }}">
                        {{ $days < 0 ? abs($days).'d overdue' : ($days === 0 ? 'today' : $days.'d left') }}
                    </span>
                </div>
            @empty
                <p class="text-xs text-eo-muted">No open deadlines.</p>
            @endforelse
        </div>
    </div>

    <div class="eo-soft-card p-4">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="flex items-center gap-2 text-sm font-bold text-eo-text">
                <span class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-gold-50 text-gold-600">
                    <x-icon name="sparkles" class="h-3.5 w-3.5" />
                </span>
                Needs attention
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'ai']) }}" class="text-[0.65rem] font-semibold text-eo-teal-ink hover:underline">AI →</a>
        </div>
        <ul class="space-y-2 text-xs text-eo-text">
            @forelse (array_slice($ai['attention'] ?? [], 0, 4) as $point)
                <li class="flex gap-2"><span class="mt-1.5 h-1 w-1 shrink-0 rounded-full bg-gold-500"></span> {{ $point }}</li>
            @empty
                <li class="text-eo-muted">No blockers detected.</li>
            @endforelse
        </ul>
    </div>
</div>

@php $activity = $event->auditLogs()->with('user')->limit(6)->get(); @endphp
@if ($activity->isNotEmpty())
    <div class="mt-5 eo-soft-card p-4">
        <div class="mb-2 flex items-center justify-between">
            <h3 class="text-sm font-bold text-eo-text">Recent activity</h3>
            <span class="text-3xs font-bold uppercase tracking-[0.14em] text-eo-muted">Audit</span>
        </div>
        <ul class="divide-y divide-eo-line">
            @foreach ($activity as $log)
                <li class="flex items-start gap-2.5 py-2 text-xs first:pt-0 last:pb-0">
                    @if ($log->user)<x-user-avatar :user="$log->user" size="h-5 w-5" text="text-[0.45rem]" />@endif
                    <span class="min-w-0 flex-1">
                        <span class="font-semibold text-eo-text">{{ $log->user?->name ?? 'System' }}</span>
                        <span class="text-eo-muted">{{ ['created' => 'created', 'updated' => 'changed', 'deleted' => 'deleted'][$log->action] ?? $log->action }}</span>
                        <span class="font-semibold text-eo-text">{{ $log->label }}</span>
                    </span>
                    <span class="shrink-0 text-[0.62rem] text-eo-muted">{{ $log->created_at->diffForHumans(short: true) }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
