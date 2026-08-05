@php
    $typeTabs = ['all' => 'All Events', 'conference' => 'Conferences', 'workshop' => 'Workshops',
        'exhibition' => 'Exhibitions', 'gala' => 'Galas', 'vip' => 'VIP', 'outdoor' => 'Outdoor'];

    // Deck · List · Flight Path. Three ways of looking at one book — and what
    // each one is FOR, which is why they carry names rather than icons alone.
    $views = [
        'deck' => ['Deck View', 'grid', 'Premium portfolio browsing'],
        'list' => ['List View', 'list', 'Operational management'],
        'path' => ['Flight Path', 'chart', 'Strategic event timeline'],
    ];
@endphp

<div class="space-y-4">

    {{-- ══════════ THE COMMAND MASTHEAD ══════════
         The gold rule + eyebrow reads like a mission log header, not a page
         title — the same idiom the Dashboard opens with, carried here. --}}
    <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
        <div class="min-w-0">
            <p class="flex items-center gap-2 text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-600">
                <span class="h-px w-4 bg-gold-400"></span>Portfolio Command
            </p>
            <h1 class="pf mt-1 text-[30px] font-black leading-none text-navy-950">Projects &amp; Events</h1>
        </div>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search events, clients, venues…"
                       class="input h-10 w-52 !rounded-2xl !border-navy-100 !py-0 !ps-9 text-xs shadow-sm transition focus:!border-gold-400 xl:w-64">
            </div>

            <details class="relative" data-menu>
                <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-navy-100 bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 hover:shadow-md [&::-webkit-details-marker]:hidden">
                    <x-icon name="list" class="h-3.5 w-3.5 text-navy-400" /> Filters
                    @if ($tab !== 'all' || $starred)<span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>@endif
                </summary>
                <div class="absolute end-0 z-30 mt-2 w-56 overflow-hidden rounded-2xl border border-navy-100 bg-white p-1.5 shadow-2xl shadow-navy-950/10">
                    @foreach ($typeTabs as $key => $label)
                        <button type="button" wire:click="setTab('{{ $key }}')"
                                @class([
                                    'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                    'bg-navy-950 text-gold-300' => $tab === $key,
                                    'text-navy-600 hover:bg-page' => $tab !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                    <button type="button" wire:click="toggleStarred"
                            @class([
                                'mt-1 flex w-full items-center gap-2 rounded-xl border-t border-line px-3 py-2 text-start text-[12px] font-semibold transition',
                                'text-gold-700' => $starred, 'text-navy-600 hover:bg-page' => ! $starred,
                            ])>
                        <x-icon name="star" class="h-3.5 w-3.5 {{ $starred ? 'fill-current' : '' }}" /> Starred only
                    </button>
                </div>
            </details>

            <details class="relative" data-menu>
                <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-navy-100 bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 hover:shadow-md [&::-webkit-details-marker]:hidden">
                    <x-icon name="columns" class="h-3.5 w-3.5 text-navy-400" /> Sort
                </summary>
                <div class="absolute end-0 z-30 mt-2 w-48 overflow-hidden rounded-2xl border border-navy-100 bg-white p-1.5 shadow-2xl shadow-navy-950/10">
                    @foreach (['date' => 'By date', 'health' => 'By health', 'budget' => 'By budget spent'] as $key => $label)
                        <button type="button" wire:click="$set('sort', '{{ $key }}')"
                                @class([
                                    'flex w-full items-center rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                    'bg-navy-950 text-gold-300' => $sort === $key,
                                    'text-navy-600 hover:bg-page' => $sort !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>
            </details>

            {{-- Columns belongs to the List and nowhere else. --}}
            @if ($view === 'list')
                <details class="relative" data-menu>
                    <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-navy-100 bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 hover:shadow-md [&::-webkit-details-marker]:hidden">
                        <x-icon name="grid" class="h-3.5 w-3.5 text-navy-400" /> Columns
                    </summary>
                    <div class="absolute end-0 z-30 mt-2 w-52 overflow-hidden rounded-2xl border border-navy-100 bg-white p-1.5 shadow-2xl shadow-navy-950/10">
                        <p class="px-3 py-1.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">Rows per page</p>
                        @foreach ([10, 25, 50] as $n)
                            <button type="button" wire:click="setPerPage({{ $n }})"
                                    @class([
                                        'flex w-full items-center rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                        'bg-navy-950 text-gold-300' => $perPage === $n,
                                        'text-navy-600 hover:bg-page' => $perPage !== $n,
                                    ])>{{ $n }} rows</button>
                        @endforeach
                    </div>
                </details>
            @endif

            <a href="{{ route('settings.index') }}"
               class="flex h-10 items-center gap-1.5 rounded-2xl border border-navy-100 bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 hover:shadow-md">
                <x-icon name="cog" class="h-3.5 w-3.5 text-navy-400" /> Customize
            </a>

            <a href="{{ route('events.create') }}"
               class="flex h-10 items-center gap-1.5 rounded-2xl bg-gradient-to-r from-navy-900 to-navy-950 px-4 text-[12px] font-bold text-white shadow-[0_10px_28px_-12px_rgba(11,31,58,0.85)] ring-1 ring-white/10 transition hover:shadow-[0_14px_32px_-10px_rgba(212,175,55,0.55)]">
                <span class="text-gold-400">＋</span> New Event
            </a>
        </div>
    </div>

    {{-- ══════════ THE PORTFOLIO PULSE ══════════
         The five figures, on a light instrument strip rather than a second
         dark band stacked straight on top of the Mission Radar below it —
         two navy panels back to back read as a wall, not a control room.
         Gold carries the accent instead: a hairline on top, and the icon
         plate going dark only on hover — the same "where you are" idiom
         the deck's own controls already use. --}}
    <div class="relative isolate overflow-hidden rounded-[22px] border border-navy-100 bg-white shadow-[0_16px_40px_-28px_rgba(11,31,58,0.2)]">
        <div class="absolute inset-x-0 top-0 h-[3px] bg-gradient-to-r from-transparent via-gold-400 to-transparent"></div>

        <div @class(['grid grid-cols-2 divide-x divide-y divide-navy-100 @container/pulse sm:grid-cols-3', 'lg:grid-cols-5 lg:divide-y-0' => true])>
            @foreach ($figures as $f)
                @php
                    $tag = ($f['href'] ?? null) ? 'a' : 'div';
                @endphp
                <{{ $tag }} @if ($f['href'] ?? null) href="{{ $f['href'] }}" @endif
                   class="group/pulse flex items-center gap-3 px-4 {{ $view === 'deck' ? 'py-3' : 'py-4' }} transition {{ ($f['href'] ?? null) ? 'hover:bg-page/70' : '' }}">
                    <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-500 ring-1 ring-navy-100 transition group-hover/pulse:bg-navy-950 group-hover/pulse:text-gold-400">
                        <x-icon :name="$f['icon'] ?? 'chart'" class="h-4.5 w-4.5" />
                    </span>
                    <div class="min-w-0">
                        <p class="pf text-[22px] font-black leading-none text-navy-950">{{ $f['value'] }}</p>
                        <p class="mt-1 truncate text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">{{ $f['label'] }}</p>
                        @if ($view !== 'deck' && ($f['note'] ?? null))
                            <p class="mt-0.5 truncate text-[10.5px] text-muted">{{ $f['note'] }}</p>
                        @endif
                    </div>
                    @if ($view !== 'deck')
                        @if ($f['trend'] ?? null)
                            <span @class([
                                'ms-auto shrink-0 self-start text-[11px] font-bold',
                                'text-emerald-600' => $f['trend']['up'],
                                'text-navy-300' => ! $f['trend']['up'],
                            ])>{{ $f['trend']['label'] }}</span>
                        @endif
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>
    </div>

    {{-- ══════════ THE MISSION RADAR ══════════
         Deck, List and Flight Path each answer "what is my book," one row or
         card at a time. Nothing answers "what's closing in on me, all at
         once" — that question is inherently spatial, so it gets a spatial
         answer: a sweep display where bearing is arbitrary (evenly spread,
         so nothing overlaps) but distance from the centre is not — it's how
         soon each mission lands, computed from the same daysOut every card
         already shows. Live missions sit at the core. Nothing here is a new
         fact; it's the one fact (urgency) pulled out of nineteen rows and
         given a shape the eye reads before the brain does. --}}
    @php
        $radarMaxDays = 120;
        $radarPool = $deck
            ->reject(fn ($m) => $m['past'] ?? false)
            ->sortBy(fn ($m) => $m['daysOut'] ?? 999)
            ->take(14)
            ->values();
        $radarN = max($radarPool->count(), 1);
        $radarNodes = $radarPool->map(function ($m, $i) use ($radarN, $radarMaxDays) {
            $angle = -90 + (360 / $radarN) * $i;
            $rad = deg2rad($angle);
            $live = $m['live'] ?? false;
            $days = $m['daysOut'];
            $urgency = $live ? 0 : ($days === null ? 1 : max(0, min(1, $days / $radarMaxDays)));
            $r = $live ? 8 : 16 + $urgency * 32;

            return [
                'm' => $m,
                'left' => round(50 + $r * cos($rad), 2),
                'top' => round(50 + $r * sin($rad), 2),
                'alert' => $live ? false : (($m['healthGroup'] ?? null) === 'risk' || ($m['risk'] ?? null) === 'Critical'),
            ];
        });
        $riskRank = fn ($m) => match ($m['risk'] ?? 'Low') { 'Critical' => 4, 'High' => 3, 'Medium' => 2, default => 1 };
        $nearest = $radarPool->first();
        $riskiest = $radarPool->sortByDesc($riskRank)->first();
        $onTrack = $radarPool->filter(fn ($m) => ($m['healthGroup'] ?? null) === 'track')->count();
    @endphp
    <div x-data="{ radarOpen: true }" class="relative isolate overflow-hidden rounded-[22px] bg-navy-950 shadow-[0_24px_50px_-30px_rgba(6,20,38,0.75)]">
        <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
            <div class="absolute inset-0 bg-[radial-gradient(100%_140%_at_100%_0%,rgba(212,175,55,0.13),transparent_55%)]"></div>
            <div class="absolute inset-0 bg-[radial-gradient(80%_100%_at_0%_100%,rgba(59,90,133,0.3),transparent_60%)]"></div>
        </div>

        <button type="button" wire:key="radar-toggle" @click="radarOpen = ! radarOpen"
                class="flex w-full items-center gap-2.5 px-4 py-3.5 text-start transition hover:bg-white/[0.03] lg:px-6">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-white/10 text-gold-300 ring-1 ring-white/10">
                <x-icon name="globe" class="h-4 w-4" />
            </span>
            <span class="min-w-0">
                <span class="flex items-center gap-2 text-eyebrow font-bold uppercase tracking-[0.22em] text-gold-300/90">
                    Mission Radar
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400 shell-pulse"></span>
                </span>
                <span class="mt-0.5 hidden text-[11px] text-white/40 sm:block">Bearing is arbitrary — distance from the centre is how soon each one lands.</span>
            </span>
            <span class="ms-auto grid h-7 w-7 shrink-0 place-items-center rounded-full text-white/40 transition" :class="radarOpen ? '' : '-rotate-180'">
                <x-icon name="chevron" class="h-3.5 w-3.5" />
            </span>
        </button>

        <div x-show="radarOpen" x-transition.opacity.duration.200ms>
            <div class="grid gap-6 px-4 pb-6 pt-1 lg:grid-cols-[260px_1fr] lg:gap-8 lg:px-6">

                {{-- the sweep --}}
                <div class="relative mx-auto aspect-square w-full max-w-[260px]">
                    <div class="absolute inset-0 overflow-hidden rounded-full ring-1 ring-white/10" style="background: radial-gradient(circle, rgba(255,255,255,0.03), transparent 70%);">
                        <div class="absolute inset-0 radar-spin" style="background: conic-gradient(from 0deg, rgba(212,175,55,0.28), rgba(212,175,55,0.05) 12%, transparent 26%, transparent 100%);"></div>
                    </div>
                    <div class="absolute inset-[6%] rounded-full border border-dashed border-white/[0.08]"></div>
                    <div class="absolute inset-[27%] rounded-full border border-dashed border-white/[0.09]"></div>
                    <div class="absolute inset-[48%] rounded-full border border-white/10"></div>

                    <span class="absolute bottom-[1%] left-1/2 -translate-x-1/2 text-[8.5px] font-bold uppercase tracking-[0.14em] text-white/25">{{ $radarMaxDays }}+ days out</span>

                    {{-- today, the core --}}
                    <div class="absolute left-1/2 top-1/2 -translate-x-1/2 -translate-y-1/2">
                        <span class="block h-2 w-2 rounded-full bg-gold-400 core-glow"></span>
                        <span class="absolute left-1/2 top-3 -translate-x-1/2 whitespace-nowrap text-[8.5px] font-bold uppercase tracking-[0.14em] text-gold-300/80">Today</span>
                    </div>

                    @forelse ($radarNodes as $node)
                        <a href="{{ route('events.hub', $node['m']['event']) }}" wire:navigate
                           title="{{ $node['m']['name'] }} · {{ $node['m']['timeline'] }}"
                           class="group absolute z-10 -translate-x-1/2 -translate-y-1/2 p-1.5"
                           style="left: {{ $node['left'] }}%; top: {{ $node['top'] }}%">
                            <span class="relative flex h-2.5 w-2.5 rounded-full ring-2 ring-navy-950 transition duration-200 group-hover:scale-[1.8]"
                                  style="background: {{ $node['m']['statusHex'] }}; box-shadow: 0 0 10px 1px {{ $node['m']['statusHex'] }}70;">
                                @if ($node['alert'])
                                    <span class="absolute inset-0 rounded-full radar-ping" style="--pulse-color: {{ $node['m']['riskHex'] }}80"></span>
                                @endif
                            </span>
                            <span class="pointer-events-none absolute start-1/2 top-full z-20 mt-1.5 -translate-x-1/2 whitespace-nowrap rounded-lg bg-navy-950 px-2 py-1 text-[10px] font-bold text-white opacity-0 shadow-xl ring-1 ring-white/10 transition group-hover:opacity-100">
                                {{ $node['m']['name'] }}
                            </span>
                        </a>
                    @empty
                        <p class="absolute left-1/2 top-[62%] w-[80%] -translate-x-1/2 text-center text-[10.5px] font-semibold text-white/35">Nothing ahead of today — the book is caught up.</p>
                    @endforelse
                </div>

                {{-- what the sweep is telling you --}}
                <div class="flex flex-col justify-center gap-2.5">
                    @if ($nearest)
                        <a href="{{ route('events.hub', $nearest['event']) }}" wire:navigate
                           class="group flex items-center gap-3 rounded-2xl bg-white/[0.04] px-4 py-3 ring-1 ring-white/[0.06] transition hover:bg-white/[0.07] hover:ring-white/10">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10 text-white/70"><x-icon name="clock" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-eyebrow font-bold uppercase tracking-[0.14em] text-white/40">Closest on the sweep</span>
                                <span class="mt-0.5 block truncate text-[13px] font-bold text-white">{{ $nearest['name'] }}</span>
                            </span>
                            <span class="shrink-0 text-[11px] font-bold text-gold-300">{{ $nearest['live'] ? 'Live now' : $nearest['timeline'] }}</span>
                        </a>
                    @endif

                    @if ($riskiest && $riskRank($riskiest) > 1)
                        <a href="{{ route('events.hub', $riskiest['event']) }}" wire:navigate
                           class="group flex items-center gap-3 rounded-2xl bg-white/[0.04] px-4 py-3 ring-1 ring-white/[0.06] transition hover:bg-white/[0.07] hover:ring-white/10">
                            <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10" style="color: {{ $riskiest['riskHex'] }}"><x-icon name="flag" class="h-4 w-4" /></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-eyebrow font-bold uppercase tracking-[0.14em] text-white/40">Sharpest blip</span>
                                <span class="mt-0.5 block truncate text-[13px] font-bold text-white">{{ $riskiest['name'] }}</span>
                            </span>
                            <span class="shrink-0 text-[11px] font-bold" style="color: {{ $riskiest['riskHex'] }}">{{ $riskiest['risk'] }} risk</span>
                        </a>
                    @endif

                    <div class="flex items-center gap-3 rounded-2xl bg-white/[0.04] px-4 py-3 ring-1 ring-white/[0.06]">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-white/10 text-emerald-300"><x-icon name="check" class="h-4 w-4" /></span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-eyebrow font-bold uppercase tracking-[0.14em] text-white/40">Clear of the centre</span>
                            <span class="mt-0.5 block text-[13px] font-bold text-white">{{ $onTrack }} of {{ $radarPool->count() }} on track</span>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- ══════════ THE VIEW SWITCHER ══════════ --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-1 rounded-2xl border border-navy-100 bg-white p-1 shadow-sm">
            @foreach ($views as $key => [$label, $icon, $note])
                <button type="button" wire:click="setView('{{ $key }}')" title="{{ $note }}"
                        @class([
                            'relative flex items-center gap-2 rounded-xl px-3.5 py-2 text-[12.5px] font-bold transition',
                            'bg-gradient-to-b from-navy-900 to-navy-950 text-white shadow-[0_10px_22px_-12px_rgba(11,31,58,0.9)] ring-1 ring-gold-400/30' => $view === $key,
                            'text-navy-500 hover:bg-page hover:text-navy-900' => $view !== $key,
                        ])>
                    <x-icon :name="$icon" class="h-3.5 w-3.5 {{ $view === $key ? 'text-gold-400' : '' }}" />{{ $label }}
                </button>
            @endforeach
        </div>

        <p class="flex items-center gap-1.5 text-[11.5px] font-semibold text-navy-500">
            <span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>
            {{ $deck->count() }} {{ str('mission')->plural($deck->count()) }} in view
        </p>

        {{-- One legend for all three views, because there is one vocabulary. --}}
        <div class="ms-auto flex flex-wrap items-center gap-x-3.5 gap-y-1">
            @foreach ($statuses as $key => [$label, $tone, $hex])
                <span class="flex items-center gap-1.5 text-[10.5px] font-semibold text-navy-500">
                    <span class="h-2 w-2 rounded-full ring-2 ring-white" style="background: {{ $hex }}; box-shadow: 0 0 0 1px {{ $hex }}33"></span>{{ $label }}
                </span>
            @endforeach
        </div>
    </div>

    @if ($deck->isEmpty())
        <x-empty icon="calendar" title="No mission matches"
                 hint="Clear the filters, or create the first event of this kind.">
            <x-slot:actions>
                <a href="{{ route('events.create') }}" class="btn-gold btn-sm">＋ New Event</a>
            </x-slot:actions>
        </x-empty>

    {{-- ══════════════════════════════════════════════════════════════
    {{-- ══════════════════════════════════════════════════════════════
         DECK VIEW — premium portfolio browsing

         A spatial deck, not a carousel and not a grid. Every mission is in the
         DOM at once and the arrangement happens on the client in 3D: the one
         you are on comes toward you, its neighbours fall back and turn away.

         It has to be client-side. A server round-trip per step cannot produce a
         520ms transition, and re-rendering would tear down the very elements
         the transition is animating — so the deck owns its own index and only
         tells Livewire which mission is active for the sake of deep links.
         ══════════════════════════════════════════════════════════════ --}}
    @elseif ($view === 'deck')
        <div class="relative" data-deck-root>
            {{-- the field the deck floats in — the same navy/gold glow the
                 command masthead opens with, so the deck reads as the
                 instrument at the centre of one control room, not a separate
                 page underneath it. --}}
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden rounded-[28px]" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-b from-navy-50/70 via-white to-page/50"></div>
                <div class="absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(11,31,58,0.08),transparent_70%)]"></div>
                <div class="absolute -right-24 top-1/4 h-80 w-80 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.14),transparent_70%)]"></div>
            </div>

            <div class="flex items-center justify-between px-1 pb-1 pt-2">
                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.2em] text-navy-400">
                    <span class="h-px w-4 bg-navy-200"></span>Past missions
                </p>
                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-600">
                    <span class="relative flex h-1.5 w-1.5"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-gold-400 opacity-60"></span><span class="relative inline-flex h-1.5 w-1.5 rounded-full bg-gold-500"></span></span>
                    Active mission
                </p>
                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.2em] text-navy-400">
                    Future missions<span class="h-px w-4 bg-navy-200"></span>
                </p>
            </div>

            {{-- ── the stage ──
                 perspective on the stage, transforms on the cards. Dragging
                 anywhere on it moves the deck. --}}
            <div class="deck-stage" data-deck-stage
                 style="perspective: 1800px; perspective-origin: 50% 42%"
                 tabindex="0" role="group" aria-label="Event mission deck"
                 aria-roledescription="spatial deck">

                @foreach ($deck as $i => $m)
                    <article wire:key="deck-{{ $m['id'] }}" data-deck-card data-index="{{ $i }}" data-id="{{ $m['id'] }}" data-active="{{ $active && $m['id'] === $active['id'] ? 1 : 0 }}"
                             class="deck-card overflow-hidden rounded-[26px] border border-white/60 bg-white ring-1 ring-navy-950/5">

                        {{-- 1 · the cover, reimagined as a boarding pass ──
                             A full-bleed photo behind navy scrim was the single
                             biggest dark surface on the whole page — one per
                             card, times five in view. This is the same flexible
                             region (still absorbs whatever height the rest of
                             the card does not use) but it is light by default:
                             a soft field, not a photograph. The event's cover or
                             generated crest becomes a framed emblem rather than
                             a backdrop, echoing the badge on a real boarding
                             pass — identity, not wallpaper. --}}
                        <div class="relative isolate flex flex-col overflow-hidden bg-gradient-to-br from-gold-50/80 via-white to-navy-50/60" data-deck-part="cover">
                            <div class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
                                <span class="absolute aspect-square h-[80%] rounded-full border border-dashed border-gold-300/60"></span>
                                <span class="absolute aspect-square h-[54%] rounded-full border border-dashed border-gold-300/50"></span>
                                <span class="absolute aspect-square h-[30%] rounded-full border border-navy-100"></span>
                            </div>

                            <div class="relative z-10 flex items-start justify-between p-4">
                                <x-mission.badge :mission="$m" class="!bg-white shadow-sm" />

                                <button type="button" wire:click="toggleFavorite({{ $m['id'] }})" data-deck-keep
                                        class="grid h-9 w-9 place-items-center rounded-full bg-white text-navy-300 ring-1 ring-navy-100 shadow-sm transition hover:text-gold-600 hover:ring-gold-300"
                                        title="{{ in_array($m['id'], $favoriteIds, true) ? 'Unstar' : 'Star' }} this event">
                                    <x-icon name="star" class="h-4 w-4 {{ in_array($m['id'], $favoriteIds, true) ? 'fill-gold-400 text-gold-400' : '' }}" />
                                </button>
                            </div>

                            <div class="relative z-10 flex flex-1 items-center justify-center py-2">
                                <div class="grid h-[104px] w-[104px] shrink-0 place-items-center overflow-hidden rounded-[24px] ring-[5px] ring-white shadow-[0_20px_40px_-18px_rgba(11,31,58,0.35)]">
                                    @if ($m['cover'])
                                        <img src="{{ $m['cover'] }}" alt="" class="h-full w-full object-cover" style="object-position: 50% 38%">
                                    @else
                                        <x-event-crest :event="$m['event']" class="h-full w-full" />
                                    @endif
                                </div>
                            </div>

                            {{-- the barcode: a boarding-pass signature, deterministic per
                                 event rather than decoration for its own sake. --}}
                            @php
                                $barcode = collect(str_split(substr(sha1($m['id'].'|'.$m['name']), 0, 60), 2))
                                    ->map(fn ($h) => 22 + (hexdec($h) % 78));
                            @endphp
                            <div class="relative z-10 mb-3 flex h-4 items-end justify-center gap-[2px] px-6" aria-hidden="true">
                                @foreach ($barcode as $h)
                                    <span class="w-[2px] rounded-full bg-navy-200" style="height: {{ $h }}%"></span>
                                @endforeach
                            </div>

                            {{-- A hairline of gold along the cover's own bottom edge — the
                                 one recurring signature the deck carries, card to card. --}}
                            <div class="absolute inset-x-0 bottom-0 h-[3px] bg-gradient-to-r from-transparent via-gold-400 to-transparent opacity-80"></div>
                        </div>

                        {{-- 2 · the title block --}}
                        <div class="relative -mt-8 flex flex-wrap items-start gap-4 px-4 pb-3.5 lg:px-5" data-deck-part="title">
                            <div class="grid w-[84px] shrink-0 place-items-center rounded-2xl border border-gold-200/70 bg-white py-3 text-center shadow-[0_14px_28px_-14px_rgba(11,31,58,0.45)]">
                                <span class="text-eyebrow font-bold uppercase tracking-[0.16em] text-gold-600">{{ $m['month'] ?? '—' }}</span>
                                <span class="pf text-[29px] font-black leading-none text-navy-950">{{ $m['day'] ?? '··' }}</span>
                                <span class="text-[10.5px] text-muted">{{ $m['year'] }}</span>
                            </div>

                            <div class="min-w-0 flex-1 pt-9">
                                <h2 class="pf line-clamp-2 text-[22px] font-black leading-tight text-navy-950">
                                    <a href="{{ route('events.hub', $m['event']) }}" data-deck-keep class="transition hover:text-gold-700">{{ $m['name'] }}</a>
                                </h2>
                                <p class="mt-1.5 flex items-center gap-1.5 truncate text-[12px] text-navy-600"><x-icon name="pin" class="h-3.5 w-3.5 shrink-0 text-navy-300" />{{ $m['where'] }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-[12px] text-navy-600">
                                    <span class="flex items-center gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 text-navy-300" />{{ $m['dates'] }}</span>
                                    <span class="text-navy-200">•</span>{{ number_format($m['attendees']) }} {{ strtolower($m['attendeeWord']) }}
                                </p>
                            </div>

                            {{-- Wrapped so the deck can drop it: on a narrow
                                 plate the ring is the third column that will
                                 not fit, and it wraps the whole title block
                                 into three stacked rows. --}}
                            <span data-deck-part="ring">
                                <x-mission.ring :percent="$m['progress']" :hex="$m['statusHex']" :size="84" label="Overall" class="mt-9" />
                            </span>
                        </div>

                        {{-- 3 · the numbers ── 4 · the faces travel with them --}}
                        <div data-deck-part="kpis">
                            <x-mission.kpis :mission="$m" class="border-y border-line" />
                        </div>

                        {{-- 5 · what is next, and what the rules make of it --}}
                        <div class="grid divide-y divide-line sm:grid-cols-2 sm:divide-x sm:divide-y-0" data-deck-part="insight">
                            <div class="px-4 py-3">
                                <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">Next milestone</p>
                                <p class="pf mt-1 truncate text-[13.5px] font-bold text-navy-950">{{ $m['milestone']['title'] }}</p>
                                <p class="mt-0.5 text-[11px] {{ $m['milestone']['overdue'] ? 'font-semibold text-red-600' : 'text-muted' }}">{{ $m['milestone']['due'] }}</p>
                            </div>
                            <div class="bg-gold-50/40 px-4 py-3">
                                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-gold-700">
                                    AI insight <x-icon name="sparkles" class="ms-auto h-3.5 w-3.5 text-gold-500" />
                                </p>
                                <p class="mt-1 line-clamp-2 text-[12px] leading-relaxed text-navy-700">{{ $m['insight'] }}</p>
                            </div>
                        </div>

                        {{-- 6 · the dock, which does not animate: furniture that
                             moves is furniture you cannot aim at --}}
                        <x-mission.dock :mission="$m" class="mt-auto" />
                    </article>
                @endforeach
            </div>

            {{-- ── the controls ──
                 The hint sits on this row rather than under it: a second line
                 of 11px grey cost 25px of the card's height, and the card is
                 what people came for. --}}
            <div class="mt-3 flex items-center justify-center gap-3">
                <button type="button" data-deck-prev
                        class="grid h-10 w-10 place-items-center rounded-full border border-navy-100 bg-white text-[18px] leading-none text-navy-600 shadow-sm transition hover:border-gold-300 hover:text-gold-700 hover:shadow-md disabled:opacity-25"
                        aria-label="Previous mission">‹</button>

                <div class="flex items-center gap-1.5" data-deck-dots>
                    @foreach ($deck as $i => $m)
                        <button type="button" data-deck-dot data-index="{{ $i }}"
                                class="h-1.5 w-1.5 rounded-full bg-navy-200 transition-all hover:bg-gold-400"
                                title="{{ $m['name'] }}" aria-label="{{ $m['name'] }}"></button>
                    @endforeach
                </div>

                <button type="button" data-deck-next
                        class="grid h-10 w-10 place-items-center rounded-full border border-navy-100 bg-white text-[18px] leading-none text-navy-600 shadow-sm transition hover:border-gold-300 hover:text-gold-700 hover:shadow-md disabled:opacity-25"
                        aria-label="Next mission">›</button>

                <p class="ms-2 hidden text-[11px] text-muted lg:block">
                    Drag, swipe, use ← →, or pick a card at the edge
                </p>
            </div>
        </div>


    {{-- ══════════════════════════════════════════════════════════════
         LIST VIEW — operational management

         Rows that read as horizontal mini-cards, and a panel beneath that
         updates in place. Picking a row should never cost you sight of the
         rest of the book, which is what a modal would do.
         ══════════════════════════════════════════════════════════════ --}}
    @elseif ($view === 'list')
        <div class="space-y-4">
            @if ($selectedIds)
                <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-red-200 bg-red-50/60 px-4 py-2.5">
                    <p class="text-[12.5px] font-bold text-red-900">
                        {{ count($selectedIds) }} {{ str('event')->plural(count($selectedIds)) }} selected
                    </p>
                    <button type="button" wire:click="selectAllMatching"
                            class="text-[11.5px] font-semibold text-red-700 underline-offset-2 hover:underline">Select everything matching these filters</button>
                    <button type="button" wire:click="clearSelection"
                            class="text-[11.5px] font-semibold text-navy-500 hover:text-navy-800">Clear</button>

                    <x-confirm
                            title="Delete {{ count($selectedIds) }} {{ str('event')->plural(count($selectedIds)) }} permanently?"
                            :body="'Their tasks, budgets, documents, contracts and bookings go with them. Invoices and proposals are kept, unattached.'.PHP_EOL.PHP_EOL.'This cannot be undone.'"
                            confirm="Delete permanently"
                            run="$wire.deleteSelected()"
                            class="ms-auto rounded-xl bg-red-600 px-4 py-2 text-xs font-bold text-white transition hover:bg-red-700">
                        Delete permanently
                    </x-confirm>
                </div>
            @endif

            <div class="overflow-hidden rounded-[22px] border border-navy-100 bg-white shadow-[0_16px_40px_-24px_rgba(11,31,58,0.25)]">
                {{-- ══ THE MANIFEST STRIP ══
                     A ledger identity for the one view built for scanning, not
                     browsing: a gold-tinted masthead and a doubled rule under
                     the column heads — the two marks that read "this is a
                     manifest" before a single row has been read. --}}
                <div class="flex items-center gap-2 bg-gradient-to-r from-gold-50/70 via-white to-white px-4 py-2.5">
                    <p class="flex items-center gap-2 text-eyebrow font-bold uppercase tracking-[0.22em] text-gold-700">
                        <span class="h-px w-4 bg-gold-400"></span>Mission Manifest
                    </p>
                    <p class="ms-auto text-[10.5px] text-muted">Sorted by {{ ['date' => 'date', 'health' => 'health', 'budget' => 'budget spent'][$sort] ?? 'date' }}</p>
                </div>

                <div class="scrollbar-none overflow-x-auto">
                    <div class="min-w-[1120px]">
                        @php
                            $cols = 'grid-cols-[28px_minmax(220px,2.2fr)_100px_130px_150px_78px_130px_92px_112px_128px_36px]';
                            $pageIds = $rows->pluck('id')->all();
                            $pageAllOn = $pageIds !== [] && ! array_diff($pageIds, $selectedIds);
                        @endphp

                        <div class="grid {{ $cols }} items-center gap-3 border-b border-navy-900/[0.08] bg-page/80 px-4 py-3 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">
                            <span>
                                @can('manage-events')
                                    <input type="checkbox" @checked($pageAllOn)
                                           wire:click="toggleSelectPage({{ \Illuminate\Support\Js::from($pageIds) }})"
                                           class="h-3.5 w-3.5 cursor-pointer rounded border-navy-200">
                                @endcan
                            </span>
                            <span class="text-gold-700">Event</span><span>Status</span><span>Dates</span><span>Location</span>
                            <span class="text-center">Progress</span><span>Budget</span>
                            <span class="text-center">Attendees</span><span>Health</span><span>Next milestone</span><span></span>
                        </div>
                        <div class="h-px bg-gold-200/70"></div>

                        <div class="divide-y divide-line">
                            @foreach ($rows as $m)
                                @php $on = $active && $m['id'] === $active['id']; @endphp
                                @php $ticked = in_array($m['id'], $selectedIds, true); @endphp
                                <div wire:key="row-{{ $m['id'] }}" wire:click="activate({{ $m['id'] }})"
                                     @class([
                                         'relative grid cursor-pointer '.$cols.' items-center gap-3 px-4 py-3 transition',
                                         'bg-gold-50/50 shadow-[inset_3px_0_0_0_theme(colors.gold.500)]' => $on && ! $ticked,
                                         'bg-red-50/50' => $ticked,
                                         'bg-page/40' => ! $on && ! $ticked && $loop->even,
                                         'hover:bg-page/70' => ! $on && ! $ticked,
                                     ])>
                                    {{-- a status-colour thread down the row's own left edge, so
                                         the eye can scan "who's in trouble" down the column of
                                         bars before it ever reaches the Health text. --}}
                                    @unless ($on && ! $ticked)
                                        <span class="absolute inset-y-2 left-0 w-[3px] rounded-full opacity-70" style="background: {{ $m['statusHex'] }}"></span>
                                    @endunless

                                    <span wire:click.stop>
                                        @can('manage-events')
                                            <input type="checkbox" @checked($ticked) wire:click="toggleSelect({{ $m['id'] }})"
                                                   class="h-3.5 w-3.5 cursor-pointer rounded border-navy-300">
                                        @endcan
                                    </span>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="grid h-11 w-14 shrink-0 place-items-center rounded-xl bg-white p-[3px] shadow-sm ring-1 ring-navy-100">
                                            <span class="h-full w-full overflow-hidden rounded-[8px]">
                                                @if ($m['cover'])
                                                    <img src="{{ $m['cover'] }}" alt="" class="h-full w-full object-cover">
                                                @else
                                                    <x-event-crest :event="$m['event']" class="h-full w-full" />
                                                @endif
                                            </span>
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-[13px] font-bold text-navy-950">{{ $m['name'] }}</span>
                                            <span class="flex items-center gap-1.5">
                                                <span class="shrink-0 font-mono text-[9px] font-bold tracking-wider text-gold-600/80">EV-{{ str_pad($m['id'], 4, '0', STR_PAD_LEFT) }}</span>
                                                <span class="truncate text-[10.5px] text-muted">{{ $m['description'] ?: ($m['client'] ?: 'No description') }}</span>
                                            </span>
                                        </span>
                                    </div>

                                    <x-mission.badge :mission="$m" size="xs" class="justify-self-start" />

                                    <span class="min-w-0">
                                        <span class="block truncate text-[11.5px] font-semibold text-navy-800">{{ $m['shortDates'] }}</span>
                                        <span class="block text-[10px] text-muted">{{ $m['duration'] }}</span>
                                    </span>

                                    <span class="block truncate text-[11.5px] text-navy-700">{{ $m['where'] }}</span>

                                    <x-mission.ring :percent="$m['progress']" :hex="$m['statusHex']" :size="46" class="justify-self-center" />

                                    <span class="min-w-0">
                                        <span class="block truncate text-[11.5px] font-bold text-navy-950">{{ $m['budgetLabel'] }}</span>
                                        <span class="block truncate text-[10px] text-muted">{{ $m['budgetOf'] }}</span>
                                        <span class="mt-1 block h-[3px] overflow-hidden rounded-full bg-navy-50">
                                            <span class="block h-full rounded-full bg-gold-500" style="width: {{ $m['budgetPct'] ?? 0 }}%"></span>
                                        </span>
                                    </span>

                                    <span class="text-center">
                                        <span class="block text-[13px] font-bold text-navy-950">{{ number_format($m['attendees']) }}</span>
                                        <span class="block text-[9.5px] text-muted">{{ $m['attendeeWord'] }}</span>
                                    </span>

                                    <span class="flex min-w-0 items-center gap-1.5 text-[11.5px] font-semibold">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $m['riskHex'] }}"></span>
                                        <span class="truncate text-navy-700">{{ $m['health'] }}</span>
                                    </span>

                                    <span class="min-w-0">
                                        <span class="block truncate text-[11px] font-semibold text-navy-800">{{ $m['milestone']['title'] }}</span>
                                        <span class="block truncate text-[10px] {{ $m['milestone']['overdue'] ? 'text-red-600' : 'text-muted' }}">{{ $m['milestone']['due'] }}</span>
                                    </span>

                                    <details class="relative justify-self-end" data-menu>
                                        <summary class="grid h-7 w-7 cursor-pointer list-none place-items-center rounded-lg text-[15px] leading-none text-navy-300 transition hover:bg-navy-50 hover:text-navy-700 [&::-webkit-details-marker]:hidden">⋮</summary>
                                        <div class="absolute end-0 z-30 mt-1 w-44 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-xl">
                                            <a href="{{ route('events.hub', $m['event']) }}" class="block px-3 py-2 text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">Open event</a>
                                            <button type="button" wire:click="toggleFavorite({{ $m['id'] }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">{{ in_array($m['id'], $favoriteIds, true) ? 'Unstar' : 'Star' }}</button>
                                            <button type="button" wire:click="duplicate({{ $m['id'] }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">Duplicate</button>
                                            <x-confirm title="Archive “{{ $m['name'] }}”?" body="It leaves every board and list." confirm="Archive" tone="warn" run="$wire.archive({{ $m['id'] }})"
                                                       class="block w-full border-t border-line px-3 py-2 text-start text-[11.5px] font-semibold text-red-600 transition hover:bg-red-50">Archive</x-confirm>
                                            @can('manage-events')
                                                {{-- Permanent, so it says so and it says what it takes.
                                                     The event's own Settings has the full inventory and
                                                     asks for the name; this is the quick one. --}}
                                                <x-confirm
                                                        title="Delete “{{ $m['name'] }}” permanently?"
                                                        :body="'Its tasks, budget, documents, contracts and bookings go with it. Invoices and proposals are kept, unattached.'.PHP_EOL.PHP_EOL.'This cannot be undone.'"
                                                        confirm="Delete permanently"
                                                        run="$wire.deleteEvent({{ $m['id'] }})"
                                                        class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-red-700 transition hover:bg-red-50">Delete permanently</x-confirm>
                                            @endcan
                                        </div>
                                    </details>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>

                @if ($rows->hasPages())
                    <div class="flex flex-wrap items-center gap-3 border-t border-line px-4 py-2.5">
                        <p class="text-[11px] text-muted">{{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</p>
                        <div class="ms-auto">{{ $rows->links() }}</div>
                    </div>
                @endif
            </div>

            @if ($active)
                <x-mission.panel :mission="$active" />
            @endif
        </div>

    {{-- ══════════════════════════════════════════════════════════════
         FLIGHT PATH — strategic event timeline

         Lanes by category, months across, each mission a card floating at
         its own date. Deliberately not a Gantt: nothing here is a bar, and
         a bar is what turns a plan into a spreadsheet.
         ══════════════════════════════════════════════════════════════ --}}
    @else
        <div class="space-y-4">
            <div class="overflow-hidden rounded-[22px] border border-navy-100 bg-white shadow-[0_16px_40px_-24px_rgba(11,31,58,0.25)]">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-navy-100 bg-page/80 px-4 py-3.5">
                    <div>
                        <p class="text-eyebrow font-bold uppercase tracking-[0.2em] text-gold-700">Flight Path</p>
                        <h2 class="pf text-[16px] font-bold text-navy-950">{{ $months['label'] ?? 'Strategic timeline' }}</h2>
                    </div>
                    <p class="hidden text-[11.5px] text-muted sm:block">Where every mission sits in the year.</p>

                    <div class="ms-auto flex items-center gap-2">
                        <button type="button" data-fp-today
                                class="flex h-8 items-center gap-1.5 rounded-xl border border-navy-100 bg-white px-3 text-[11.5px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 hover:bg-gold-50">
                            <x-icon name="calendar" class="h-3.5 w-3.5 text-gold-600" /> Today
                        </button>
                        <div class="flex h-8 items-center gap-0.5 rounded-xl border border-navy-100 bg-white px-1 shadow-sm">
                            <button type="button" data-fp-zoom="-1" class="grid h-6 w-6 place-items-center rounded-lg text-navy-500 transition hover:bg-page" aria-label="Zoom out">−</button>
                            <span data-fp-level class="w-10 text-center text-[10.5px] font-bold tabular-nums text-navy-700">100%</span>
                            <button type="button" data-fp-zoom="1" class="grid h-6 w-6 place-items-center rounded-lg text-navy-500 transition hover:bg-page" aria-label="Zoom in">+</button>
                        </div>
                    </div>
                </div>

                @if (! $months)
                    <p class="px-4 py-10 text-center text-[12px] text-muted">No mission carries a date yet, so there is no path to draw.</p>
                @else
                    <div class="scrollbar-none overflow-x-auto p-4">
                        <div data-fp-canvas data-fp-base="{{ max(880, count($months['list']) * 190) }}"
                             style="min-width: {{ max(880, count($months['list']) * 190) }}px">

                            <div class="relative ms-[172px] h-9 border-b border-line">
                                @foreach ($months['list'] as $month)
                                    <span class="absolute top-1" style="left: {{ $month['left'] }}%">
                                        <span @class([
                                            'rounded-lg px-2 py-1 text-[10.5px] font-bold uppercase tracking-[0.14em]',
                                            'bg-gold-500 text-navy-950' => $month['current'],
                                            'text-navy-400' => ! $month['current'],
                                        ])>{{ $month['label'] }}</span>
                                    </span>
                                @endforeach
                            </div>

                            <div class="relative">
                                {{-- month rules and the today line, down the whole canvas --}}
                                <div class="pointer-events-none absolute inset-y-0 left-[172px] right-0 z-0" aria-hidden="true">
                                    @foreach ($months['list'] as $month)
                                        <span class="absolute inset-y-0 w-px bg-line/60" style="left: {{ $month['left'] }}%"></span>
                                    @endforeach
                                    @if ($months['todayLeft'] !== null)
                                        {{-- a flag rather than a dot: this line is the one date on
                                             the canvas that means something on its own, so it gets
                                             a marker that reads as "here" from across the page. --}}
                                        <span data-fp-line class="absolute inset-y-0 w-px bg-gold-500/70" style="left: {{ $months['todayLeft'] }}%">
                                            <span class="absolute -top-[3px] left-0 h-0 w-0 border-y-[6px] border-l-[9px] border-y-transparent border-l-gold-500 drop-shadow-[0_2px_3px_rgba(212,175,55,0.4)]"></span>
                                        </span>
                                    @endif
                                </div>

                                @foreach ($lanes as $lane)
                                    <div @class([
                                        'relative flex items-stretch border-b border-line/70 last:border-b-0',
                                        'bg-page/35' => $loop->even,
                                    ])>
                                        <div class="z-10 flex w-[172px] shrink-0 items-center gap-2.5 py-4 pe-4 {{ $loop->even ? 'bg-page/35' : 'bg-white' }}">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-page text-navy-400">
                                                <x-icon :name="$lane['icon']" class="h-4 w-4" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-[12px] font-bold text-navy-900">{{ $lane['label'] }}</span>
                                                <span class="block text-[10px] text-muted">{{ $lane['missions']->count() }} {{ str('mission')->plural($lane['missions']->count()) }}</span>
                                            </span>
                                        </div>

                                        <div class="relative min-h-[108px] flex-1 py-3">
                                            {{-- the lane's own baseline: where a mission's date sits
                                                 exactly, plotted as a waypoint rather than left only
                                                 to the card's own (edge-clamped) position above it. --}}
                                            <div class="pointer-events-none absolute inset-x-0 bottom-2 h-px bg-line/50" aria-hidden="true"></div>

                                            @foreach ($lane['missions'] as $m)
                                                @php
                                                    $start = $m['event']->starts_at;
                                                    $left = $start ? round($months['from']->diffInDays($start) / $months['span'] * 100, 3) : 0;
                                                    $on = $active && $m['id'] === $active['id'];
                                                @endphp
                                                <span class="pointer-events-none absolute bottom-2 z-0 h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 rounded-full ring-2 ring-white"
                                                      style="left: {{ $left }}%; background: {{ $m['statusHex'] }}" aria-hidden="true"></span>
                                                <button type="button" wire:click="activate({{ $m['id'] }})" wire:key="fp-{{ $m['id'] }}"
                                                        @class([
                                                            'absolute top-3 z-10 flex w-[236px] items-center gap-2.5 rounded-2xl border p-2 text-left transition',
                                                            'border-gold-400 bg-white shadow-[0_16px_36px_-20px_rgba(11,31,58,0.55)] ring-2 ring-gold-400/25' => $on,
                                                            'border-line bg-white/90 shadow-sm backdrop-blur hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-lg' => ! $on,
                                                        ])
                                                        style="left: min({{ $left }}%, calc(100% - 236px))">
                                                    <span class="grid h-11 w-14 shrink-0 place-items-center rounded-xl bg-white p-[3px] shadow-sm ring-1 ring-navy-100">
                                                        <span class="h-full w-full overflow-hidden rounded-[8px]">
                                                            @if ($m['cover'])
                                                                <img src="{{ $m['cover'] }}" alt="" class="h-full w-full object-cover">
                                                            @else
                                                                <x-event-crest :event="$m['event']" class="h-full w-full" />
                                                            @endif
                                                        </span>
                                                    </span>
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-[12px] font-bold text-navy-950">{{ $m['name'] }}</span>
                                                        <span class="block truncate text-[10px] text-muted">{{ $m['shortDates'] }} · {{ $m['where'] }}</span>
                                                        <span class="mt-1 flex items-center gap-1.5">
                                                            <x-mission.badge :mission="$m" size="xs" />
                                                            <span class="text-[10px] font-bold tabular-nums text-navy-500">{{ $m['progress'] }}%</span>
                                                        </span>
                                                        <span class="mt-1 block h-[3px] overflow-hidden rounded-full bg-navy-50">
                                                            <span class="block h-full rounded-full" style="width: {{ $m['progress'] }}%; background: {{ $m['statusHex'] }}"></span>
                                                        </span>
                                                    </span>
                                                </button>
                                            @endforeach
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif
            </div>

            @if ($active)
                <x-mission.panel :mission="$active" />
            @endif
        </div>
    @endif

</div>
