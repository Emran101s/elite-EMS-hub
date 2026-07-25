@php
    $theme = $event->theme();
    // Event module navigation → the floating right rail (icons, all enabled modules).
    $modNav = [
        'overview' => ['Overview', 'home'], 'planning' => ['Planning', 'list'], 'tasks' => ['Tasks', 'clipboard'],
        'budget' => ['Budget', 'currency'], 'agenda' => ['Agenda', 'calendar'], 'speakers' => ['Speakers', 'identification'],
        'venue' => ['Venue', 'building'], 'suppliers' => ['Suppliers', 'truck'], 'transportation' => ['Transport', 'truck'],
        'accommodation' => ['Accommodation', 'home'], 'exhibition' => ['Exhibition', 'grid'], 'sponsors' => ['Sponsors', 'star'],
        'attendees' => ['Attendees', 'users'], 'brief' => ['Event Brief', 'clipboard'], 'contract' => ['Contract', 'identification'],
        'risks' => ['Risks', 'bell'], 'approvals' => ['Approvals', 'identification'], 'files' => ['Documents', 'archive'],
        'reports' => ['Reports', 'chart'], 'ai' => ['AI', 'sparkles'], 'settings' => ['Settings', 'cog'],
    ];
    $moduleRail = collect($modNav)
        ->filter(fn ($m, $k) => in_array($k, ['overview', 'ai', 'settings'], true) || $event->moduleEnabled($k))
        ->map(fn ($m, $k) => [$m[0], route('events.hub', [$event, 'tab' => $k]), $m[1], $tab === $k])
        ->values()->all();
@endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :hide-title-row="true"
               :rail-nav="$moduleRail"
               :subtitle="str($event->type)->replace('_', ' ')->title() . '  |  ' . $event->city . ', ' . $event->country . '  |  ' . $event->starts_at?->format('M j') . ' – ' . ($event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y'))"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Events', 'href' => route('events.index')],
                   ['label' => $event->name, 'href' => $tab === 'overview' ? null : route('events.hub', $event)],
                   ...($tab === 'overview' ? [] : [['label' => \App\Models\Event::HUB_MODULES[$tab][0] ?? str($tab)->title()->toString()]]),
               ]">

