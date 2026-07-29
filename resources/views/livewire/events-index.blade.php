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

    {{-- ══════════ THE COMMAND BAR ══════════ --}}
    <div class="flex flex-wrap items-end gap-x-6 gap-y-3">
        <div class="min-w-0">
            <h1 class="pf text-[28px] font-black leading-none text-navy-950">Projects &amp; Events</h1>
            <p class="mt-1.5 text-[12.5px] text-muted">{{ $views[$view][2] }} — see, compare and open every mission in the book.</p>
        </div>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search events, clients, venues…"
                       class="input h-10 w-52 !rounded-2xl !py-0 !ps-9 text-xs xl:w-64">
            </div>

            <details class="relative" data-menu>
                <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-indigo-200 [&::-webkit-details-marker]:hidden">
                    <x-icon name="list" class="h-3.5 w-3.5 text-navy-400" /> Filters
                    @if ($tab !== 'all' || $starred)<span class="h-1.5 w-1.5 rounded-full bg-indigo-500"></span>@endif
                </summary>
                <div class="absolute end-0 z-30 mt-2 w-56 overflow-hidden rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                    @foreach ($typeTabs as $key => $label)
                        <button type="button" wire:click="setTab('{{ $key }}')"
                                @class([
                                    'flex w-full items-center gap-2 rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                    'bg-navy-950 text-white' => $tab === $key,
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
                <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-indigo-200 [&::-webkit-details-marker]:hidden">
                    <x-icon name="columns" class="h-3.5 w-3.5 text-navy-400" /> Sort
                </summary>
                <div class="absolute end-0 z-30 mt-2 w-48 overflow-hidden rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                    @foreach (['date' => 'By date', 'health' => 'By health', 'budget' => 'By budget spent'] as $key => $label)
                        <button type="button" wire:click="$set('sort', '{{ $key }}')"
                                @class([
                                    'flex w-full items-center rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                    'bg-navy-950 text-white' => $sort === $key,
                                    'text-navy-600 hover:bg-page' => $sort !== $key,
                                ])>{{ $label }}</button>
                    @endforeach
                </div>
            </details>

            {{-- Columns belongs to the List and nowhere else. --}}
            @if ($view === 'list')
                <details class="relative" data-menu>
                    <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-indigo-200 [&::-webkit-details-marker]:hidden">
                        <x-icon name="grid" class="h-3.5 w-3.5 text-navy-400" /> Columns
                    </summary>
                    <div class="absolute end-0 z-30 mt-2 w-52 overflow-hidden rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                        <p class="px-3 py-1.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">Rows per page</p>
                        @foreach ([10, 25, 50] as $n)
                            <button type="button" wire:click="setPerPage({{ $n }})"
                                    @class([
                                        'flex w-full items-center rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                        'bg-navy-950 text-white' => $perPage === $n,
                                        'text-navy-600 hover:bg-page' => $perPage !== $n,
                                    ])>{{ $n }} rows</button>
                        @endforeach
                    </div>
                </details>
            @endif

            <a href="{{ route('settings.index') }}"
               class="flex h-10 items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-indigo-200">
                <x-icon name="cog" class="h-3.5 w-3.5 text-navy-400" /> Customize
            </a>

            <a href="{{ route('events.create') }}"
               class="flex h-10 items-center gap-1.5 rounded-2xl bg-navy-950 px-4 text-[12px] font-bold text-white shadow-[0_10px_24px_-14px_rgba(11,31,58,0.9)] transition hover:bg-navy-800">
                ＋ New Event
            </a>
        </div>
    </div>

    <x-figure-strip :figures="$figures" />

    {{-- ══════════ THE VIEW SWITCHER ══════════ --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-1 rounded-2xl border border-line bg-white/80 p-1 shadow-sm backdrop-blur">
            @foreach ($views as $key => [$label, $icon, $note])
                <button type="button" wire:click="setView('{{ $key }}')" title="{{ $note }}"
                        @class([
                            'flex items-center gap-2 rounded-xl px-3.5 py-2 text-[12.5px] font-bold transition',
                            'bg-navy-950 text-white shadow-[0_8px_20px_-12px_rgba(11,31,58,0.9)]' => $view === $key,
                            'text-navy-500 hover:bg-page hover:text-navy-900' => $view !== $key,
                        ])>
                    <x-icon :name="$icon" class="h-3.5 w-3.5 {{ $view === $key ? 'text-gold-400' : '' }}" />{{ $label }}
                </button>
            @endforeach
        </div>

        <p class="text-[11.5px] text-muted">{{ $deck->count() }} {{ str('mission')->plural($deck->count()) }} in view</p>

        {{-- One legend for all three views, because there is one vocabulary. --}}
        <div class="ms-auto flex flex-wrap items-center gap-x-3.5 gap-y-1">
            @foreach ($statuses as $key => [$label, $tone, $hex])
                <span class="flex items-center gap-1.5 text-[10.5px] text-muted">
                    <span class="h-2 w-2 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
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
            {{-- the field the deck floats in --}}
            <div class="pointer-events-none absolute inset-0 -z-10 overflow-hidden rounded-[28px]" aria-hidden="true">
                <div class="absolute inset-0 bg-gradient-to-b from-indigo-50/70 via-white to-page/50"></div>
                <div class="absolute -left-24 top-1/3 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(99,102,241,0.10),transparent_70%)]"></div>
                <div class="absolute -right-24 top-1/4 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.12),transparent_70%)]"></div>
            </div>

            <div class="flex items-center justify-between px-1 pb-1 pt-4">
                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.2em] text-navy-400">
                    <span class="h-px w-4 bg-navy-200"></span>Past missions
                </p>
                <p class="text-eyebrow font-bold uppercase tracking-[0.24em] text-indigo-500">Active mission</p>
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
                             class="deck-card overflow-hidden rounded-[26px] border border-white/60 bg-white ring-1 ring-indigo-500/5">

                        {{-- 1 · the cover ── first thing to change on a step --}}
                        <div class="relative isolate overflow-hidden" data-deck-part="cover">
                            @if ($m['cover'])
                                <img src="{{ $m['cover'] }}" alt="" class="absolute inset-0 -z-10 h-full w-full object-cover" style="object-position: 50% 38%">
                            @else
                                <x-event-crest :event="$m['event']" class="absolute inset-0 -z-10 h-full w-full" />
                            @endif
                            <div class="absolute inset-0 -z-10 bg-gradient-to-t from-navy-950/55 via-transparent to-navy-950/25"></div>

                            <div class="flex items-start justify-between p-4">
                                <x-mission.badge :mission="$m" class="!bg-white/95 shadow-sm !ring-white/50" />

                                <button type="button" wire:click="toggleFavorite({{ $m['id'] }})" data-deck-keep
                                        class="grid h-9 w-9 place-items-center rounded-full bg-navy-950/70 text-white backdrop-blur transition hover:bg-navy-950"
                                        title="{{ in_array($m['id'], $favoriteIds, true) ? 'Unstar' : 'Star' }} this event">
                                    <x-icon name="star" class="h-4 w-4 {{ in_array($m['id'], $favoriteIds, true) ? 'fill-gold-400 text-gold-400' : '' }}" />
                                </button>
                            </div>
                        </div>

                        {{-- 2 · the title block --}}
                        <div class="relative -mt-8 flex flex-wrap items-start gap-4 px-4 pb-3.5 lg:px-5" data-deck-part="title">
                            <div class="grid w-[84px] shrink-0 place-items-center rounded-2xl border border-line bg-white py-3 text-center shadow-lg">
                                <span class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">{{ $m['month'] ?? '—' }}</span>
                                <span class="pf text-[29px] font-black leading-none text-navy-950">{{ $m['day'] ?? '··' }}</span>
                                <span class="text-[10.5px] text-muted">{{ $m['year'] }}</span>
                            </div>

                            <div class="min-w-0 flex-1 pt-9">
                                <h2 class="pf line-clamp-2 text-[22px] font-black leading-tight text-navy-950">
                                    <a href="{{ route('events.hub', $m['event']) }}" data-deck-keep class="transition hover:text-indigo-600">{{ $m['name'] }}</a>
                                </h2>
                                <p class="mt-1.5 flex items-center gap-1.5 truncate text-[12px] text-navy-600"><x-icon name="pin" class="h-3.5 w-3.5 shrink-0 text-navy-300" />{{ $m['where'] }}</p>
                                <p class="mt-1 flex flex-wrap items-center gap-x-2 text-[12px] text-navy-600">
                                    <span class="flex items-center gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 text-navy-300" />{{ $m['dates'] }}</span>
                                    <span class="text-navy-200">•</span>{{ number_format($m['attendees']) }} {{ strtolower($m['attendeeWord']) }}
                                </p>
                            </div>

                            <x-mission.ring :percent="$m['progress']" :hex="$m['statusHex']" :size="84" label="Overall" class="mt-9" />
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
                            <div class="px-4 py-3">
                                <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">
                                    AI insight <x-icon name="sparkles" class="ms-auto h-3.5 w-3.5 text-indigo-500" />
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

            {{-- ── the controls ── --}}
            <div class="mt-5 flex items-center justify-center gap-3">
                <button type="button" data-deck-prev
                        class="grid h-11 w-11 place-items-center rounded-full border border-line bg-white text-[19px] leading-none text-navy-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 disabled:opacity-25"
                        aria-label="Previous mission">‹</button>

                <div class="flex items-center gap-1.5" data-deck-dots>
                    @foreach ($deck as $i => $m)
                        <button type="button" data-deck-dot data-index="{{ $i }}"
                                class="h-1.5 w-1.5 rounded-full bg-navy-200 transition-all hover:bg-navy-400"
                                title="{{ $m['name'] }}" aria-label="{{ $m['name'] }}"></button>
                    @endforeach
                </div>

                <button type="button" data-deck-next
                        class="grid h-11 w-11 place-items-center rounded-full border border-line bg-white text-[19px] leading-none text-navy-600 shadow-sm transition hover:border-indigo-200 hover:text-indigo-600 disabled:opacity-25"
                        aria-label="Next mission">›</button>
            </div>

            <p class="mt-2 text-center text-[11px] text-muted">
                Drag the deck, swipe, use ← →, or pick a card at the edge
            </p>
        </div>


    {{-- ══════════════════════════════════════════════════════════════
         LIST VIEW — operational management

         Rows that read as horizontal mini-cards, and a panel beneath that
         updates in place. Picking a row should never cost you sight of the
         rest of the book, which is what a modal would do.
         ══════════════════════════════════════════════════════════════ --}}
    @elseif ($view === 'list')
        <div class="space-y-4">
            <div class="card overflow-hidden">
                <div class="scrollbar-none overflow-x-auto">
                    <div class="min-w-[1080px]">
                        <div class="grid grid-cols-[minmax(230px,2.2fr)_100px_130px_150px_78px_130px_92px_112px_128px_36px] items-center gap-3 border-b border-line bg-page/40 px-4 py-2.5 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400">
                            <span>Event</span><span>Status</span><span>Dates</span><span>Location</span>
                            <span class="text-center">Progress</span><span>Budget</span>
                            <span class="text-center">Attendees</span><span>Health</span><span>Next milestone</span><span></span>
                        </div>

                        <div class="divide-y divide-line">
                            @foreach ($rows as $m)
                                @php $on = $active && $m['id'] === $active['id']; @endphp
                                <div wire:key="row-{{ $m['id'] }}" wire:click="activate({{ $m['id'] }})"
                                     @class([
                                         'grid cursor-pointer grid-cols-[minmax(230px,2.2fr)_100px_130px_150px_78px_130px_92px_112px_128px_36px] items-center gap-3 px-4 py-3 transition',
                                         'bg-indigo-50/60 shadow-[inset_3px_0_0_0_theme(colors.indigo.500)]' => $on,
                                         'hover:bg-page/60' => ! $on,
                                     ])>

                                    <div class="flex min-w-0 items-center gap-3">
                                        <span class="h-11 w-14 shrink-0 overflow-hidden rounded-xl bg-navy-50 ring-1 ring-line">
                                            @if ($m['cover'])
                                                <img src="{{ $m['cover'] }}" alt="" class="h-full w-full object-cover">
                                            @else
                                                <x-event-crest :event="$m['event']" class="h-full w-full" />
                                            @endif
                                        </span>
                                        <span class="min-w-0">
                                            <span class="block truncate text-[13px] font-bold text-navy-950">{{ $m['name'] }}</span>
                                            <span class="block truncate text-[10.5px] text-muted">{{ $m['description'] ?: ($m['client'] ?: 'No description') }}</span>
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
                                            <span class="block h-full rounded-full bg-blue-500" style="width: {{ $m['budgetPct'] ?? 0 }}%"></span>
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
                                            <button type="button" wire:click="archive({{ $m['id'] }})" wire:confirm="Archive “{{ $m['name'] }}”? It leaves every board and list."
                                                    class="block w-full border-t border-line px-3 py-2 text-start text-[11.5px] font-semibold text-red-600 transition hover:bg-red-50">Archive</button>
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
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line px-4 py-3">
                    <h2 class="pf text-[15px] font-bold text-navy-950">{{ $months['label'] ?? 'Flight Path' }}</h2>
                    <p class="text-[11.5px] text-muted">Where every mission sits in the year.</p>

                    <div class="ms-auto flex items-center gap-2">
                        <button type="button" data-fp-today
                                class="flex h-8 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-[11.5px] font-semibold text-navy-700 transition hover:border-indigo-200">
                            <x-icon name="calendar" class="h-3.5 w-3.5 text-navy-400" /> Today
                        </button>
                        <div class="flex h-8 items-center gap-0.5 rounded-xl border border-line bg-white px-1">
                            <button type="button" data-fp-zoom="-1" class="grid h-6 w-6 place-items-center rounded-lg text-navy-500 transition hover:bg-page" aria-label="Zoom out">−</button>
                            <span data-fp-level class="w-10 text-center text-[10.5px] font-bold tabular-nums text-navy-600">100%</span>
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
                                            'bg-navy-950 text-white' => $month['current'],
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
                                        <span data-fp-line class="absolute inset-y-0 w-px bg-navy-950/70" style="left: {{ $months['todayLeft'] }}%">
                                            <span class="absolute -left-[3px] -top-1 h-[7px] w-[7px] rounded-full bg-navy-950"></span>
                                        </span>
                                    @endif
                                </div>

                                @foreach ($lanes as $lane)
                                    <div class="relative flex items-stretch border-b border-line/70 last:border-b-0">
                                        <div class="z-10 flex w-[172px] shrink-0 items-center gap-2.5 bg-white py-4 pe-4">
                                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-page text-navy-400">
                                                <x-icon :name="$lane['icon']" class="h-4 w-4" />
                                            </span>
                                            <span class="min-w-0">
                                                <span class="block truncate text-[12px] font-bold text-navy-900">{{ $lane['label'] }}</span>
                                                <span class="block text-[10px] text-muted">{{ $lane['missions']->count() }} {{ str('mission')->plural($lane['missions']->count()) }}</span>
                                            </span>
                                        </div>

                                        <div class="relative min-h-[108px] flex-1 py-3">
                                            @foreach ($lane['missions'] as $m)
                                                @php
                                                    $start = $m['event']->starts_at;
                                                    $left = $start ? round($months['from']->diffInDays($start) / $months['span'] * 100, 3) : 0;
                                                    $on = $active && $m['id'] === $active['id'];
                                                @endphp
                                                <button type="button" wire:click="activate({{ $m['id'] }})" wire:key="fp-{{ $m['id'] }}"
                                                        @class([
                                                            'absolute top-3 z-10 flex w-[236px] items-center gap-2.5 rounded-2xl border p-2 text-left transition',
                                                            'border-indigo-400 bg-white shadow-[0_16px_36px_-20px_rgba(79,70,229,0.65)] ring-2 ring-indigo-500/20' => $on,
                                                            'border-line bg-white/90 shadow-sm backdrop-blur hover:-translate-y-0.5 hover:border-indigo-200 hover:shadow-lg' => ! $on,
                                                        ])
                                                        style="left: min({{ $left }}%, calc(100% - 236px))">
                                                    <span class="h-11 w-14 shrink-0 overflow-hidden rounded-xl bg-navy-50">
                                                        @if ($m['cover'])
                                                            <img src="{{ $m['cover'] }}" alt="" class="h-full w-full object-cover">
                                                        @else
                                                            <x-event-crest :event="$m['event']" class="h-full w-full" />
                                                        @endif
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
