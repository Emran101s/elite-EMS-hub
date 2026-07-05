{{-- Lifecycle timeline --}}
<div class="card mb-6 px-6 py-5">
    <div class="flex items-center justify-between overflow-x-auto">
        @php
            $stages = ['draft', 'proposal', 'confirmed', 'planning', 'production', 'live', 'completed', 'closed'];
            $currentIndex = array_search($event->stage, $stages);
        @endphp
        @foreach ($stages as $i => $stage)
            <div class="flex min-w-0 flex-1 items-center">
                <div class="flex flex-col items-center gap-1.5">
                    <span @class([
                            'flex h-6 w-6 items-center justify-center rounded-full text-[0.6rem] font-bold',
                            'bg-gold-500 text-navy-900 ring-4 ring-gold-100' => $i === $currentIndex,
                            'bg-navy-900 text-gold-400' => $currentIndex !== false && $i < $currentIndex,
                            'bg-navy-50 text-navy-300' => $currentIndex === false || $i > $currentIndex,
                        ])>{{ $currentIndex !== false && $i < $currentIndex ? '✓' : $i + 1 }}</span>
                    <span class="{{ $i === $currentIndex ? 'font-bold text-navy-900' : 'text-muted' }} whitespace-nowrap text-[0.6rem]">
                        {{ str($stage)->title() }}
                    </span>
                </div>
                @unless ($loop->last)
                    <div class="mx-1.5 h-px flex-1 {{ $currentIndex !== false && $i < $currentIndex ? 'bg-gold-400' : 'bg-line' }}"></div>
                @endunless
            </div>
        @endforeach
    </div>
</div>

