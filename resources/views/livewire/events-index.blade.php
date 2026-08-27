@php
    // Event Type — one click each, no dropdown to open first.
    $typeTabs = ['all' => 'All Types', 'conference' => 'Conference', 'summit' => 'Summit',
        'exhibition' => 'Exhibition', 'workshop' => 'Workshop', 'gala' => 'Gala',
        'corporate' => 'Corporate', 'awards' => 'Awards'];

    // Mission Board · Timeline · Table · Calendar — exactly these four, in
    // this order, every time. Calendar is real and selectable; it just
    // doesn't have a calendar to show yet, and says so rather than faking
    // one.
    $views = [
        'board' => ['Mission Board', 'grid'],
        'path' => ['Timeline', 'calendar'],
        'list' => ['Table', 'list'],
        'calendar' => ['Calendar', 'clock'],
    ];

    $hasActiveFilters = $q !== '' || $tab !== 'all' || $queue || $stage || $starred;
@endphp

<div class="space-y-5 rounded-[24px] bg-[radial-gradient(120%_80%_at_100%_0%,rgba(212,175,55,0.06),transparent_45%),radial-gradient(90%_60%_at_0%_100%,rgba(11,31,58,0.045),transparent_40%)] bg-page">

    {{-- ══════════════════════════════════════════════════════════════
         1 · EVENT PORTFOLIO HEADER
         ══════════════════════════════════════════════════════════════ --}}
    <x-cc.header
        :eyebrow="$archived ? 'Archived Events · Event Portfolio Command' : 'Event Portfolio Command'"
        :title="$archived ? 'Archived missions' : 'Event Portfolio'"
        :subtitle="$archived ? 'Closed out and off the live board — still here if you need them.' : 'Manage every event mission across planning, commercial, and delivery.'"
    >
        <x-slot:actions>
            <a href="{{ route('events.create') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Open Event Studio</a>
            <a href="{{ route('events.create') }}" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Create Event</a>
        </x-slot:actions>
    </x-cc.header>

    {{-- Portfolio pulse — four figures and the Fleet Health strip share one
         band so the top reads as a single command surface, not a stack of
         thin rows. Figures glance the book; the fleet bars are a second,
         faster way into the same shared detail panel every view opens into. --}}
    <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_320px]">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-4">
            @foreach ($figures as $f)
                @php
                    $tone = match (true) {
                        str_contains(strtolower($f['label']), 'risk') => 'risk',
                        str_contains(strtolower($f['label']), 'health') => 'live',
                        str_contains(strtolower($f['label']), 'active') => 'ok',
                        default => null,
                    };
                @endphp
                @if ($f['href'] ?? null)
                    <a href="{{ $f['href'] }}" wire:navigate class="block">
                        <x-cc.kpi-tile :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$tone" />
                    </a>
                @else
                    <x-cc.kpi-tile :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$tone" />
                @endif
            @endforeach
        </div>

        <x-events.health-strip :deck="$deck" :active="$active" />
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         2 · PORTFOLIO COMMAND FILTER BAR
         Search, sort and clear along its own top edge, Event Type below.
         ══════════════════════════════════════════════════════════════ --}}
    <x-events.filter-bar :type-tabs="$typeTabs" :tab="$tab" :sort="$sort" :starred="$starred" :has-active-filters="$hasActiveFilters" />

    {{-- ══════════════════════════════════════════════════════════════
         3 · VIEW SWITCHER — fixed position, same row, every view.
         ══════════════════════════════════════════════════════════════ --}}
    {{-- Every matching mission, not just what $deck holds on this page —
         List's $deck is now just the current page (see
         EventsIndex::render()), so the whole-book total has to come from
         the paginator, which always knows it regardless of view. --}}
    <x-events.view-switcher :views="$views" :view="$view" :total="$rows->total()" :statuses="$statuses" />

    {{-- ══════════════════════════════════════════════════════════════
         4 · MAIN WORKSPACE + 5 · SELECTED EVENT DETAIL PANEL
         One grid for every view: workspace on the left, the one detail
         panel sticky on the right. Nothing renders detail at the bottom.
         ══════════════════════════════════════════════════════════════ --}}
    {{-- Same reasoning as the count above it: $deck can be legitimately
         empty on List's page N while real matches exist on page 1 — the
         "nothing matches at all" check has to ask the paginator's total,
         not the current page's own row count. --}}
    @if ($rows->total() === 0)
        <x-eo.empty-state icon="calendar" title="No mission matches" hint="Clear the filters, or create the first event of this kind.">
            <x-slot:actions>
                <x-eo.button href="{{ route('events.create') }}" size="sm">＋ Create Event</x-eo.button>
            </x-slot:actions>
        </x-eo.empty-state>
    @else
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_360px]">
            <div class="min-w-0">

                {{-- ── MISSION BOARD ── --}}
                @if ($view === 'board')
                    @php
                        // Spotlight the single highest-priority mission. The board
                        // is already ordered worst/soonest-first, so it's the first
                        // mission of the first non-empty group. Pulled out here so it
                        // headlines the board and isn't also drawn in the grid below.
                        $spotlight = collect($board)->first(fn ($g) => $g['missions']->isNotEmpty())['missions']->first() ?? null;
                        $spotlightId = $spotlight['id'] ?? null;
                    @endphp

                    @if ($spotlight)
                        <div class="mb-6">
                            <x-cc.mission-card :mission="$spotlight" variant="spotlight" selectAction="activate"
                                :selected="$active && $active['id'] === $spotlightId"
                                wire:key="spotlight-{{ $spotlightId }}" />
                        </div>
                    @endif

                    <div class="space-y-7">
                        @foreach ($board as $group)
                            @php $missions = $group['missions']->reject(fn ($m) => $m['id'] === $spotlightId)->values(); @endphp
                            @continue ($missions->isEmpty())
                            <div>
                                <div class="mb-1 flex items-center gap-2">
                                    <h2 class="text-[14px] font-bold text-ink">{{ $group['label'] }}</h2>
                                    <span class="rounded-full bg-page px-2 py-0.5 text-[11px] font-bold tabular-nums text-muted">{{ $missions->count() }}</span>
                                </div>
                                <p class="mb-3 text-[11.5px] text-muted">
                                    {{ match ($group['label']) {
                                        'Needs Attention' => 'At risk, blocked, or waiting on a decision — open these first.',
                                        'Live / Active' => 'Running now, day by day.',
                                        'Upcoming' => 'Booked and ahead of you, soonest first.',
                                        default => 'Wrapped in the last 30 days.',
                                    } }}
                                </p>
                                <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($missions as $m)
                                        <x-cc.mission-card :mission="$m" variant="board" selectAction="activate"
                                            :selected="$active && $active['id'] === $m['id']"
                                            wire:key="board-{{ $m['id'] }}" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                {{-- ── TIMELINE ── --}}
                @elseif ($view === 'path')
                    <x-events.timeline-view :months="$months" :lanes="$lanes" :active="$active" />

                {{-- ── TABLE ── --}}
                @elseif ($view === 'list')
                    <x-events.table-view :rows="$rows" :selected-ids="$selectedIds" :active="$active" :favorite-ids="$favoriteIds" />

                {{-- ── CALENDAR — real, selectable, honestly unbuilt ── --}}
                @else
                    <x-eo.empty-state icon="calendar" title="Calendar is coming soon"
                        hint="Month and week views are next for the portfolio. Mission Board, Timeline and Table already cover the same missions, sorted and grouped a different way each time." />
                @endif
            </div>

            @include('livewire.partials.event-detail-panel')
        </div>
    @endif
</div>
