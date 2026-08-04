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

{{-- ══ Row 1: Event Overview · Agenda Overview · Live Alerts ══ --}}
<div class="grid gap-5 xl:grid-cols-3">

    <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge() }}; background: {{ $badge() }}15">
                    <x-icon name="home" class="h-4 w-4" />
                </span>
                Event Overview
            </h3>
            <span class="rounded-full bg-gold-50 px-2.5 py-1 text-[0.62rem] font-bold text-gold-700 ring-1 ring-gold-200">
                ✦ {{ str($event->stage)->replace('_', ' ')->title() }} Phase
            </span>
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
                                'h-4 w-4 bg-navy-900' => $currentIndex !== false && $i < $currentIndex,
                                'h-4 w-4 border-2 border-navy-100 bg-white' => $currentIndex === false || $i > $currentIndex,
                            ])></span>
                        <span class="{{ $i === $currentIndex ? 'font-bold text-navy-900' : 'text-muted' }} whitespace-nowrap text-[0.55rem]">{{ $label }}</span>
                    </div>
                    @unless ($loop->last)
                        <div class="mx-1 mb-4 h-px flex-1 {{ $currentIndex !== false && $i < $currentIndex ? 'bg-navy-900' : 'bg-line' }}"></div>
                    @endunless
                </div>
            @endforeach
        </div>

        {{-- Metrics row --}}
        <dl class="mt-5 grid grid-cols-3 gap-x-2 gap-y-4 border-t border-line pt-4">
            @foreach ([
                ['icon' => 'users', 'value' => $event->expected_participants ? number_format($event->expected_participants) : '—', 'label' => 'Participants'],
                ['icon' => 'star', 'value' => $event->sponsors->count(), 'label' => 'Sponsors'],
                ['icon' => 'currency', 'value' => $budgetUsedPct !== null ? $budgetUsedPct.'%' : '—', 'label' => 'Budget Used'],
                ['icon' => 'clipboard', 'value' => $health['components']['tasks'] !== null ? $health['components']['tasks'].'%' : '—', 'label' => 'Tasks Done'],
                ['icon' => 'truck', 'value' => $health['components']['suppliers'] !== null ? $health['components']['suppliers'].'%' : '—', 'label' => 'Supplier Ready'],
            ] as $metric)
                <div class="flex flex-col items-center">
                    <dd class="flex items-center gap-1.5 text-sm font-bold text-navy-900">
                        <span class="text-navy-400"><x-icon :name="$metric['icon']" class="h-3.5 w-3.5" /></span> {{ $metric['value'] }}
                    </dd>
                    <dt class="mt-0.5 text-3xs text-muted">{{ $metric['label'] }}</dt>
                </div>
            @endforeach
            <div class="flex flex-col items-center">
                <dd>
                    <span @class([
                            'inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[0.65rem] font-bold ring-1',
                            'bg-track/10 text-emerald-700 ring-track/30' => $riskWord === 'Low',
                            'bg-warn/10 text-amber-700 ring-warn/30' => $riskWord === 'Medium',
                            'bg-risk/10 text-red-700 ring-risk/30' => $riskWord === 'High',
                        ])>✓ {{ $riskWord }}</span>
                </dd>
                <dt class="mt-1 text-3xs text-muted">Risk Level</dt>
            </div>
        </dl>

        @if ($health['critical_risk'])
            <p class="mt-4 rounded-xl bg-risk/10 px-3.5 py-2 text-[0.68rem] font-medium text-red-700 ring-1 ring-risk/30">
                ⚠ Health capped at "At Risk": {{ $health['critical_risk'] }}
            </p>
        @endif
    </div>

    <div class="card p-5">
        @php $firstDay = $agendaDay; @endphp
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('agenda') }}; background: {{ $badge('agenda') }}15">
                    <x-icon name="calendar" class="h-4 w-4" />
                </span>
                Agenda Overview
            </h3>
            @if ($firstDay)
                <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}"
                   class="rounded-xl border border-line px-2.5 py-1 text-[0.62rem] font-semibold text-navy-700 transition hover:border-gold-300">
                    {{ $firstDay->date->format('j M Y') }}
                </a>
            @endif
        </div>
        @if ($firstDay && $firstDay->sessions->isNotEmpty())
            <p class="-mt-2 mb-3 truncate text-[0.62rem] text-muted">{{ $firstDay->label }}</p>
            <ul class="space-y-0">
                @foreach ($firstDay->sessions->sortBy('starts_at')->take(5) as $session)
                    <li class="relative flex items-center gap-3 pb-4 text-xs {{ $loop->last ? 'pb-0' : '' }}">
                        @unless ($loop->last)
                            <span class="absolute left-[3px] top-4 h-full w-px bg-line"></span>
                        @endunless
                        <span class="relative h-[7px] w-[7px] shrink-0 rounded-full" style="background: {{ $loop->first ? '#3B82F6' : ($loop->iteration === 2 ? $accent : '#CBD5E1') }}"></span>
                        <span class="w-[5.5rem] shrink-0 font-semibold text-navy-900">{{ substr($session->starts_at, 0, 5) }} – {{ substr($session->ends_at, 0, 5) }}</span>
                        <span class="min-w-0 flex-1 truncate font-medium text-navy-800">{{ $session->title }}</span>
                        <span class="shrink-0 text-[0.62rem] text-muted">{{ $session->room?->name }}</span>
                    </li>
                @endforeach
            </ul>
            <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" class="mt-4 block text-center text-xs font-semibold text-[#3B82F6] hover:underline">View Full Agenda →</a>
        @else
            <p class="text-xs text-muted">No sessions yet — the agenda contributes 10% of the health score.</p>
        @endif
    </div>

    <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('risks') }}; background: {{ $badge('risks') }}15">
                    <x-icon name="bell" class="h-4 w-4" />
                </span>
                Live Alerts
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'risks']) }}" class="text-[0.65rem] font-semibold text-[#3B82F6] hover:underline">View all</a>
        </div>
        <ul class="space-y-3.5">
            @forelse ($alerts as $alert)
                <li class="flex gap-2.5">
                    <span @class([
                            'mt-1 h-2 w-2 shrink-0 rounded-full',
                            'bg-risk' => $alert['tone'] === 'risk',
                            'bg-warn' => $alert['tone'] === 'warn',
                            'bg-[#3B82F6]' => $alert['tone'] === 'info',
                        ])></span>
                    <div class="min-w-0 flex-1">
                        <p class="truncate text-xs font-semibold text-navy-900">{{ $alert['title'] }}</p>
                        <p class="truncate text-[0.62rem] text-muted">{{ $alert['sub'] }}</p>
                    </div>
                    <span class="shrink-0 text-3xs text-muted">{{ $alert['when']?->diffForHumans(short: true) }}</span>
                </li>
            @empty
                <li class="text-xs text-muted">All quiet — no active alerts. 🎉</li>
            @endforelse
        </ul>
    </div>
</div>

{{-- ══ Row 1b: Delivery status — brief, contract, plan and speakers at a glance ══ --}}
@php
    use App\Support\Workflow;

    // The document's status, named and coloured by the one list that owns it
    // — Workflow::rows('contract_status'), which is what Settings renames and
    // recolours. This used to be a copy of that list living here, and it was
    // missing two of the five statuses: a partly-signed contract took the whole
    // Overview tab down with "Undefined array key".
    //
    // The colour is a hex from the list, so a status added later arrives with
    // its own colour instead of needing a class written for it here.
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
@endphp
<div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-3">

    {{-- Speakers --}}
    <a href="{{ route('events.hub', [$event, 'tab' => 'speakers']) }}" class="op-card p-5">
        <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('speakers') }}" aria-hidden="true"></span>
        <div class="flex items-center justify-between">
            <h3 class="flex items-center gap-2 pf text-sm font-bold text-navy-900">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('speakers') }}; background: {{ $badge('speakers') }}15">
                    <x-icon name="sparkles" class="h-3.5 w-3.5" />
                </span>
                Speakers
            </h3>
            <span class="text-3xs font-bold text-muted">roster</span>
        </div>
        <p class="mt-3 text-2xl font-bold text-navy-900">{{ $speakerCount }}</p>
        <p class="mt-2 text-[0.62rem] text-muted">
            {{ $speakersConfirmed }} confirmed · {{ max($speakerCount - $speakersConfirmed, 0) }} pending
        </p>
    </a>

    {{-- Brief --}}
    <a href="{{ route('events.hub', [$event, 'tab' => 'brief']) }}" class="op-card p-5">
        <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('brief') }}" aria-hidden="true"></span>
        <div class="flex items-center justify-between">
            <h3 class="flex items-center gap-2 pf text-sm font-bold text-navy-900">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('brief') }}; background: {{ $badge('brief') }}15">
                    <x-icon name="clipboard" class="h-3.5 w-3.5" />
                </span>
                Event Brief
            </h3>
        </div>
        <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[0.62rem] font-bold" style="{{ $briefStyle }}">{{ $briefLabel }}</span>
        <p class="mt-2 text-[0.62rem] text-muted">The front-door document that drives the plan.</p>
    </a>

    {{-- Contract --}}
    <a href="{{ route('events.hub', [$event, 'tab' => 'contract']) }}" class="op-card p-5">
        <span class="absolute inset-y-0 start-0 w-[3px]" style="background: {{ $badge('contract') }}" aria-hidden="true"></span>
        <div class="flex items-center justify-between">
            <h3 class="flex items-center gap-2 pf text-sm font-bold text-navy-900">
                <span class="grid h-6 w-6 shrink-0 place-items-center rounded-lg" style="color: {{ $badge('contract') }}; background: {{ $badge('contract') }}15">
                    <x-icon name="identification" class="h-3.5 w-3.5" />
                </span>
                Contract
            </h3>
            @if ($contract)<span class="text-3xs font-bold text-muted">{{ $contract->reference }}</span>@endif
        </div>
        <span class="mt-3 inline-flex rounded-full px-2.5 py-1 text-[0.62rem] font-bold" style="{{ $contractStyle }}">{{ $contractLabel }}</span>
        <p class="mt-2 text-[0.62rem] text-muted">
            @if ($contract)
                {{ $sym }}{{ $gap }}{{ number_format(($contract->data['financials']['estimated_total_cents'] ?? 0) / 100) }} estimated
                @php $cpCollected = $contract->payments->sum('paid_cents'); @endphp
                @if ($cpCollected > 0)· <b class="text-emerald-700">{{ $sym }}{{ $gap }}{{ number_format($cpCollected / 100) }} collected</b>@endif
            @else Not generated yet. @endif
        </p>
    </a>
