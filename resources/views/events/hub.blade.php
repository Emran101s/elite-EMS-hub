@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'" :subtitle="'Control room for every operation of ' . $event->name . '.'">

    {{-- ══ Hero ══ --}}
    <div class="card overflow-hidden">
        <div class="h-1.5" style="background: linear-gradient(90deg, {{ $theme['primary'] }}, {{ $theme['accent'] }})"></div>
        <div class="grid gap-6 p-6 lg:grid-cols-[minmax(0,22rem)_minmax(0,1fr)_minmax(0,22rem)]">

            {{-- Avatar visual + health --}}
            <div>
                <div class="relative overflow-hidden rounded-2xl ring-2" style="--tw-ring-color: {{ $theme['accent'] }}55">
                    <x-event-avatar :event="$event" :ring="false" size="xl" class="block w-full [&>span]:h-44 [&>span]:w-full [&>span]:rounded-none [&>span]:ring-0" />
                    <span class="absolute bottom-2 left-2 flex flex-col items-center rounded-2xl bg-white/95 px-2.5 py-2 shadow-lg backdrop-blur">
                        <x-health-ring :percent="$health['score']" :group="$health['group']" size="h-14 w-14" />
                        <span class="mt-0.5 text-[0.55rem] font-bold uppercase tracking-wide text-muted">Health Score</span>
                    </span>
                </div>
                <a href="{{ route('home') }}" class="mt-3 flex items-center justify-center gap-2 rounded-xl border border-gold-300 bg-gold-50 px-4 py-2.5 text-xs font-bold text-gold-700 transition hover:bg-gold-100">
                    <x-icon name="sparkles" class="h-3.5 w-3.5" /> View Event in Operations Hub
                </a>
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
                        ['calendar', 'Event Type', str($event->type)->replace('_', ' ')->title()],
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

            {{-- Stat chips + dates + actions --}}
            <div class="flex flex-col gap-4">
                <div class="flex justify-end gap-2">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="rounded-xl border border-line bg-white px-3.5 py-2 text-xs font-semibold text-navy-700 transition hover:border-gold-300">✎ Edit Event</a>
                    <span class="btn-navy cursor-default px-3.5 py-2 text-xs">⚡ Quick Actions</span>
                </div>

                <div class="grid grid-cols-3 gap-2">
                    @foreach ([
                        ['label' => 'Budget Health', 'score' => $health['components']['budget'], 'word' => ['Healthy', 'Watch', 'Over']],
                        ['label' => 'Tasks Completed', 'score' => $health['components']['tasks'], 'word' => ['Strong', 'In Progress', 'Behind']],
                        ['label' => 'Supplier Readiness', 'score' => $health['components']['suppliers'], 'word' => ['Good', 'Building', 'Weak']],
                    ] as $chip)
                        @php $s = $chip['score']; @endphp
                        <div class="rounded-2xl border border-line bg-page/60 px-3 py-3 text-center">
                            <p class="text-lg font-bold {{ $s === null ? 'text-muted' : ($s >= 81 ? 'text-emerald-600' : ($s >= 61 ? 'text-amber-600' : 'text-risk')) }}">
                                {{ $s !== null ? $s.'%' : '—' }}
                            </p>
                            <p class="text-[0.55rem] font-semibold uppercase tracking-wide text-muted">{{ $chip['label'] }}</p>
                            @if ($s !== null)
                                <p class="mt-0.5 text-[0.6rem] font-semibold {{ $s >= 81 ? 'text-emerald-600' : ($s >= 61 ? 'text-amber-600' : 'text-risk') }}">
                                    {{ $chip['word'][$s >= 81 ? 0 : ($s >= 61 ? 1 : 2)] }}
                                </p>
                            @endif
                        </div>
                    @endforeach
                </div>

                <div class="grid grid-cols-3 gap-2 rounded-2xl border border-line bg-page/60 px-4 py-3">
                    <div>
                        <p class="text-[0.55rem] font-semibold uppercase tracking-wide text-muted">Start Date</p>
                        <p class="mt-0.5 text-xs font-bold text-navy-900">{{ $event->starts_at?->format('M j, Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[0.55rem] font-semibold uppercase tracking-wide text-muted">End Date</p>
                        <p class="mt-0.5 text-xs font-bold text-navy-900">{{ $event->ends_at?->format('M j, Y') ?? '—' }}</p>
                    </div>
                    <div>
                        <p class="text-[0.55rem] font-semibold uppercase tracking-wide text-muted">Days Left</p>
                        <p class="mt-0.5 text-xs font-bold {{ $event->starts_at?->isPast() ? 'text-muted' : 'text-gold-600' }}">
                            {{ $event->starts_at ? ($event->starts_at->isPast() ? 'Started' : (int) now()->diffInDays($event->starts_at).' Days') : '—' }}
                        </p>
                    </div>
                </div>
            </div>
        </div>

        {{-- ══ Tabs ══ --}}
        <nav class="scrollbar-none flex gap-1 overflow-x-auto border-t border-line bg-white px-3 py-2" aria-label="Event hub">
            @foreach ([
                'overview' => 'Overview', 'agenda' => 'Agenda', 'tasks' => 'Tasks', 'budget' => 'Budget',
                'suppliers' => 'Suppliers', 'venue' => 'Venue', 'sponsors' => 'Sponsors', 'attendees' => 'Attendees',
                'files' => 'Files', 'risks' => 'Risks', 'approvals' => 'Approvals', 'reports' => 'Reports',
                'ai' => 'AI Insights', 'settings' => 'Settings',
            ] as $key => $label)
                <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                   @class([
                       'whitespace-nowrap rounded-xl px-3.5 py-2 text-xs font-semibold transition',
                       'bg-gold-50 text-gold-700 ring-1 ring-gold-200' => $tab === $key,
                       'text-navy-600 hover:bg-navy-50 hover:text-navy-900' => $tab !== $key,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-6">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai])
    </div>
</x-layouts.app>
