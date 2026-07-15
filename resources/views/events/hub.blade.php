@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :subtitle="($event->avatar?->name ?? str($event->type)->replace('_', ' ')->title()) . '  |  ' . $event->city . ', ' . $event->country . '  |  ' . $event->starts_at?->format('M j') . ' – ' . ($event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y'))">

    {{-- ══ Hero — command strip ══ --}}
    @php
        $hs = $health['score'];
        $daysLeft = $event->starts_at ? ($event->starts_at->isPast() ? 'Started' : (int) now()->diffInDays($event->starts_at).'d left') : '—';
        $healthWord = $hs === null ? 'No data' : ($health['group'] === 'risk' ? 'Behind' : ($health['group'] === 'warn' ? 'At Watch' : 'On Track'));
    @endphp
    <div class="relative overflow-hidden rounded-[22px] bg-gradient-to-br from-navy-900 via-navy-950 to-[#050F1E] shadow-[0_24px_60px_-24px_rgba(11,31,58,0.65)] ring-1 ring-white/[0.06]">
        {{-- ambience --}}
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-gold-400/70 to-transparent"></div>
            <div class="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.16),transparent_65%)]"></div>
        </div>

        <div class="relative flex flex-wrap items-center gap-x-7 gap-y-4 px-5 py-4">

            {{-- Identity: avatar + health --}}
            <div class="flex shrink-0 items-center gap-3.5">
                <x-event-avatar :event="$event" :ring="false" size="md"
                                class="[&>span]:h-[54px] [&>span]:w-[78px] [&>span]:rounded-xl [&>span]:ring-1 [&>span]:ring-white/15" />
                <div class="h-10 w-px bg-white/10"></div>
                <div class="flex items-center gap-2.5">
                    <span class="relative inline-flex h-11 w-11 items-center justify-center">
                        <x-health-ring :percent="$hs ?? 0" :group="$health['group']" size="h-11 w-11" :label="false" />
                        <span class="absolute font-bold text-white">
                            <span class="text-[0.7rem]">{{ $hs ?? '—' }}</span><span class="text-[0.45rem] text-white/50">%</span>
                        </span>
                    </span>
                    <div class="leading-tight">
                        <p class="text-[0.48rem] font-bold uppercase tracking-[0.2em] text-gold-400/80">Health</p>
                        <p class="text-[0.82rem] font-bold text-white">{{ $healthWord }}</p>
                    </div>
                </div>
            </div>

            <div class="hidden h-12 w-px bg-white/10 lg:block"></div>

            {{-- Meta --}}
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-1.5">
                    <span class="rounded-full bg-gold-400/15 px-2.5 py-0.5 text-[0.6rem] font-bold uppercase tracking-wide text-gold-300 ring-1 ring-gold-400/30">{{ str($event->stage)->replace('_', ' ')->title() }}</span>
                    @if ($health['pending_approvals'] > 0)
                        <span class="rounded-full bg-blue-400/15 px-2.5 py-0.5 text-[0.6rem] font-bold text-blue-300 ring-1 ring-blue-400/30">{{ $health['pending_approvals'] }} pending</span>
                    @endif
                    <a href="{{ route('home') }}" class="ml-1 inline-flex items-center gap-1 text-[0.62rem] font-bold text-white/45 transition hover:text-gold-300">
                        <x-icon name="sparkles" class="h-3 w-3 shrink-0" /> Operations Hub →
                    </a>
                </div>
                <dl class="flex flex-wrap items-center gap-x-6 gap-y-1.5">
                    @foreach ([
                        ['Client', $event->client?->name ?? '—'],
                        ['Venue', $event->venue?->name ?? 'Not assigned'],
                        ['Participants', $event->expected_participants ? number_format($event->expected_participants).' pax' : '—'],
                        ['Project Manager', $event->projectManager?->name ?? '—'],
                    ] as [$label, $value])
                        <div class="min-w-0">
                            <dt class="text-[0.48rem] font-bold uppercase tracking-[0.16em] text-gold-400/70">{{ $label }}</dt>
                            <dd class="truncate text-[0.82rem] font-semibold text-white">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </div>

            {{-- Right: gauges · dates · actions --}}
            <div class="ml-auto flex flex-wrap items-center gap-x-5 gap-y-3">
                <div class="flex items-center gap-4">
                    @foreach ([
                        ['Budget', $health['components']['budget']],
                        ['Tasks', $health['components']['tasks']],
                        ['Suppliers', $health['components']['suppliers']],
                    ] as [$label, $s])
                        @php
                            $fill = $s === null ? 'bg-white/20' : ($s >= 81 ? 'bg-emerald-400' : ($s >= 61 ? 'bg-amber-400' : 'bg-red-400'));
                            $txt  = $s === null ? 'text-white/35' : ($s >= 81 ? 'text-emerald-300' : ($s >= 61 ? 'text-amber-300' : 'text-red-300'));
                        @endphp
                        <div class="w-[62px]">
                            <div class="flex items-baseline justify-between gap-1">
                                <span class="text-[0.48rem] font-bold uppercase tracking-[0.12em] text-white/40">{{ $label }}</span>
                                <span class="text-[0.72rem] font-bold {{ $txt }}">{{ $s !== null ? $s : '—' }}</span>
                            </div>
                            <div class="mt-1 h-[3px] w-full overflow-hidden rounded-full bg-white/10">
                                <div class="h-full rounded-full {{ $fill }}" style="width: {{ $s ?? 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <div class="hidden h-10 w-px bg-white/10 sm:block"></div>

                <div class="text-right leading-tight">
                    <p class="text-[0.82rem] font-bold text-white">{{ $event->starts_at?->format('M j') }} – {{ $event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y') }}</p>
                    <p class="text-[0.64rem] font-bold {{ $event->starts_at?->isPast() ? 'text-white/40' : 'text-gold-400' }}">{{ $daysLeft }}</p>
                </div>

                <div class="flex items-center gap-2">
                    <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}"
                       class="flex h-9 items-center rounded-xl bg-white/[0.06] px-3 text-xs font-semibold text-white/70 ring-1 ring-white/10 transition hover:bg-white/10 hover:text-white">✎ Edit</a>
                    <details class="group relative">
                        <summary class="flex h-9 w-fit cursor-pointer list-none items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-3.5 text-xs font-bold text-navy-950 shadow-[0_6px_18px_-6px_rgba(212,175,55,0.8)] transition hover:brightness-105 [&::-webkit-details-marker]:hidden">
                            ⚡ Quick Actions ▾
                        </summary>
                        <div class="absolute right-0 top-11 z-30 w-56 overflow-hidden rounded-xl border border-white/10 bg-navy-950 shadow-2xl ring-1 ring-black/20">
                            @foreach ([
                                ['tasks', '＋ Add Task', 'clipboard'],
                                ['budget', '＋ Add Budget Line', 'currency'],
                                ['risks', '＋ Register Risk', 'bell'],
                                ['approvals', '＋ Request Approval', 'identification'],
                            ] as [$actionTab, $label, $icon])
                                <a href="{{ route('events.hub', [$event, 'tab' => $actionTab, 'action' => 'add']) }}"
                                   class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-white/70 transition hover:bg-white/5 hover:text-white">
                                    <x-icon :name="$icon" class="h-4 w-4 text-gold-400/80" /> {{ $label }}
                                </a>
                            @endforeach
                            @if ($event->moduleEnabled('planning') || $event->moduleEnabled('agenda'))
                                <div class="border-t border-white/10">
                                    @if ($event->moduleEnabled('planning'))
                                        <a href="{{ route('events.planning.pdf', $event) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-white/70 transition hover:bg-white/5 hover:text-white"><x-icon name="chart" class="h-4 w-4 text-gold-400/80" /> Export Planning PDF</a>
                                    @endif
                                    @if ($event->moduleEnabled('agenda'))
                                        <a href="{{ route('events.agenda.pdf', $event) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-white/70 transition hover:bg-white/5 hover:text-white"><x-icon name="calendar" class="h-4 w-4 text-gold-400/80" /> Export Agenda PDF</a>
                                    @endif
                                </div>
                            @endif
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>

    {{-- ══ Module rail: grouped pills that wrap — every enabled module stays visible, nothing scrolls away ══ --}}
    @php
        // Grouped so the rail reads as a control panel, not a run-on list.
        $groups = [
            'Event' => ['overview' => ['Overview', 'home'], 'brief' => ['Brief', 'clipboard']],
            'Plan' => ['planning' => ['Planning', 'list'], 'tasks' => ['Tasks', 'clipboard'], 'budget' => ['Budget', 'currency'], 'risks' => ['Risks', 'bell'], 'approvals' => ['Approvals', 'identification']],
            'Programme' => ['agenda' => ['Agenda', 'calendar'], 'speakers' => ['Speakers', 'identification']],
            'Logistics' => ['venue' => ['Venue', 'building'], 'suppliers' => ['Suppliers', 'truck'], 'transportation' => ['Transport', 'truck'], 'accommodation' => ['Stay', 'home']],
            'Commercial' => ['exhibition' => ['Exhibition', 'grid'], 'sponsors' => ['Sponsors', 'star'], 'attendees' => ['Attendees', 'users']],
            'Grow' => ['files' => ['Files', 'archive'], 'reports' => ['Reports', 'chart']],
            'System' => ['ai' => ['AI', 'sparkles'], 'settings' => ['Settings', 'cog']],
        ];
    @endphp
    <div class="sticky top-0 z-20 mt-3">
        <nav class="flex flex-wrap items-center gap-1.5 rounded-2xl border border-line bg-gradient-to-b from-white to-page/60 px-2.5 py-2 shadow-[0_8px_24px_-10px_rgba(11,31,58,0.18)] backdrop-blur-sm" aria-label="Event modules">
            @foreach ($groups as $groupName => $tabs)
                @php $visible = collect($tabs)->filter(fn ($t, $key) => $event->moduleEnabled($key)); @endphp
                @continue ($visible->isEmpty())

                {{-- one segmented cluster per group — it wraps as a whole, never splitting mid-group --}}
                <div class="flex items-center gap-0.5 rounded-xl bg-navy-50/70 p-[3px] ring-1 ring-line/70" role="group" aria-label="{{ $groupName }}">
                    @foreach ($visible as $key => [$label, $icon])
                        <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}" title="{{ $groupName }} · {{ $label }}"
                           @class([
                               'flex h-[30px] items-center gap-1.5 whitespace-nowrap rounded-[9px] px-2.5 text-[12px] font-semibold tracking-tight transition-all duration-150',
                               'bg-navy-900 text-white shadow-[0_4px_12px_-4px_rgba(11,31,58,0.55)]' => $tab === $key,
                               'text-navy-500 hover:bg-white hover:text-navy-900 hover:shadow-sm' => $tab !== $key,
                           ])>
                            <x-icon :name="$icon" @class(['h-3.5 w-3.5 shrink-0', 'text-gold-400' => $tab === $key, 'text-navy-400' => $tab !== $key]) />
                            {{ $label }}
                        </a>
                    @endforeach
                </div>
            @endforeach
        </nav>
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