</div>

{{-- ══ Row 2: Tasks · Budget · Top Suppliers · Team Workload ══ --}}
<div class="mt-5 grid gap-5 md:grid-cols-2 xl:grid-cols-4">

    <div class="card p-5">
        <h3 class="mb-4 flex items-center gap-2.5 pf text-base font-bold text-navy-900">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('tasks') }}; background: {{ $badge('tasks') }}15">
                <x-icon name="clipboard" class="h-4 w-4" />
            </span>
            Tasks Summary
        </h3>
        <div class="flex items-center gap-4">
            <x-donut :segments="[
                ['pct' => $done / $taskTotal * 100, 'class' => 'stroke-track'],
                ['pct' => $doing / $taskTotal * 100, 'class' => 'stroke-warn'],
                ['pct' => $todo / $taskTotal * 100, 'class' => 'stroke-risk'],
            ]" size="h-28 w-28" class="shrink-0">
                <span class="text-xl font-bold text-navy-900">{{ $event->tasks->count() }}</span>
                <span class="text-[0.55rem] text-muted">Total Tasks</span>
            </x-donut>
            <ul class="space-y-2 text-[0.68rem]">
                @foreach ([
                    ['Completed', $done, 'bg-track'], ['In Progress', $doing, 'bg-warn'], ['Pending', $todo, 'bg-risk'],
                ] as [$label, $count, $dot])
                    <li class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                        <span class="text-muted">{{ $label }}</span>
                        <span class="font-bold text-navy-900">{{ $count }} ({{ round($count / $taskTotal * 100) }}%)</span>
                    </li>
                @endforeach
            </ul>
        </div>
        <a href="{{ route('events.hub', [$event, 'tab' => 'tasks']) }}" class="mt-3 block text-center text-xs font-semibold text-[#3B82F6] hover:underline">View All Tasks →</a>
    </div>

    <div class="card p-5">
        <h3 class="mb-4 flex items-center gap-2.5 pf text-base font-bold text-navy-900">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('budget') }}; background: {{ $badge('budget') }}15">
                <x-icon name="currency" class="h-4 w-4" />
            </span>
            Budget Summary
        </h3>
        <div class="flex items-center gap-4">
            <x-donut :segments="[
                ['pct' => min($actual / $budgetTotal * 100, 100), 'class' => 'stroke-[#3B82F6]'],
                ['pct' => min($committed / $budgetTotal * 100, max(100 - $actual / $budgetTotal * 100, 0)), 'class' => 'stroke-warn'],
                ['pct' => min($remaining / $budgetTotal * 100, max(100 - ($actual + $committed) / $budgetTotal * 100, 0)), 'class' => 'stroke-track'],
            ]" size="h-28 w-28" class="shrink-0">
                <span class="text-sm font-bold text-navy-900">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($event->budget_cents / 100) }}</span>
                <span class="text-[0.55rem] text-muted">Total Budget</span>
            </x-donut>
            <ul class="space-y-2 text-[0.68rem]">
                @foreach ([
                    ['Spent', $actual, 'bg-[#3B82F6]'], ['Committed', $committed, 'bg-warn'], ['Remaining', $remaining, 'bg-track'],
                ] as [$label, $cents, $dot])
                    <li class="flex items-center gap-1.5">
                        <span class="h-2 w-2 rounded-full {{ $dot }}"></span>
                        <span class="text-muted">{{ $label }}</span>
                        <span class="font-bold text-navy-900">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($cents / 100) }} ({{ round($cents / $budgetTotal * 100) }}%)</span>
                    </li>
                @endforeach
            </ul>
        </div>

        {{-- P&L strip: income (client contract + sponsors + exhibitors + other) vs cost --}}
        @php $inc = $event->incomeSummary(); $net = $inc['total'] - $estimated; @endphp
        <div class="mt-3 grid grid-cols-3 gap-2 border-t border-line pt-3 text-center">
            <div>
                <p class="text-[0.55rem] font-bold uppercase tracking-wide text-muted">Income</p>
                <p class="text-xs font-black text-emerald-700">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($inc['total'] / 100) }}</p>
            </div>
            <div>
                <p class="text-[0.55rem] font-bold uppercase tracking-wide text-muted">Cost (est.)</p>
                <p class="text-xs font-black text-navy-900">{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate($estimated / 100) }}</p>
            </div>
            <div>
                <p class="text-[0.55rem] font-bold uppercase tracking-wide text-muted">Net</p>
                <p class="text-xs font-black {{ $net < 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $net < 0 ? '−' : '+' }}{{ $sym }}{{ $gap }}{{ \Illuminate\Support\Number::abbreviate(abs($net) / 100) }}</p>
            </div>
        </div>

        <a href="{{ route('events.hub', [$event, 'tab' => 'budget']) }}" class="mt-3 block text-center text-xs font-semibold text-[#3B82F6] hover:underline">View Budget Details →</a>
    </div>

    <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('suppliers') }}; background: {{ $badge('suppliers') }}15">
                    <x-icon name="truck" class="h-4 w-4" />
                </span>
                Top Suppliers
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'suppliers']) }}" class="text-[0.65rem] font-semibold text-[#3B82F6] hover:underline">View all</a>
        </div>
        <ul class="divide-y divide-line">
            @forelse ($event->suppliers->sortByDesc('rating')->take(4) as $supplier)
                <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                    <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-[0.65rem] font-bold text-navy-700">{{ str($supplier->name)->substr(0, 1) }}</span>
                    <span class="min-w-0 flex-1">
                        <span class="block truncate text-xs font-semibold text-navy-900">{{ $supplier->name }}</span>
                        <span class="block text-[0.62rem] text-muted">{{ str($supplier->category)->replace('_', ' & ')->title() }}</span>
                    </span>
                    <span class="shrink-0 text-xs font-bold text-gold-600">★ {{ number_format($supplier->rating, 1) }}</span>
                </li>
            @empty
                <li class="py-2 text-xs text-muted">No suppliers assigned yet.</li>
            @endforelse
        </ul>
    </div>

    <div class="card p-5">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-500">
                    <x-icon name="users" class="h-4 w-4" />
                </span>
                Team Workload
            </h3>
            <a href="{{ route('team.index') }}" class="text-[0.65rem] font-semibold text-[#3B82F6] hover:underline">View team</a>
        </div>
        <ul class="space-y-3.5">
            @forelse ($workload as $row)
                <li class="flex items-center gap-2.5">
                    <x-user-avatar :user="$row['user']" size="h-8 w-8" text="text-3xs" />
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center justify-between gap-2">
                            <p class="truncate text-xs font-semibold text-navy-900">{{ $row['user']->name }}</p>
                            <span class="text-[0.65rem] font-bold text-navy-900">{{ $row['pct'] }}%</span>
                        </div>
                        <p class="truncate text-3xs text-muted">{{ str($row['user']->pivot->role)->replace('_', ' ')->title() }}</p>
                        <div class="mt-1 h-1 overflow-hidden rounded-full bg-navy-50">
                            <div @class(['h-full rounded-full', 'bg-risk' => $row['pct'] >= 80, 'bg-warn' => $row['pct'] >= 60 && $row['pct'] < 80, 'bg-track' => $row['pct'] < 60]) style="width: {{ $row['pct'] }}%"></div>
                        </div>
                    </div>
                </li>
            @empty
                <li class="text-xs text-muted">No team assigned yet.</li>
            @endforelse
        </ul>
    </div>
</div>

{{-- ══ Row 3: Upcoming Deadlines · AI Recommendations ══ --}}
<div class="mt-5 grid gap-5 xl:grid-cols-3">

    <div class="card p-5 xl:col-span-2">
        <div class="mb-4 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $badge('tasks') }}; background: {{ $badge('tasks') }}15">
                    <x-icon name="calendar" class="h-4 w-4" />
                </span>
                Upcoming Deadlines
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'tasks']) }}" class="text-[0.65rem] font-semibold text-[#3B82F6] hover:underline">View all</a>
        </div>
        <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-5">
            @forelse ($event->tasks->where('status', '!=', 'done')->whereNotNull('due_on')->sortBy('due_on')->take(5) as $task)
                @php $days = (int) now()->startOfDay()->diffInDays($task->due_on, false); @endphp
                <div class="flex items-start gap-2.5 rounded-2xl border border-line px-3 py-3">
                    <span class="flex shrink-0 flex-col items-center rounded-xl border border-line px-2 py-1">
                        <span class="text-[0.55rem] font-bold uppercase text-gold-600">{{ $task->due_on->format('M') }}</span>
                        <span class="text-sm font-bold text-navy-900">{{ $task->due_on->format('d') }}</span>
                    </span>
                    <span class="min-w-0">
                        <span class="line-clamp-2 text-[0.68rem] font-semibold leading-snug text-navy-900">{{ $task->title }}</span>
                        <span class="mt-0.5 block text-3xs font-semibold {{ $days < 3 ? 'text-risk' : 'text-muted' }}">
                            {{ $days < 0 ? abs($days).' days overdue' : ($days === 0 ? 'due today' : $days.' days left') }}
                        </span>
                    </span>
                </div>
            @empty
                <p class="col-span-full text-xs text-muted">No open deadlines.</p>
            @endforelse
        </div>
    </div>

    <div class="card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="flex items-center gap-2.5 pf text-base font-bold text-navy-900">
                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-gold-50 text-gold-600">
                    <x-icon name="sparkles" class="h-4 w-4" />
                </span>
                AI Recommendations
            </h3>
            <a href="{{ route('events.hub', [$event, 'tab' => 'ai']) }}" class="text-[0.65rem] font-semibold text-[#3B82F6] hover:underline">View all</a>
        </div>
        <ul class="space-y-2.5 text-xs text-navy-800">
            @foreach (array_slice($ai['attention'], 0, 3) as $point)
                <li class="flex gap-2"><span class="mt-0.5 text-gold-600">◎</span> {{ $point }}</li>
            @endforeach
            @if (($health['components']['budget'] ?? 0) >= 81)
                <li class="flex gap-2"><span class="mt-0.5 text-emerald-600">✓</span> Budget is healthy. Keep monitoring production costs.</li>
            @endif
            @if (empty($ai['attention']))
                <li class="flex gap-2"><span class="mt-0.5 text-emerald-600">✓</span> No blockers detected — keep executing the plan.</li>
            @endif
        </ul>
    </div>
</div>

{{-- ══ Row 4: Audit trail — every decision has an author ══ --}}
@php $activity = $event->auditLogs()->with('user')->limit(8)->get(); @endphp
@if ($activity->isNotEmpty())
    <div class="mt-5 card p-5">
        <div class="mb-3 flex items-center justify-between">
            <h3 class="pf text-base font-bold text-navy-900">Recent Activity</h3>
            <span class="text-3xs font-bold uppercase tracking-[0.14em] text-muted">Audit trail</span>
        </div>
        <ul class="divide-y divide-line">
            @foreach ($activity as $log)
                <li class="flex items-start gap-2.5 py-2 text-xs first:pt-0 last:pb-0">
                    @if ($log->user)<x-user-avatar :user="$log->user" size="h-5 w-5" text="text-[0.45rem]" />@endif
                    <span class="min-w-0 flex-1">
                        <span class="font-semibold text-navy-900">{{ $log->user?->name ?? 'System' }}</span>
                        <span class="text-muted">{{ ['created' => 'created', 'updated' => 'changed', 'deleted' => 'deleted'][$log->action] }}</span>
                        <span class="font-semibold text-navy-800">{{ $log->label }}</span>
                        @if ($log->summary())
                            <span class="block truncate text-[0.62rem] text-muted">{{ $log->summary() }}</span>
                        @endif
                    </span>
                    <span class="shrink-0 text-[0.62rem] text-muted">{{ $log->created_at->diffForHumans(short: true) }}</span>
                </li>
            @endforeach
        </ul>
    </div>
@endif
