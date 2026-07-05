@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :subtitle="($event->avatar?->name ?? str($event->type)->replace('_', ' ')->title()) . '  |  ' . $event->city . ', ' . $event->country . '  |  ' . $event->starts_at?->format('M j') . ' – ' . ($event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y'))">

    {{-- ══ Hero ══ --}}
    <div class="card overflow-hidden !rounded-[28px]">
        <div class="h-1.5" style="background: linear-gradient(90deg, {{ $theme['primary'] }}, {{ $theme['accent'] }})"></div>
        <div class="grid gap-6 p-6 xl:grid-cols-[minmax(0,150px)_minmax(0,430px)_minmax(0,1fr)_minmax(0,24rem)]">

            {{-- Health ring + Operations Hub link --}}
            <div class="flex flex-col items-center justify-center gap-4">
                <div class="flex flex-col items-center">
                    <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-[120px] w-[120px]" textSize="text-[24px]" dark />
                    <p class="mt-1.5 text-[0.65rem] font-semibold uppercase tracking-wide text-muted">Health Score</p>
                </div>
                <a href="{{ route('home') }}" class="flex w-full items-center justify-center gap-2 rounded-xl border border-gold-300 bg-gold-50 px-3 py-2.5 text-center text-[0.68rem] font-bold text-gold-700 transition hover:bg-gold-100">
                    <x-icon name="sparkles" class="h-3.5 w-3.5 shrink-0" /> View Event in Operations Hub
                </a>
            </div>

            {{-- Avatar visual (430×240 area) --}}
            <div class="relative overflow-hidden rounded-2xl">
                <x-event-avatar :event="$event" :ring="false" size="xl" class="block w-full [&>span]:h-[240px] [&>span]:w-full [&>span]:rounded-2xl [&>span]:bg-white" />
            </div>

            {{-- Meta column --}}
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-center gap-2.5">
                    <x-status-badge :status="$event->stage" />
                    <x-status-badge :status="$health['status']" />
                    @if ($health['pending_approvals'] > 0)
                        <span class="inline-flex items-center rounded-full bg-[#3B82F6]/10 px-2.5 py-0.5 text-xs font-semibold text-blue-700 ring-1 ring-[#3B82F6]/40">
                            {{ $health['pending_approvals'] }} pending {{ str('approval')->plural($health['pending_approvals']) }}
                        </span>
                    @endif
                </div>
                <dl class="space-y-2.5 text-sm">
                    @foreach ([
                        ['identification', 'Client', $event->client?->name ?? '—'],
                        ['calendar', 'Event Type', $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title()],
                        ['building', 'Venue', $event->venue?->name ?? 'Not assigned'],
                        ['users', 'Participants', $event->expected_participants ? number_format($event->expected_participants).' Registered' : '—'],
                        ['users', 'Project Manager', $event->projectManager?->name ?? '—'],
                        ['home', 'Event Owner', 'Elite Business Hub'],
                    ] as [$icon, $label, $value])
                        <div class="flex items-center gap-3">
                            <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-navy-600"><x-icon :name="$icon" class="h-4 w-4" /></span>
                            <div class="min-w-0">
                                <dt class="text-[0.6rem] font-semibold uppercase tracking-wide text-muted">{{ $label }}</dt>
                                <dd class="truncate text-xs font-semibold text-navy-900">{{ $value }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Actions + stat chips + dates --}}
            <div class="flex flex-col gap-4">
                <div class="flex justify-end gap-2">
                    <span class="btn-navy cursor-default px-3.5 py-2 text-xs"><span class="text-gold-400">⚡</span> Quick Actions ▾</span>
                </div>
                <div class="flex justify-end">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="rounded-xl border border-line bg-white px-3.5 py-2 text-xs font-semibold text-navy-700 transition hover:border-gold-300">✎ Edit Event</a>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    @foreach ([
                        ['label' => 'Budget Health', 'score' => $health['components']['budget'], 'word' => ['Healthy', 'Watch', 'Over']],
                        ['label' => 'Tasks Completed', 'score' => $health['components']['tasks'], 'word' => ['Strong', 'In Progress', 'Behind']],
                        ['label' => 'Supplier Readiness', 'score' => $health['components']['suppliers'], 'word' => ['Good', 'Building', 'Weak']],
                    ] as $chip)
                        @php $s = $chip['score']; @endphp
                        <div class="flex min-h-[82px] flex-col justify-center rounded-2xl border border-line bg-white px-3 py-2 text-center shadow-[0_1px_3px_rgba(11,31,58,0.04)]">
                            <p class="text-[0.55rem] font-semibold text-muted">{{ $chip['label'] }}</p>
                            <p class="mt-1 text-xl font-bold {{ $s === null ? 'text-muted' : ($s >= 81 ? 'text-emerald-600' : ($s >= 61 ? 'text-amber-600' : 'text-risk')) }}">
                                {{ $s !== null ? $s.'%' : '—' }}
                            </p>
                            @if ($s !== null)
                                <p class="mt-0.5 text-[0.6rem] font-semibold {{ $s >= 81 ? 'text-emerald-600' : ($s >= 61 ? 'text-amber-600' : 'text-risk') }}">
                                    {{ $chip['word'][$s >= 81 ? 0 : ($s >= 61 ? 1 : 2)] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="grid min-h-[80px] grid-cols-3 items-center divide-x divide-line rounded-2xl border border-line bg-white px-1 py-2 shadow-[0_1px_3px_rgba(11,31,58,0.04)]">
                    <div class="px-3">
                        <p class="text-[0.55rem] font-semibold text-muted">Start Date</p>
                        <p class="mt-0.5 text-sm font-bold text-navy-900">{{ $event->starts_at?->format('M j, Y') ?? '—' }}</p>
                    </div>
                    <div class="px-3">
                        <p class="text-[0.55rem] font-semibold text-muted">End Date</p>
                        <p class="mt-0.5 text-sm font-bold text-navy-900">{{ $event->ends_at?->format('M j, Y') ?? '—' }}</p>
                    </div>
                    <div class="px-3">
                        <p class="text-[0.55rem] font-semibold text-muted">Days Left</p>
                        <p class="mt-0.5 text-sm font-bold {{ $event->starts_at?->isPast() ? 'text-muted' : 'text-navy-900' }}">
                            {{ $event->starts_at ? ($event->starts_at->isPast() ? 'Started' : (int) now()->diffInDays($event->starts_at).' Days') : '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ Tabs: gold underline ══ --}}
        <nav class="scrollbar-none flex gap-1 overflow-x-auto border-t border-line bg-white px-4" aria-label="Event hub">
            @foreach ([
                'overview' => ['Overview', 'home'], 'agenda' => ['Agenda', 'calendar'], 'tasks' => ['Tasks', 'clipboard'],
                'budget' => ['Budget', 'currency'], 'suppliers' => ['Suppliers', 'truck'], 'venue' => ['Venue', 'building'],
                'sponsors' => ['Sponsors', 'star'], 'attendees' => ['Attendees', 'users'], 'files' => ['Files', 'archive'],
                'risks' => ['Risks', 'bell'], 'approvals' => ['Approvals', 'identification'], 'reports' => ['Reports', 'chart'],
                'ai' => ['AI Insights', 'sparkles'], 'settings' => ['Settings', 'cog'],
            ] as $key => [$label, $icon])
                <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                   @class([
                       'flex h-14 items-center gap-1.5 whitespace-nowrap border-b-2 px-3.5 text-[13px] font-semibold transition',
                       'border-gold-500 bg-[#FFF7E6] text-gold-700' => $tab === $key,
                       'border-transparent text-navy-600 hover:text-navy-900' => $tab !== $key,
                   ])>
                    <x-icon :name="$icon" class="h-3.5 w-3.5 shrink-0" /> {{ $label }}
                </a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