<div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
    <div class="space-y-6">
        {{-- Health components --}}
        <div class="card p-6">
            <div class="mb-5 flex items-center justify-between">
                <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Event Health — {{ $health['score'] }}%</h3>
                <x-status-badge :status="$health['status']" />
            </div>
            @if ($health['critical_risk'])
                <p class="mb-4 rounded-xl bg-risk/10 px-4 py-2.5 text-xs font-medium text-red-700 ring-1 ring-risk/30">
                    ⚠ Critical risk caps this event at "At Risk": {{ $health['critical_risk'] }}
                </p>
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach ([
                    'tasks' => 'Task Completion', 'budget' => 'Budget Health', 'suppliers' => 'Supplier Readiness',
                    'venue' => 'Venue Readiness', 'agenda' => 'Agenda Completion', 'risk' => 'Risk Level',
                ] as $key => $label)
                    @php $score = $health['components'][$key]; @endphp
                    <div>
                        <div class="mb-1 flex items-center justify-between text-xs">
                            <span class="font-medium text-navy-800">{{ $label }} <span class="text-muted">· {{ $health['weights'][$key] }}%</span></span>
                            <span class="font-bold text-navy-900">{{ $score !== null ? $score.'%' : '—' }}</span>
                        </div>
                        <div class="h-1.5 overflow-hidden rounded-full bg-navy-50">
                            @if ($score !== null)
                                <div @class([
                                        'h-full rounded-full',
                                        'bg-track' => $score >= 81,
                                        'bg-warn' => $score >= 61 && $score < 81,
                                        'bg-risk' => $score < 61,
                                    ]) style="width: {{ $score }}%"></div>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        {{-- Agenda overview + summaries --}}
        <div class="grid gap-4 lg:grid-cols-2">
            <div class="card p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Agenda Overview</h3>
                    @php $firstDay = $event->agendaDays->first(); @endphp
                    @if ($firstDay)
                        <span class="rounded-full bg-navy-50 px-2.5 py-1 text-[0.65rem] font-semibold text-navy-600">{{ $firstDay->date->format('M j, Y') }}</span>
                    @endif
                </div>
                @if ($firstDay && $firstDay->sessions->isNotEmpty())
                    <ul class="space-y-2">
                        @foreach ($firstDay->sessions->take(5) as $session)
                            <li class="flex items-center gap-3 text-xs">
                                <span class="w-24 shrink-0 border-l-2 pl-2 font-semibold text-navy-900" style="border-color: {{ $event->theme()['accent'] }}">
                                    {{ substr($session->starts_at, 0, 5) }} – {{ substr($session->ends_at, 0, 5) }}
                                </span>
                                <span class="min-w-0 flex-1 truncate font-medium text-navy-800">{{ $session->title }}</span>
                                <span class="shrink-0 text-[0.65rem] text-muted">{{ $session->room?->name }}</span>
                            </li>
                        @endforeach
                    </ul>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" class="mt-3 block text-right text-xs font-semibold text-gold-600 hover:text-gold-700">View Full Agenda →</a>
                @else
                    <p class="text-xs text-muted">No sessions yet — the agenda contributes 10% of the health score.</p>
                @endif
            </div>

            <div class="grid grid-cols-2 gap-4">
                @php
                    $taskTotal = max($event->tasks->count(), 1);
                    $done = $event->tasks->where('status', 'completed')->count();
                    $doing = $event->tasks->where('status', 'in_progress')->count();
                    $todo = $event->tasks->where('status', 'pending')->count();
                    $estimated = $event->budgetItems->sum('estimated_cents');
                    $actual = $event->budgetItems->sum('actual_cents');
                    $budgetTotal = max($event->budget_cents, 1);
                    $committed = max($estimated - $actual, 0);
                    $remaining = max($event->budget_cents - $estimated, 0);
                @endphp
                <div class="card flex flex-col items-center p-4">
                    <h3 class="mb-2 self-start text-xs font-bold uppercase tracking-wide text-navy-900">Tasks</h3>
                    <x-donut :segments="[
                        ['pct' => $done / $taskTotal * 100, 'class' => 'stroke-track'],
                        ['pct' => $doing / $taskTotal * 100, 'class' => 'stroke-warn'],
                        ['pct' => $todo / $taskTotal * 100, 'class' => 'stroke-navy-200'],
                    ]" size="h-24 w-24">
                        <span class="text-lg font-bold text-navy-900">{{ $event->tasks->count() }}</span>
                        <span class="text-[0.55rem] text-muted">Total</span>
                    </x-donut>
                    <p class="mt-2 text-[0.6rem] text-muted">{{ $done }} done · {{ $doing }} active · {{ $todo }} pending</p>
                </div>
                <div class="card flex flex-col items-center p-4">
                    <h3 class="mb-2 self-start text-xs font-bold uppercase tracking-wide text-navy-900">Budget</h3>
                    <x-donut :segments="[
                        ['pct' => min($actual / $budgetTotal * 100, 100), 'class' => 'stroke-risk'],
                        ['pct' => min($committed / $budgetTotal * 100, 100 - min($actual / $budgetTotal * 100, 100)), 'class' => 'stroke-warn'],
                    ]" size="h-24 w-24">
                        <span class="text-sm font-bold text-navy-900">${{ \Illuminate\Support\Number::abbreviate($event->budget_cents / 100) }}</span>
                        <span class="text-[0.55rem] text-muted">Budget</span>
                    </x-donut>
                    <p class="mt-2 text-[0.6rem] text-muted">
                        ${{ \Illuminate\Support\Number::abbreviate($actual / 100) }} spent ·
                        ${{ \Illuminate\Support\Number::abbreviate($committed / 100) }} committed ·
                        ${{ \Illuminate\Support\Number::abbreviate($remaining / 100) }} free
                    </p>
                </div>
            </div>
        </div>

        {{-- Quick stat cards --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-4">
            @php
                $openTasks = $event->tasks->where('status', '!=', 'completed')->count();
                $estimated = $event->budgetItems->sum('estimated_cents');
                $actual = $event->budgetItems->sum('actual_cents');
                $openRisks = $event->risks->filter->isOpen()->count();
            @endphp
            @foreach ([
                ['label' => 'Open Tasks', 'value' => $openTasks, 'sub' => $event->tasks->count().' total'],
                ['label' => 'Budget Used', 'value' => $estimated ? round($actual / max($estimated, 1) * 100).'%' : '—', 'sub' => '$'.\Illuminate\Support\Number::abbreviate($actual / 100).' of $'.\Illuminate\Support\Number::abbreviate($estimated / 100)],
                ['label' => 'Open Risks', 'value' => $openRisks, 'sub' => $event->risks->count().' registered', 'risk' => $openRisks > 0],
                ['label' => 'Pending Approvals', 'value' => $health['pending_approvals'], 'sub' => $event->approvals->count().' total'],
            ] as $stat)
                <div class="card p-4">
                    <p class="text-[0.65rem] font-semibold uppercase tracking-wide text-muted">{{ $stat['label'] }}</p>
                    <p class="mt-1.5 text-2xl font-bold {{ ($stat['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">{{ $stat['value'] }}</p>
                    <p class="mt-0.5 text-[0.65rem] text-muted">{{ $stat['sub'] }}</p>
                </div>
            @endforeach
        </div>

        {{-- Key deadlines --}}
        <div class="card p-6">
            <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Key Deadlines</h3>
            <ul class="space-y-3">
                @forelse ($event->tasks->where('status', '!=', 'completed')->sortBy('due_on')->take(5) as $task)
                    <li class="flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-navy-900">{{ $task->title }}</p>
                            <p class="text-[0.65rem] text-muted">{{ $task->assignee?->name ?? 'Unassigned' }}</p>
                        </div>
                        <span class="shrink-0 text-[0.65rem] font-bold {{ $task->due_on?->isPast() ? 'text-risk' : 'text-gold-600' }}">
                            {{ $task->due_on?->diffForHumans(short: true) ?? '—' }}
                        </span>
                    </li>
                @empty
                    <li class="text-xs text-muted">No open tasks.</li>
                @endforelse
            </ul>
        </div>
    </div>

    {{-- Right rail: AI advisor --}}
    <div class="space-y-6">
        <div class="card p-5">
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-navy-900">✦ AI Daily Brief</h3>
            <p class="text-sm font-semibold text-navy-900">{{ $ai['headline'] }}</p>
            @if (count($ai['attention']))
                <p class="mb-1.5 mt-3 text-[0.65rem] font-semibold uppercase tracking-wide text-muted">Attention points</p>
                <ol class="space-y-1.5 text-xs text-navy-800">
                    @foreach ($ai['attention'] as $i => $point)
                        <li class="flex gap-2"><span class="font-bold text-gold-600">{{ $i + 1 }}.</span> {{ $point }}</li>
                    @endforeach
                </ol>
            @endif
            <div class="mt-4 rounded-xl bg-gold-50 px-3.5 py-2.5 ring-1 ring-gold-200">
                <p class="text-[0.65rem] font-bold uppercase tracking-wide text-gold-700">Recommended action</p>
                <p class="mt-1 text-xs text-navy-900">{{ $ai['recommendation'] }}</p>
            </div>
            <a href="{{ route('events.hub', [$event, 'tab' => 'ai']) }}" class="mt-3 block text-right text-xs font-semibold text-gold-600 hover:text-gold-700">Open AI Insights →</a>
        </div>

        {{-- Risk radar --}}
        <div class="card p-5">
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-navy-900">Risk Radar</h3>
            <ul class="space-y-2.5">
                @forelse ($event->risks->filter->isOpen()->sortByDesc->severity()->take(4) as $risk)
                    <li class="flex items-center justify-between gap-2">
                        <p class="min-w-0 truncate text-xs font-medium text-navy-900">{{ $risk->title }}</p>
                        <span @class([
                                'shrink-0 rounded-full px-2 py-0.5 text-[0.6rem] font-bold',
                                'bg-risk/10 text-red-700' => $risk->severity() >= 15,
                                'bg-warn/10 text-amber-700' => $risk->severity() >= 8 && $risk->severity() < 15,
                                'bg-navy-50 text-navy-600' => $risk->severity() < 8,
                            ])>{{ $risk->severity() }}/25</span>
                    </li>
                @empty
                    <li class="text-xs text-muted">No open risks. 🎉</li>
                @endforelse
            </ul>
        </div>

        {{-- Team --}}
        <div class="card p-5">
            <h3 class="mb-3 text-xs font-bold uppercase tracking-wide text-navy-900">Team</h3>
            <ul class="space-y-2.5">
                @forelse ($event->teamMembers as $member)
                    <li class="flex items-center gap-2.5">
                        <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-navy-900 text-[0.65rem] font-bold text-gold-400">{{ $member->initials() }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-xs font-semibold text-navy-900">{{ $member->name }}</p>
                            <p class="text-[0.6rem] text-muted">{{ str($member->pivot->role)->replace('_', ' ')->title() }}</p>
                        </div>
                    </li>
                @empty
                    <li class="text-xs text-muted">No team assigned yet.</li>
                @endforelse
            </ul>
        </div>
    </div>
</div>
