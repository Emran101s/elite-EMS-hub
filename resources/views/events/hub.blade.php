@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :subtitle="($event->avatar?->name ?? str($event->type)->replace('_', ' ')->title()) . '  |  ' . $event->city . ', ' . $event->country . '  |  ' . $event->starts_at?->format('M j') . ' – ' . ($event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y'))">

    {{-- ══ Hero ══ --}}
    <div class="card overflow-hidden !rounded-[28px]">
        <div class="h-1.5" style="background: linear-gradient(90deg, {{ $theme['primary'] }}, {{ $theme['accent'] }})"></div>
        <div class="grid gap-5 p-5 xl:grid-cols-[minmax(0,128px)_minmax(0,300px)_minmax(0,1fr)_minmax(0,23rem)]">

            {{-- Health ring + Operations Hub link --}}
            <div class="flex flex-col items-center justify-center gap-3">
                <div class="flex flex-col items-center">
                    <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-[96px] w-[96px]" textSize="text-xl" dark />
                    <p class="mt-1 text-[0.6rem] font-semibold uppercase tracking-wide text-muted">Health Score</p>
                </div>
                <a href="{{ route('home') }}" class="flex w-full items-center justify-center gap-1.5 rounded-xl border border-gold-300 bg-gold-50 px-2 py-2 text-center text-[0.62rem] font-bold leading-tight text-gold-700 transition hover:bg-gold-100">
                    <x-icon name="sparkles" class="h-3 w-3 shrink-0" /> View in Operations Hub
                </a>
            </div>

            {{-- Avatar visual --}}
            <div class="relative overflow-hidden rounded-2xl">
                <x-event-avatar :event="$event" :ring="false" size="xl" class="block h-full w-full [&>span]:h-full [&>span]:min-h-[172px] [&>span]:w-full [&>span]:rounded-2xl [&>span]:!bg-transparent" />
            </div>

            {{-- Meta column (2-up grid) --}}
            <div class="min-w-0">
                <div class="mb-2.5 flex flex-wrap items-center gap-2">
                    <x-status-badge :status="$event->stage" />
                    <x-status-badge :status="$health['status']" />
                    @if ($health['pending_approvals'] > 0)
                        <span class="inline-flex items-center rounded-full bg-[#3B82F6]/10 px-2 py-0.5 text-[0.65rem] font-semibold text-blue-700 ring-1 ring-[#3B82F6]/40">
                            {{ $health['pending_approvals'] }} pending {{ str('approval')->plural($health['pending_approvals']) }}
                        </span>
                    @endif
                </div>
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2">
                    @foreach ([
                        ['identification', 'Client', $event->client?->name ?? '—'],
                        ['calendar', 'Event Type', $event->avatar?->name ?? str($event->type)->replace('_', ' ')->title()],
                        ['building', 'Venue', $event->venue?->name ?? 'Not assigned'],
                        ['users', 'Participants', $event->expected_participants ? number_format($event->expected_participants).' pax' : '—'],
                        ['users', 'Project Manager', $event->projectManager?->name ?? '—'],
                        ['home', 'Event Owner', 'Elite Business Hub'],
                    ] as [$icon, $label, $value])
                        <div class="flex items-center gap-2">
                            <span class="flex h-7 w-7 shrink-0 items-center justify-center rounded-lg bg-navy-50 text-navy-600"><x-icon :name="$icon" class="h-3.5 w-3.5" /></span>
                            <div class="min-w-0">
                                <dt class="text-[0.55rem] font-semibold uppercase tracking-wide text-muted">{{ $label }}</dt>
                                <dd class="truncate text-xs font-semibold text-navy-900">{{ $value }}</dd>
                            </div>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Actions + stat chips + dates --}}
            <div class="flex flex-col gap-3">
                <div class="flex items-center justify-end gap-2">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="rounded-xl border border-line bg-white px-3 py-1.5 text-xs font-semibold text-navy-700 transition hover:border-gold-300">✎ Edit</a>
                    <details class="group relative">
                        <summary class="btn-navy flex w-fit cursor-pointer list-none px-3 py-1.5 text-xs [&::-webkit-details-marker]:hidden">
                            <span class="text-gold-400">⚡</span> Quick Actions ▾
                        </summary>
                        <div class="absolute right-0 top-9 z-30 w-52 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                            @foreach ([
                                ['tasks', '＋ Add Task', 'clipboard'],
                                ['budget', '＋ Add Budget Line', 'currency'],
                                ['risks', '＋ Register Risk', 'bell'],
                                ['approvals', '＋ Request Approval', 'identification'],
                            ] as [$actionTab, $label, $icon])
                                <a href="{{ route('events.hub', [$event, 'tab' => $actionTab, 'action' => 'add']) }}"
                                   class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-gold-50/60">
                                    <x-icon :name="$icon" class="h-4 w-4 text-navy-500" /> {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </details>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    @foreach ([
                        ['label' => 'Budget Health', 'score' => $health['components']['budget'], 'word' => ['Healthy', 'Watch', 'Over']],
                        ['label' => 'Tasks Completed', 'score' => $health['components']['tasks'], 'word' => ['Strong', 'In Progress', 'Behind']],
                        ['label' => 'Supplier Readiness', 'score' => $health['components']['suppliers'], 'word' => ['Good', 'Building', 'Weak']],
                    ] as $chip)
                        @php $s = $chip['score']; @endphp
                        <div class="flex min-h-[62px] flex-col justify-center rounded-2xl border border-line bg-white px-2.5 py-2 text-center shadow-[0_1px_3px_rgba(11,31,58,0.04)]">
                            <p class="text-[0.52rem] font-semibold leading-tight text-muted">{{ $chip['label'] }}</p>
                            <p class="mt-0.5 text-lg font-bold leading-none {{ $s === null ? 'text-muted' : ($s >= 81 ? 'text-emerald-600' : ($s >= 61 ? 'text-amber-600' : 'text-risk')) }}">
                                {{ $s !== null ? $s.'%' : '—' }}
                            </p>
                            @if ($s !== null)
                                <p class="mt-0.5 text-[0.55rem] font-semibold {{ $s >= 81 ? 'text-emerald-600' : ($s >= 61 ? 'text-amber-600' : 'text-risk') }}">
                                    {{ $chip['word'][$s >= 81 ? 0 : ($s >= 61 ? 1 : 2)] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-3 items-center divide-x divide-line rounded-2xl border border-line bg-white py-2 shadow-[0_1px_3px_rgba(11,31,58,0.04)]">
                    @foreach ([
                        ['Start', $event->starts_at?->format('M j, Y') ?? '—', 'text-navy-900'],
                        ['End', $event->ends_at?->format('M j, Y') ?? '—', 'text-navy-900'],
                        ['Days Left', $event->starts_at ? ($event->starts_at->isPast() ? 'Started' : (int) now()->diffInDays($event->starts_at).'d') : '—', $event->starts_at?->isPast() ? 'text-muted' : 'text-gold-600'],
                    ] as [$dl, $dv, $dc])
                        <div class="px-3">
                            <p class="text-[0.52rem] font-semibold text-muted">{{ $dl }}</p>
                            <p class="mt-0.5 text-[0.8rem] font-bold {{ $dc }}">{{ $dv }}</p>
                        </div>
                    @endforeach
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
                @continue (! $event->moduleEnabled($key))
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

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
