<x-layouts.app
    :title="'Welcome back, ' . str(auth()->user()->name)->before(' ') . ' 👋'"
    subtitle="Here's what's happening across your events and projects.">

    <div class="grid gap-5 xl:grid-cols-[minmax(0,1fr)_20rem]">

        {{-- ════════ Main column ════════ --}}
        <div class="min-w-0 space-y-5">

            {{-- KPI command strip --}}
            <div class="strip-dark px-6 py-5">
                <div class="pointer-events-none absolute -right-8 -top-16 h-48 w-48 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.30),transparent_70%)]"></div>

                <div class="relative flex flex-wrap items-center gap-x-5 gap-y-5">
                    @foreach ([
                        ['label' => 'Total Events', 'icon' => 'calendar', 'value' => $stats['events'], 'hint' => 'across the region'],
                        ['label' => 'Active Projects', 'icon' => 'folder', 'value' => $stats['projects'], 'hint' => 'portfolios running'],
                        ['label' => 'Total Budget', 'icon' => 'currency', 'value' => '$' . \Illuminate\Support\Number::abbreviate($stats['budget'] / 100, 2), 'hint' => 'committed to events'],
                        ['label' => 'Open Tasks', 'icon' => 'clipboard', 'value' => $stats['openTasks'], 'hint' => 'pending + in progress'],
                        ['label' => 'At Risk', 'icon' => 'bell', 'value' => $stats['atRisk'], 'hint' => 'needs attention', 'risk' => $stats['atRisk'] > 0],
                    ] as $i => $kpi)
                        @if ($i > 0)
                            <span class="hidden h-11 w-px bg-white/10 lg:block" aria-hidden="true"></span>
                        @endif
                        <div class="flex min-w-[122px] flex-1 items-center gap-2.5">
                            <span @class([
                                'flex h-9 w-9 shrink-0 items-center justify-center rounded-xl ring-1',
                                'bg-red-400/10 text-red-300 ring-red-400/30' => $kpi['risk'] ?? false,
                                'bg-white/[0.07] text-gold-400 ring-white/10' => ! ($kpi['risk'] ?? false),
                            ])>
                                <x-icon :name="$kpi['icon']" class="h-4 w-4" />
                            </span>
                            <div class="min-w-0">
                                <p class="text-[0.48rem] font-bold uppercase tracking-[0.16em] text-gold-300/80">{{ $kpi['label'] }}</p>
                                <p class="pf mt-0.5 text-[26px] font-bold leading-none {{ ($kpi['risk'] ?? false) ? 'text-red-300' : 'text-white' }}">{{ $kpi['value'] }}</p>
                                <p class="mt-1 truncate text-[0.6rem] text-white/40">{{ $kpi['hint'] }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Operations Hub — circular event ecosystem --}}
            <div id="operations-hub" class="card scroll-mt-6 overflow-hidden">
                <div class="flex items-center justify-between border-b border-line px-6 py-4">
                    <div>
                        <h2 class="pf text-base font-bold text-navy-900">Operations Hub</h2>
                        <p class="mt-0.5 text-xs text-muted">Real-time overview of your events ecosystem</p>
                    </div>
                    <div class="hidden items-center gap-3 text-[0.65rem] font-semibold text-muted sm:flex">
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-track"></span> On Track</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-warn"></span> In Progress</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-risk"></span> At Risk</span>
                        <span class="flex items-center gap-1.5"><span class="h-2 w-2 rounded-full bg-navy-200"></span> Not Started</span>
                    </div>
                </div>

                {{-- Orbit canvas (lg+) --}}
                <div class="relative hidden h-[620px] overflow-hidden lg:block"
                     style="background:
                        radial-gradient(ellipse 60% 55% at 50% 50%, rgba(212,175,55,0.10), transparent 70%),
                        radial-gradient(ellipse 90% 80% at 50% 50%, rgba(11,31,58,0.04), transparent 75%),
                        linear-gradient(180deg, #FBFCFE, #F4F7FC);">

                    {{-- faint dot texture --}}
                    <div class="pointer-events-none absolute inset-0 opacity-[0.5]"
                         style="background-image: radial-gradient(rgba(11,31,58,0.05) 1px, transparent 1px); background-size: 26px 26px;"></div>

                    {{-- Rotating orbit rings --}}
                    <div class="pointer-events-none absolute left-1/2 top-1/2 h-[560px] w-[560px] -translate-x-1/2 -translate-y-1/2">
                        <div class="orbit-spin-slow absolute inset-0 rounded-full border border-dashed border-gold-500/25"></div>
                        <div class="orbit-spin-reverse absolute inset-[70px] rounded-full border border-dashed border-navy-300/25"></div>
                        <div class="absolute inset-[150px] rounded-full border border-gold-500/10"></div>
                    </div>

                    {{-- Curved gold connectors --}}
                    <svg class="absolute inset-0 h-full w-full" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                        <defs>
                            <radialGradient id="conn" cx="50%" cy="50%" r="50%">
                                <stop offset="0%" stop-color="#D4AF37" stop-opacity="0.65" />
                                <stop offset="100%" stop-color="#D4AF37" stop-opacity="0.12" />
                            </radialGradient>
                        </defs>
                        @foreach ($islands as $event)
                            {{-- soft glow underlay --}}
                            <path d="M50 50 Q{{ $event->ctrl_x }} {{ $event->ctrl_y }} {{ $event->pos_x }} {{ $event->pos_y }}"
                                  fill="none" stroke="#D4AF37" stroke-opacity="0.10" stroke-width="0.9" stroke-linecap="round" />
                            <path d="M50 50 Q{{ $event->ctrl_x }} {{ $event->ctrl_y }} {{ $event->pos_x }} {{ $event->pos_y }}"
                                  fill="none" stroke="url(#conn)" stroke-width="0.28" stroke-linecap="round"
                                  stroke-dasharray="1.6 1.2" class="dash-flow" />
                        @endforeach
                    </svg>

                    {{-- Event Circle Cards --}}
                    @foreach ($islands as $event)
                        @php
                            $ringColor = ['track' => '#22C55E', 'warn' => '#F59E0B', 'risk' => '#EF4444',
                                          'neutral' => '#B3C2DD'][$event->health_group];
                            $statusLabel = str($event->health_status)->replace('_', ' ')->title();
                            // Draft and proposal events carry no score; the ring stays
                            // empty and the pill reads "—" rather than a damning 0%.
                            $scored = $event->health_score !== null;
                        @endphp
                        <a href="{{ route('events.hub', $event) }}"
                           class="group absolute z-10 flex w-40 -translate-x-1/2 -translate-y-1/2 flex-col items-center text-center"
                           style="left: {{ $event->pos_x }}%; top: {{ $event->pos_y }}%">

                            {{-- Circular medallion --}}
                            <span class="relative block h-[116px] w-[116px] transition duration-300 group-hover:-translate-y-1 group-hover:scale-105">
                                <svg viewBox="0 0 100 100" class="absolute inset-0 h-full w-full -rotate-90 drop-shadow-[0_8px_20px_rgba(11,31,58,0.15)]">
                                    <circle cx="50" cy="50" r="45" fill="#FFFFFF" />
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="#E2E8F0" stroke-width="5" />
                                    <circle cx="50" cy="50" r="45" fill="none" stroke="{{ $ringColor }}" stroke-width="5"
                                            stroke-linecap="round" stroke-dasharray="{{ $scored ? $event->health_score * 2.827 : 0 }} 283" />
                                </svg>
                                {{-- avatar inside the ring --}}
                                <span class="absolute inset-[11px] overflow-hidden rounded-full bg-[radial-gradient(circle,#FFFFFF,#EEF2F8)] ring-1 ring-white">
                                    <x-event-avatar :event="$event" :ring="false" size="md"
                                                    class="block h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-full [&>span]:!bg-transparent [&>span]:ring-0 [&_img]:scale-[1.35] [&_img]:object-contain" />
                                </span>
                                {{-- health % pill --}}
                                <span class="absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-white px-2 py-0.5 text-[0.7rem] font-bold shadow ring-1 ring-line"
                                      style="color: {{ $ringColor }}">{{ $scored ? $event->health_score.'%' : '—' }}</span>
                                {{-- status pill --}}
                                <span class="absolute -top-1.5 left-1/2 -translate-x-1/2 whitespace-nowrap rounded-full px-2 py-0.5 text-[0.55rem] font-bold uppercase tracking-wide text-white shadow"
                                      style="background: {{ $ringColor }}">{{ $statusLabel }}</span>
                                {{-- risk badge --}}
                                @if ($event->open_risks > 0)
                                    <span class="absolute right-0 top-3 flex h-5 min-w-5 items-center justify-center rounded-full bg-risk px-1 text-[0.6rem] font-bold text-white shadow ring-2 ring-white"
                                          title="{{ $event->open_risks }} open {{ str('risk')->plural($event->open_risks) }}">⚠</span>
                                @endif
                            </span>

                            {{-- Labels --}}
                            <span class="mt-3 block max-w-full truncate text-xs font-bold text-navy-900 group-hover:text-gold-700">{{ $event->name }}</span>
                            {{-- The avatar library is gone; the event's type names it now. --}}
                            <span class="block truncate text-[0.62rem] text-muted">{{ str($event->type)->replace('_', ' ')->title() }}</span>
                            <span class="block truncate text-[0.62rem] text-muted">{{ $event->city }}, {{ $event->country }}</span>
                        </a>
                    @endforeach

                    {{-- AI Command Core (center, on top) --}}
                    <div class="absolute left-1/2 top-1/2 z-20 -translate-x-1/2 -translate-y-1/2">
                        <div class="relative flex h-[168px] w-[168px] items-center justify-center">
                            {{-- glow + pulse rings --}}
                            <div class="core-glow absolute inset-0 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.35),transparent_70%)]"></div>
                            <div class="absolute inset-4 rounded-full border border-gold-400/40"></div>
                            <div class="absolute inset-4 animate-ping rounded-full border border-gold-400/30 [animation-duration:3s]"></div>
                            {{-- core disc --}}
                            <div class="relative flex h-[132px] w-[132px] flex-col items-center justify-center rounded-full border border-gold-300/60 text-center shadow-[0_12px_40px_rgba(11,31,58,0.30)]"
                                 style="background: radial-gradient(circle at 50% 35%, #16294A, #0B1F3A 70%);">
                                <svg class="h-9 w-9" viewBox="0 0 40 40" fill="none" aria-hidden="true">
                                    <rect x="20" y="3.5" width="23.3" height="23.3" rx="4" transform="rotate(45 20 3.5)" stroke="#D4AF37" stroke-width="2.5"/>
                                    <rect x="20" y="12.5" width="10.6" height="10.6" rx="2" transform="rotate(45 20 12.5)" fill="#D4AF37"/>
                                </svg>
                                <p class="mt-1.5 text-[0.6rem] font-bold tracking-[0.15em] text-gold-300">AI COMMAND CORE</p>
                                <p class="mt-1 flex items-center gap-1.5 text-[0.55rem] text-white/70">
                                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-track"></span> All systems operational
                                </p>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Stacked fallback (< lg) --}}
                <div class="grid gap-3 p-4 sm:grid-cols-2 lg:hidden">
                    @foreach ($islands as $event)
                        <a href="{{ route('events.hub', $event) }}" class="flex items-center gap-3 rounded-2xl border border-line p-3">
                            <x-event-avatar :event="$event" size="md" :percent="$event->health_score" :group="$event->health_group" />
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
                        <h3 class="pf text-base font-bold text-navy-900">Upcoming Deadlines</h3>
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
                    <h3 class="mb-4 pf text-base font-bold text-navy-900">Tasks Overview</h3>
                    @php
                        // Tasks now move through six named stages, each carrying its own
                        // colour, rather than the three buckets this card was built for.
                        $stages = collect($taskCounts)->filter(fn ($s) => $s['count'] > 0);
                        $totalTasks = max($stages->sum('count'), 1);
                    @endphp
                    <div class="flex items-center gap-5">
                        <x-donut :segments="$stages->map(fn ($s) => ['pct' => $s['count'] / $totalTasks * 100, 'hex' => $s['hex']])->values()->all()"
                                 size="h-28 w-28" class="shrink-0">
                            <span class="text-xl font-bold text-navy-900">{{ $stages->sum('count') }}</span>
                            <span class="text-[0.6rem] text-muted">Total Tasks</span>
                        </x-donut>
                        <ul class="space-y-2 text-xs">
                            @foreach ($stages as $row)
                                <li class="flex items-center gap-2">
                                    <span class="h-2 w-2 rounded-full" style="background: {{ $row['hex'] }}"></span>
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
                        <h3 class="pf text-base font-bold text-navy-900">Top Suppliers</h3>
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
                    <h3 class="mb-4 pf text-base font-bold text-navy-900">Events by Status</h3>
                    <ul class="space-y-3">
                        @foreach ([
                            ['label' => 'On Track', 'bar' => 'bg-track'],
                            ['label' => 'In Progress', 'bar' => 'bg-warn'],
                            ['label' => 'At Risk', 'bar' => 'bg-risk'],
                            ['label' => 'Not Started', 'bar' => 'bg-navy-200'],
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
                    <h3 class="pf text-base font-bold text-navy-900">Live Alerts</h3>
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
                <h3 class="mb-4 pf text-base font-bold text-navy-900">Resource Utilization</h3>
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
                <h3 class="mb-4 pf text-base font-bold text-navy-900">Budget Overview</h3>
                <div class="flex justify-center">
                    <x-donut :segments="collect($budget['segments'])->map(fn ($segment) => [
                        'pct' => $segment['pct'],
                        'class' => match ($segment['group']) {
                            'risk' => 'stroke-risk',
                            'warn' => 'stroke-warn',
                            'neutral' => 'stroke-navy-200',
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
                                    'bg-navy-200' => $segment['group'] === 'neutral',
                                ])></span>
                            <span class="text-muted">{{ ['track' => 'On-track events', 'warn' => 'In-progress events', 'risk' => 'At-risk events', 'neutral' => 'Not started yet'][$segment['group']] }}</span>
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
