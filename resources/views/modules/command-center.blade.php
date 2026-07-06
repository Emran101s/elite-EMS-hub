<x-layouts.app
    :title="'Welcome back, ' . str(auth()->user()->name)->before(' ') . ' 👋'"
    subtitle="Here's what's happening across your events and projects.">

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">

        {{-- ════════ Main column ════════ --}}
        <div class="min-w-0 space-y-5">

            {{-- KPI row: 155×90 cards (same scale as the Events page) --}}
            <div class="flex flex-wrap gap-3">
                @foreach ([
                    ['label' => 'Total Events', 'icon' => 'calendar', 'value' => $stats['events'], 'hint' => 'across the region', 'tone' => 'bg-[#3B82F6]/10 text-[#3B82F6]'],
                    ['label' => 'Active Projects', 'icon' => 'folder', 'value' => $stats['projects'], 'hint' => 'portfolios running', 'tone' => 'bg-track/10 text-emerald-600'],
                    ['label' => 'Total Budget', 'icon' => 'currency', 'value' => '$' . \Illuminate\Support\Number::abbreviate($stats['budget'] / 100, 2), 'hint' => 'committed to events', 'tone' => 'bg-gold-50 text-gold-600'],
                    ['label' => 'Open Tasks', 'icon' => 'clipboard', 'value' => $stats['openTasks'], 'hint' => 'pending + in progress', 'tone' => 'bg-track/10 text-emerald-600'],
                    ['label' => 'At Risk', 'icon' => 'bell', 'value' => $stats['atRisk'], 'hint' => 'needs attention', 'tone' => 'bg-risk/10 text-risk', 'risk' => $stats['atRisk'] > 0],
                ] as $kpi)
                    <div class="flex h-[90px] w-[155px] flex-col justify-between rounded-[18px] border border-line bg-white px-3 py-2.5 shadow-[0_10px_30px_rgba(15,23,42,0.05)]">
                        <div class="flex items-center gap-2">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg {{ $kpi['tone'] }}">
                                <x-icon :name="$kpi['icon']" class="h-4.5 w-4.5" />
                            </span>
                            <p class="text-[11px] font-semibold leading-tight text-muted">{{ $kpi['label'] }}</p>
                        </div>
                        <p class="text-[22px] font-bold leading-none {{ ($kpi['risk'] ?? false) ? 'text-risk' : 'text-navy-900' }}">{{ $kpi['value'] }}</p>
                        <p class="truncate text-[10px] font-semibold text-muted">{{ $kpi['hint'] }}</p>
                    </div>
                @endforeach
            </div>

            {{-- Operations Hub --}}
            <div class="card overflow-hidden">
                <div class="border-b border-line px-6 py-4">
                    <h2 class="text-sm font-bold uppercase tracking-wide text-navy-900">Operations Hub</h2>
                    <p class="mt-0.5 text-xs text-muted">Real-time overview of your events ecosystem</p>
                </div>

                {{-- Orbit canvas (lg+) --}}
                <div class="relative hidden h-[540px] bg-[radial-gradient(ellipse_at_center,rgba(212,175,55,0.06),transparent_65%)] lg:block">
                    <svg class="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                        <ellipse cx="50" cy="50" rx="42" ry="36" fill="none" stroke="#D4AF37" stroke-opacity="0.35"
                                 stroke-width="0.18" stroke-dasharray="1.2 1" />
                        @foreach ($islands as $event)
                            <line x1="50" y1="50" x2="{{ $event->pos_x }}" y2="{{ $event->pos_y }}"
                                  stroke="#D4AF37" stroke-opacity="0.22" stroke-width="0.15" />
                        @endforeach
                    </svg>

                    {{-- AI Command Core --}}
                    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                        <div class="flex flex-col items-center gap-2 rounded-2xl border border-gold-200 bg-white/90 px-5 py-4 text-center shadow-[0_8px_30px_rgba(212,175,55,0.18)] backdrop-blur">
                            <svg class="h-8 w-8" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                                <rect x="20" y="3.5" width="23.3" height="23.3" rx="4" transform="rotate(45 20 3.5)" stroke="#D4AF37" stroke-width="2.5"/>
                                <rect x="20" y="12.5" width="10.6" height="10.6" rx="2" transform="rotate(45 20 12.5)" fill="#D4AF37"/>
                            </svg>
                            <p class="text-xs font-bold tracking-wide text-navy-900">AI COMMAND CORE</p>
                            <p class="flex items-center gap-1.5 text-[0.65rem] text-muted">
                                <span class="h-1.5 w-1.5 rounded-full bg-track"></span> All systems operational
                            </p>
                        </div>
                    </div>

                    {{-- Event islands --}}
                    @foreach ($islands as $event)
                        <a href="{{ route('events.hub', $event) }}"
                           class="absolute -translate-x-1/2 -translate-y-1/2 transition hover:scale-105"
                           style="left: {{ $event->pos_x }}%; top: {{ $event->pos_y }}%">
                            <span class="flex w-48 items-center gap-3 rounded-2xl border border-line bg-white/95 p-3 shadow-[0_6px_24px_rgba(11,31,58,0.10)] backdrop-blur">
                                <x-event-avatar :event="$event" size="md" />
                                <span class="min-w-0">
                                    <span class="block truncate text-xs font-bold text-navy-900">{{ $event->name }}</span>
                                    <span class="block truncate text-[0.65rem] text-muted">{{ str($event->type)->replace('_', ' ')->title() }}</span>
                                    <span class="block truncate text-[0.65rem] text-muted">{{ $event->city }}, {{ $event->country }}</span>
                                </span>
                            </span>
                        </a>
                    @endforeach
                </div>

                {{-- Stacked fallback (< lg) --}}
                <div class="grid gap-3 p-4 sm:grid-cols-2 lg:hidden">
                    @foreach ($islands as $event)
                        <a href="{{ route('events.hub', $event) }}" class="flex items-center gap-3 rounded-2xl border border-line p-3">
                            <x-event-avatar :event="$event" size="md" />
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-bold text-navy-900">{{ $event->name }}</span>
                                <span class="block truncate text-[0.65rem] text-muted">{{ $event->city }}, {{ $event->country }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- Bottom analytics row --}}
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-4">

                <div class="card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Upcoming Deadlines</h3>
                        <a href="{{ route('tasks.index') }}" class="text-xs font-semibold text-gold-600 hover:text-gold-700">View all</a>
                    </div>
                    <ul class="space-y-3">
                        @forelse ($deadlines as $task)
                            <li class="flex items-start justify-between gap-2">
                                <div class="min-w-0">
                                    <p class="truncate text-xs font-semibold text-navy-900">{{ $task->title }}</p>
                                    <p class="text-[0.65rem] text-muted">{{ $task->event?->name }} · {{ $task->due_on->format('M j, Y') }}</p>
                                </div>
                                <span class="shrink-0 text-[0.65rem] font-bold {{ $task->due_on->isPast() ? 'text-risk' : 'text-gold-600' }}">
                                    {{ $task->due_on->diffForHumans(short: true) }}
                                </span>
                            </li>
                        @empty
                            <li class="text-xs text-muted">Nothing due — clear runway.</li>
                        @endforelse
                    </ul>
                </div>

                <div class="card p-5">
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Tasks Overview</h3>
                    @php $totalTasks = max(array_sum($taskCounts), 1); @endphp
                    <div class="flex items-center gap-5">
                        <x-donut :segments="[
                            ['pct' => $taskCounts['completed'] / $totalTasks * 100, 'class' => 'stroke-track'],
                            ['pct' => $taskCounts['in_progress'] / $totalTasks * 100, 'class' => 'stroke-warn'],
                            ['pct' => $taskCounts['pending'] / $totalTasks * 100, 'class' => 'stroke-navy-200'],
                        ]" size="h-28 w-28" class="shrink-0">
                            <span class="text-xl font-bold text-navy-900">{{ array_sum($taskCounts) }}</span>
                            <span class="text-[0.6rem] text-muted">Total Tasks</span>
                        </x-donut>
                        <ul class="space-y-2 text-xs">
                            @foreach ([
                                ['label' => 'Completed', 'count' => $taskCounts['completed'], 'dot' => 'bg-track'],
                                ['label' => 'In Progress', 'count' => $taskCounts['in_progress'], 'dot' => 'bg-warn'],
                                ['label' => 'Pending', 'count' => $taskCounts['pending'], 'dot' => 'bg-navy-200'],
                            ] as $row)
                                <li class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full {{ $row['dot'] }}"></span>
                                    <span class="text-muted">{{ $row['label'] }}</span>
                                    <span class="font-bold text-navy-900">{{ $row['count'] }}</span>
                                    <span class="text-muted">({{ round($row['count'] / $totalTasks * 100) }}%)</span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>

                <div class="card p-5">
                    <div class="mb-4 flex items-center justify-between">
                        <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Top Suppliers</h3>
                        <a href="{{ route('suppliers.index') }}" class="text-xs font-semibold text-gold-600 hover:text-gold-700">View all</a>
                    </div>
                    <ul class="divide-y divide-line">
                        @foreach ($topSuppliers as $supplier)
                            <li class="flex items-center gap-3 py-2.5 first:pt-0 last:pb-0">
                                <span class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-xs font-bold text-gold-400">
                                    {{ str($supplier->name)->substr(0, 1) }}
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-xs font-semibold text-navy-900">{{ $supplier->name }}</span>
                                    <span class="block text-[0.65rem] text-muted">{{ str($supplier->category)->replace('_', ' & ')->title() }}</span>
                                </span>
                                <span class="text-xs font-bold text-gold-600">★ {{ number_format($supplier->rating, 1) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="card p-5">
                    <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Events by Status</h3>
                    <ul class="space-y-3">
                        @foreach ([
                            ['label' => 'On Track', 'bar' => 'bg-track'],
                            ['label' => 'In Progress', 'bar' => 'bg-warn'],
                            ['label' => 'At Risk', 'bar' => 'bg-risk'],
                            ['label' => 'Completed', 'bar' => 'bg-navy-300'],
                        ] as $row)
                            @php $count = $statusBars['counts'][$row['label']]; @endphp
                            <li>
                                <div class="mb-1 flex items-center justify-between text-xs">
                                    <span class="text-muted">{{ $row['label'] }}</span>
                                    <span class="font-bold text-navy-900">{{ $count }}</span>
                                </div>
                                <div class="h-2 overflow-hidden rounded-full bg-navy-50">
                                    <div class="h-full rounded-full {{ $row['bar'] }}" style="width: {{ $count / $statusBars['max'] * 100 }}%"></div>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </div>

        {{-- ════════ Right rail ════════ --}}
        <div class="space-y-5">

            <div class="card p-5">
                <div class="mb-4 flex items-center justify-between">
                    <h3 class="text-xs font-bold uppercase tracking-wide text-navy-900">Live Alerts</h3>
                    <a href="{{ route('events.index') }}" class="text-xs font-semibold text-gold-600 hover:text-gold-700">View all</a>
                </div>
                <ul class="space-y-3.5">
                    @forelse ($alerts as $alert)
                        <li class="flex gap-2.5">
                            <span @class([
                                    'mt-1 h-2 w-2 shrink-0 rounded-full',
                                    'bg-risk' => $alert['severity'] === 'risk',
                                    'bg-warn' => $alert['severity'] === 'warn',
                                    'bg-track' => $alert['severity'] === 'info',
                                ])></span>
                            <div class="min-w-0">
                                <p class="truncate text-xs font-semibold text-navy-900">{{ $alert['title'] }}</p>
                                <p class="truncate text-[0.65rem] text-muted">{{ $alert['detail'] }}</p>
                            </div>
                        </li>
                    @empty
                        <li class="text-xs text-muted">All quiet — no active alerts.</li>
                    @endforelse
                </ul>
            </div>

            <div class="card p-5">
                <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Resource Utilization</h3>
                <ul class="space-y-4">
                    @foreach ($utilization as $resource)
                        <li>
                            <div class="mb-1 flex items-center justify-between text-xs">
                                <span class="font-medium text-navy-800">{{ $resource['label'] }}</span>
                                <span class="font-bold text-navy-900">{{ $resource['pct'] !== null ? $resource['pct'].'%' : '—' }}</span>
                            </div>
                            <div class="h-1.5 overflow-hidden rounded-full bg-navy-50">
                                @if ($resource['pct'] !== null)
                                    <div @class([
                                            'h-full rounded-full',
                                            'bg-risk' => $resource['pct'] >= 90,
                                            'bg-gold-500' => $resource['pct'] >= 70 && $resource['pct'] < 90,
                                            'bg-navy-400' => $resource['pct'] < 70,
                                        ]) style="width: {{ $resource['pct'] }}%"></div>
                                @endif
                            </div>
                            <p class="mt-1 text-[0.65rem] text-muted">{{ $resource['hint'] }}</p>
                        </li>
                    @endforeach
                </ul>
            </div>

            <div class="card p-5">
                <h3 class="mb-4 text-xs font-bold uppercase tracking-wide text-navy-900">Budget Overview</h3>
                <div class="flex justify-center">
                    <x-donut :segments="collect($budget['segments'])->map(fn ($segment) => [
                        'pct' => $segment['pct'],
                        'class' => match ($segment['group']) {
                            'risk' => 'stroke-risk',
                            'warn' => 'stroke-warn',
                            default => 'stroke-track',
                        },
                    ])->all()" size="h-32 w-32">
                        <span class="text-base font-bold text-navy-900">${{ \Illuminate\Support\Number::abbreviate($budget['total'] / 100, 2) }}</span>
                        <span class="text-[0.6rem] text-muted">Total Budget</span>
                    </x-donut>
                </div>
                <ul class="mt-4 space-y-2 text-xs">
                    @foreach ($budget['segments'] as $segment)
                        <li class="flex items-center gap-2">
                            <span @class([
                                    'h-2 w-2 rounded-full',
                                    'bg-track' => $segment['group'] === 'track',
                                    'bg-warn' => $segment['group'] === 'warn',
                                    'bg-risk' => $segment['group'] === 'risk',
                                ])></span>
                            <span class="text-muted">{{ ['track' => 'On-track events', 'warn' => 'In-progress events', 'risk' => 'At-risk events'][$segment['group']] }}</span>
                            <span class="ml-auto font-bold text-navy-900">${{ \Illuminate\Support\Number::abbreviate($segment['cents'] / 100, 2) }}</span>
                            <span class="text-muted">{{ round($segment['pct']) }}%</span>
                        </li>
                    @endforeach
                </ul>
                <p class="mt-3 text-[0.65rem] text-muted">Grouped by event health — spend tracking arrives with Finance (Phase 3).</p>
            </div>
        </div>
    </div>
</x-layouts.app>
