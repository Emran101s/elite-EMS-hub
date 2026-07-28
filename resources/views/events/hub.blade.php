@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :subtitle="str($event->type)->replace('_', ' ')->title() . '  |  ' . $event->city . ', ' . $event->country . '  |  ' . $event->starts_at?->format('M j') . ' – ' . ($event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y'))"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Events', 'href' => route('events.index')],
                   ['label' => $event->name, 'href' => route('events.hub', $event)],
                   ['label' => \App\Models\Event::moduleLabel($tab)],
               ]">

    {{-- ══ Identity bar ══
         This used to be a 90px navy slab under a 55px band of boxed chips —
         145px of chrome before a single figure. It is the last large dark mass
         in the platform, and with the sidebar and the KPI strip gone it had
         become the odd one out.

         Now: one light bar on the workspace. Navy survives in two places, the
         crest and Quick Actions, which is the whole rule of this palette. The
         gold field labels are gone — a client name after a building icon does
         not need the word CLIENT above it. ══ --}}
    @php
        $hs = $health['score'];
        $daysLeft = $event->starts_at
            ? ($event->starts_at->isPast() ? 'Started' : (int) now()->diffInDays($event->starts_at).'d left')
            : null;
        $healthWord = $hs === null ? 'Not started'
            : ($health['group'] === 'risk' ? 'Behind' : ($health['group'] === 'warn' ? 'At watch' : 'On track'));
        $meters = [
            ['Budget', $health['components']['budget']],
            ['Tasks', $health['components']['tasks']],
            ['Suppliers', $health['components']['suppliers']],
        ];
    @endphp

    <div class="rounded-2xl border border-line bg-white px-4 py-2.5 shadow-[0_10px_26px_-20px_rgba(11,31,58,0.4)]">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">

            {{-- crest · name · meta --}}
            <div class="flex min-w-0 flex-1 items-center gap-3">
                <x-event-avatar :event="$event" :ring="false" size="sm"
                                class="shrink-0 [&>span]:h-10 [&>span]:w-[52px] [&>span]:rounded-lg" />

                <div class="min-w-0">
                    <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                        <h2 class="pf truncate text-[16px] font-bold leading-tight text-navy-900">{{ $event->name }}</h2>
                        <span class="chip">{{ str($event->stage)->replace('_', ' ')->title() }}</span>
                        @if ($health['pending_approvals'] > 0)
                            <span class="chip-gold">{{ $health['pending_approvals'] }} pending</span>
                        @endif
                    </div>

                    {{-- the four facts, inline. Icons carry the labels. --}}
                    <div class="scrollbar-none mt-0.5 flex items-center gap-x-3 overflow-hidden whitespace-nowrap text-[11.5px] text-muted">
                        <span class="flex items-center gap-1 truncate"><x-icon name="identification" class="h-3 w-3 shrink-0 text-navy-300" />{{ $event->client?->name ?? 'No client' }}</span>
                        <span class="flex items-center gap-1 truncate"><x-icon name="building" class="h-3 w-3 shrink-0 text-navy-300" />{{ $event->venue?->name ?? 'Venue TBC' }}</span>
                        <span class="flex items-center gap-1"><x-icon name="users" class="h-3 w-3 shrink-0 text-navy-300" />{{ $event->expected_participants ? number_format($event->expected_participants).' pax' : '—' }}</span>
                        <span class="flex items-center gap-1 truncate"><x-icon name="sparkles" class="h-3 w-3 shrink-0 text-navy-300" />{{ $event->projectManager?->name ?? 'No PM' }}</span>
                    </div>
                </div>
            </div>

            {{-- health · the three meters --}}
            <div class="flex shrink-0 items-center gap-4">
                <div class="flex items-center gap-2">
                    <x-health-ring :percent="$hs" :group="$health['group']" size="h-9 w-9" textSize="text-[9px]" />
                    <span class="leading-tight">
                        <span class="eyebrow block">Health</span>
                        <span class="block text-[12px] font-bold text-navy-900">{{ $healthWord }}</span>
                    </span>
                </div>

                <div class="hidden items-center gap-3 border-l border-line pl-4 lg:flex">
                    @foreach ($meters as [$label, $score])
                        <div class="w-[58px]">
                            <div class="flex items-baseline justify-between gap-1">
                                <span class="text-[8.5px] font-bold uppercase tracking-[0.1em] text-navy-300">{{ $label }}</span>
                                <span class="text-[10.5px] font-bold tabular-nums text-navy-700">{{ $score ?? '—' }}</span>
                            </div>
                            <div class="mt-1 h-[3px] overflow-hidden rounded-full bg-navy-50">
                                <div @class([
                                    'h-full rounded-full',
                                    'bg-track' => $score !== null && $score >= 81,
                                    'bg-warn' => $score !== null && $score >= 61 && $score < 81,
                                    'bg-risk' => $score !== null && $score < 61,
                                ]) style="width: {{ $score ?? 0 }}%"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- dates · actions --}}
            <div class="flex shrink-0 items-center gap-3 border-l border-line pl-4">
                <div class="text-right leading-tight">
                    <p class="text-[12px] font-bold text-navy-900">{{ $event->starts_at?->format('d M') }} – {{ $event->ends_at?->format('d M Y') ?? $event->starts_at?->format('Y') }}</p>
                    @if ($daysLeft)
                        <p class="text-[10.5px] font-semibold {{ $event->starts_at?->isPast() ? 'text-muted' : 'text-gold-600' }}">{{ $daysLeft }}</p>
                    @endif
                </div>

                <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="btn-ghost btn-sm">✎ Edit</a>

                <details class="group relative">
                    <summary class="btn-gold btn-sm cursor-pointer list-none [&::-webkit-details-marker]:hidden">⚡ Quick Actions ▾</summary>
                    <div class="absolute right-0 top-10 z-30 w-56 overflow-hidden rounded-xl border border-line bg-white shadow-lg">
                        @foreach ([
                            ['tasks', '＋ Add Task', 'clipboard'],
                            ['budget', '＋ Add Budget Line', 'currency'],
                            ['risks', '＋ Register Risk', 'bell'],
                            ['approvals', '＋ Request Approval', 'identification'],
                        ] as [$actionTab, $label, $icon])
                            <a href="{{ route('events.hub', [$event, 'tab' => $actionTab, 'action' => 'add']) }}"
                               class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-navy-50">
                                <x-icon :name="$icon" class="h-4 w-4 text-gold-600" /> {{ $label }}
                            </a>
                        @endforeach
                        <div class="border-t border-line">
                            <a href="{{ route('home') }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-navy-50">
                                <x-icon name="sparkles" class="h-4 w-4 text-gold-600" /> Operations Hub
                            </a>
                            @if ($event->moduleEnabled('planning'))
                                <a href="{{ route('events.planning.pdf', $event) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-navy-50"><x-icon name="chart" class="h-4 w-4 text-gold-600" /> Export Planning PDF</a>
                            @endif
                            @if ($event->moduleEnabled('agenda'))
                                <a href="{{ route('events.agenda.master.pdf', $event) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-navy-700 transition hover:bg-navy-50"><x-icon name="calendar" class="h-4 w-4 text-gold-600" /> Export Agenda PDF</a>
                            @endif
                        </div>
                    </div>
                </details>
            </div>
        </div>
    </div>

    {{-- ══ Module tabs ══
         Twenty boxed chips wrapped onto two rows and read as a wall. Underlined
         text in one scrollable row is far quieter, and it draws the hierarchy:
         pills at the top are the platform, underlines here are this event. ══ --}}
    @php
        $modules = [
            'overview' => 'Overview', 'brief' => 'Brief', 'contract' => 'Contract',
            'planning' => 'Planning', 'tasks' => 'Tasks', 'budget' => 'Budget',
            'risks' => 'Risks', 'approvals' => 'Approvals',
            'agenda' => 'Agenda', 'speakers' => 'Speakers',
            'venue' => 'Venue', 'suppliers' => 'Suppliers', 'transportation' => 'Transport', 'accommodation' => 'Stay',
            'exhibition' => 'Exhibition', 'sponsors' => 'Sponsors', 'attendees' => 'Attendees',
            'files' => 'Files', 'reports' => 'Reports',
            'ai' => 'AI', 'settings' => 'Settings',
        ];
    @endphp
    <div class="sticky top-0 z-20 -mx-1 mt-3 border-b border-line bg-page/90 px-1 backdrop-blur">
        <nav class="scrollbar-none flex items-center gap-1 overflow-x-auto" aria-label="Event modules">
            @foreach ($modules as $key => $label)
                @continue (! $event->moduleEnabled($key))
                <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                   @if ($tab === $key) aria-current="page" @endif
                   @class([
                       'relative shrink-0 whitespace-nowrap px-3 py-2.5 text-[12.5px] transition',
                       'font-bold text-navy-900 after:absolute after:inset-x-2 after:-bottom-px after:h-[2.5px] after:rounded-full after:bg-gold-500' => $tab === $key,
                       'font-medium text-navy-500 hover:text-navy-900' => $tab !== $key,
                   ])>{{ $label }}</a>
            @endforeach
        </nav>
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