{{-- ══ Event identity — sits in the top executive header ══ --}}
<x-slot:identity>
    <a href="{{ route('events.hub', $event) }}" class="flex min-w-0 shrink-0 items-center gap-3" title="{{ $event->name }}">
        <x-event-avatar :event="$event" :ring="false" size="md"
                        class="[&>span]:h-11 [&>span]:w-11 [&>span]:rounded-xl [&>span]:ring-1 [&>span]:ring-white/15" />
        <div class="min-w-0">
            <p class="text-[0.5rem] font-bold uppercase tracking-[0.24em] text-gold-300">◆ Elite Event Hub</p>
            <p class="pf flex items-center gap-1.5 text-[1.05rem] font-bold leading-tight text-white">
                <span class="max-w-[15rem] truncate">{{ $event->name }}</span><span class="shrink-0 text-gold-400">✦</span>
            </p>
            <p class="truncate text-[0.6rem] text-white/55">{{ $event->starts_at?->format('M j') }} – {{ $event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y') }} · {{ $event->city }}, {{ $event->country }}</p>
        </div>
    </a>
</x-slot:identity>


{{--
    When a dock opens, the page steps aside instead of being covered — you open
    Controls to read the fleet count *while* looking at the movements, so hiding
    them defeats the point. Hero and nav shift with the content, otherwise the
    header stays full width while the body narrows and the page looks broken.

    Only from xl up: below that, 380px is too much of the screen to give away,
    so the panel overlays as before.
--}}
<div x-data
     data-dock-shift
     :class="$store.dock.open ? 'xl:pr-[432px]' : ''"
     class="transition-[padding] duration-300 ease-[cubic-bezier(0.22,1,0.36,1)]">

    {{-- ══ Hero — command strip ══ --}}
    @php
        $hs = $health['score'];
        $daysLeft = $event->starts_at ? ($event->starts_at->isPast() ? 'Started' : (int) now()->diffInDays($event->starts_at).'d left') : '—';
        $healthWord = $hs === null ? 'No data' : ($health['group'] === 'risk' ? 'Behind' : ($health['group'] === 'warn' ? 'At Watch' : 'On Track'));
    @endphp
    {{-- No overflow-hidden here: it would clip the Quick Actions menu. The glow
         is clipped by its own layer instead, so the strip keeps its soft corners. --}}
    <div class="relative z-30 rounded-[22px] bg-gradient-to-br from-navy-900 via-navy-950 to-[#050F1E] shadow-[0_24px_60px_-24px_rgba(11,31,58,0.65)] ring-1 ring-white/[0.06]">
        {{-- ambience --}}
        <div class="pointer-events-none absolute inset-0 overflow-hidden rounded-[22px]">
            <div class="absolute inset-x-0 top-0 h-px bg-gradient-to-r from-transparent via-gold-400/70 to-transparent"></div>
            <div class="absolute -right-16 -top-24 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.16),transparent_65%)]"></div>
        </div>

        <div class="relative flex flex-wrap items-center gap-x-7 gap-y-4 px-5 py-4">

            {{-- Health (identity/logo now lives in the top header) --}}
            <div class="flex shrink-0 items-center gap-3.5">
                <div class="flex items-center gap-2.5">
                    <span class="relative inline-flex h-11 w-11 items-center justify-center">
                        <x-health-ring :percent="$hs ?? 0" :group="$health['group']" size="h-11 w-11" :label="false" />
                        <span class="absolute font-bold text-white">
                            <span class="text-2xs">{{ $hs ?? '—' }}</span><span class="text-[0.45rem] text-white/50">%</span>
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
                    <span class="rounded-full bg-gold-400/15 px-2.5 py-0.5 text-3xs font-bold uppercase tracking-wide text-gold-300 ring-1 ring-gold-400/30">{{ str($event->stage)->replace('_', ' ')->title() }}</span>
                    @if ($health['pending_approvals'] > 0)
                        <span class="rounded-full bg-blue-400/15 px-2.5 py-0.5 text-3xs font-bold text-blue-300 ring-1 ring-blue-400/30">{{ $health['pending_approvals'] }} pending</span>
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
                    {{-- <details> stays open on outside clicks by itself — close it. --}}
                    <details class="group relative" x-data
                             @click.outside="$el.open = false" @keydown.escape.window="$el.open = false">
                        <summary class="flex h-9 w-fit cursor-pointer list-none items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-3.5 text-xs font-bold text-navy-950 shadow-[0_6px_18px_-6px_rgba(212,175,55,0.8)] transition hover:brightness-105 [&::-webkit-details-marker]:hidden">
                            ⚡ Quick Actions ▾
                        </summary>
                        <div class="absolute right-0 top-11 z-50 w-56 overflow-hidden rounded-xl border border-white/10 bg-navy-950 shadow-2xl ring-1 ring-black/20">
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
                            @if ($event->moduleEnabled('agenda'))
                                <div class="border-t border-white/10">
                                    <a href="{{ route('events.agenda.program.pdf', $event) }}" class="flex items-center gap-2.5 px-4 py-2.5 text-xs font-semibold text-white/70 transition hover:bg-white/5 hover:text-white"><x-icon name="calendar" class="h-4 w-4 text-gold-400/80" /> Export Programme PDF</a>
                                </div>
                            @endif
                        </div>
                    </details>
                </div>
            </div>
        </div>
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])

        {{-- Every module carries its own document drawer, docked to the right
             edge so it sits beside the tab's own control rail rather than
             stacking underneath it. The Documents tab is the whole library. --}}
        @if (array_key_exists($tab, \App\Models\Event::HUB_MODULES) && $tab !== 'files')
            <livewire:hub.module-documents :event="$event" :module="$tab" :dock="true" :wire:key="'docs-'.$tab" />
        @endif

        {{-- Every module carries its own task panel; work added there lands on the
             Tasks board tagged with the module. Excluded: the board itself, the
             plan (which already feeds it), and read-only/summary tabs. --}}
    </div>
</div>
</x-layouts.app>
