@php
    // Event Type — one click each, no dropdown to open first.
    $typeTabs = ['all' => 'All', 'conference' => 'Conference', 'summit' => 'Summit',
        'exhibition' => 'Exhibition', 'workshop' => 'Workshop', 'gala' => 'Gala',
        'corporate' => 'Corporate', 'awards' => 'Awards'];

    // Mission Board · Timeline · Table · Calendar — exactly these four, in this
    // order, every time. Calendar is real and selectable; it just doesn't have
    // a calendar to show yet, and says so rather than faking one.
    $views = [
        'board' => 'Mission Board',
        'path' => 'Timeline',
        'list' => 'Table',
        'calendar' => 'Calendar',
    ];

    $hasActiveFilters = $q !== '' || $tab !== 'all' || $queue || $stage || $starred;

    // Concourse tone maps — one place, so the spotlight, the index rows and the
    // rail all read a mission's health the same way.
    $pillTone = fn ($g) => match ($g) {
        'risk' => 'tone-risk', 'warn' => 'tone-warn', 'ok' => 'tone-ok', 'live' => 'tone-info', default => 'tone-info',
    };
    $barTone = fn ($g) => match ($g) {
        'risk' => 'tone-risk', 'warn' => 'tone-warn', 'ok' => 'tone-ok', 'live' => 'tone-live', default => 'tone-faint',
    };
    $healthHex = fn ($g) => match ($g) {
        'risk' => 'var(--cx-risk)', 'warn' => 'var(--cx-warn)', 'ok' => 'var(--cx-ok)', default => 'var(--cx-info)',
    };
    $daysLabel = fn ($d) => match (true) {
        $d === null => null, $d < 0 => 'In progress', $d === 0 => 'Today', default => $d.'d out',
    };
@endphp

<div class="cx-canvas">

    {{-- ═══ MASTHEAD ═══ --}}
    <header class="cx-masthead cx-reveal cx-d1">
        <div>
            <div class="cx-brandline">
                <span class="cx-brandmark" aria-hidden="true"></span>
                <span class="cx-eyebrow">{{ $archived ? 'Elite Business Hub — Archive' : 'Elite Business Hub — Operations Desk' }}</span>
            </div>
            <h1 class="cx-h1">{{ $archived ? 'Archived' : 'Event' }} <em>{{ $archived ? 'Missions' : 'Portfolio' }}</em></h1>
            <div class="cx-dateline">
                <span class="cx-tick" aria-hidden="true"></span>
                <span>{{ now()->format('j M Y') }}</span><span style="opacity:.4">·</span>
                <span>{{ $archived ? 'Closed out and off the live board' : 'Every mission across planning, commercial & delivery' }}</span>
            </div>
        </div>
        <div style="display:flex;flex-direction:column;align-items:flex-end;gap:16px">
            <div class="cx-actions">
                <a href="{{ route('events.create') }}" class="cx-btn cx-btn-ghost">Event Studio</a>
                <a href="{{ route('events.create') }}" class="cx-btn cx-btn-accent">＋ Create Event</a>
            </div>
            <div class="cx-pulse" role="group" aria-label="Portfolio pulse">
                <div class="cx-stat"><span class="cx-num">{{ $rows->total() }}</span><span class="cx-lbl">{{ $archived ? 'Archived' : 'Live events' }}</span></div>
                <div class="cx-stat"><span class="cx-num">{{ $figures[0]['value'] === '—' ? '—' : \Illuminate\Support\Str::of($figures[0]['value'])->before('%') }}<s>{{ $figures[0]['value'] === '—' ? '' : '%' }}</s></span><span class="cx-lbl">Portfolio health</span></div>
                <div class="cx-stat is-risk"><span class="cx-num">{{ $figures[2]['value'] }}</span><span class="cx-lbl">At risk</span></div>
            </div>
        </div>
    </header>

    {{-- ═══ CONTROLS ═══ --}}
    <nav class="cx-controls cx-reveal cx-d2" aria-label="Portfolio filters">
        <div class="cx-seg" role="group" aria-label="View">
            @foreach ($views as $key => $label)
                <button type="button" wire:click="setView('{{ $key }}')" title="{{ $label }}" aria-pressed="{{ $view === $key ? 'true' : 'false' }}">
                    {{ $label === 'Mission Board' ? 'Board' : $label }}@if ($key === 'calendar')<span class="cx-soon">soon</span>@endif
                </button>
            @endforeach
        </div>

        <div class="cx-search">
            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" style="color:var(--cx-faint)"><circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search missions…" aria-label="Search missions">
        </div>

        <div class="cx-seg" role="group" aria-label="Sort by">
            @foreach (['date' => 'Date', 'health' => 'Health', 'budget' => 'Budget'] as $key => $label)
                <button type="button" wire:click="$set('sort', '{{ $key }}')" aria-pressed="{{ $sort === $key ? 'true' : 'false' }}">{{ $label }}</button>
            @endforeach
        </div>

        <button type="button" wire:click="toggleStarred" class="cx-chip {{ $starred ? 'is-on' : '' }}" aria-pressed="{{ $starred ? 'true' : 'false' }}">★ Starred</button>

        @if ($hasActiveFilters)
            <a href="{{ route('events.index') }}" wire:navigate class="cx-clear">Clear ✕</a>
        @endif

        <span class="cx-count">{{ $rows->total() }} in view</span>
    </nav>

    {{-- Event type row --}}
    <div class="cx-typebar cx-reveal cx-d2" role="group" aria-label="Event type" style="margin:-8px 0 20px">
        @foreach ($typeTabs as $key => $label)
            <button type="button" wire:click="setTab('{{ $key }}')" class="cx-chip {{ $tab === $key ? 'is-on' : '' }}" aria-pressed="{{ $tab === $key ? 'true' : 'false' }}">{{ $label }}</button>
        @endforeach
    </div>

    @if ($rows->total() === 0)
        <div class="cx-empty cx-reveal cx-d2">
            <h3>No mission matches</h3>
            <p>Clear the filters, or create the first event of this kind.</p>
            <a href="{{ route('events.create') }}" class="cx-btn cx-btn-accent" style="display:inline-flex">＋ Create Event</a>
        </div>
    @else
        <div class="cx-board">
            <div class="cx-main">

                {{-- ── HUB GRID — each event a hub of its modules ── --}}
                @if ($view === 'board')
                    @php
                        $spotlight = collect($board)->first(fn ($g) => $g['missions']->isNotEmpty())['missions']->first() ?? null;
                        // gate label → [icon, short label] for the module honeycomb
                        $gateMeta = [
                            'Agenda confirmed' => ['calendar', 'Agenda'],
                            'Speakers confirmed' => ['users', 'Speakers'],
                            'Suppliers contracted' => ['archive', 'Suppliers'],
                            'Venue assigned' => ['building', 'Venue'],
                            'Transport ready' => ['truck', 'Transport'],
                            'Approvals cleared' => ['check', 'Approvals'],
                            'No open severe risk' => ['bell', 'Risk'],
                        ];
                    @endphp

                    @if ($spotlight)
                        <a href="{{ ($spotlight['event'] ?? null) ? route('events.hub', $spotlight['event']) : route('events.index') }}" @if ($spotlight['event'] ?? null) wire:navigate @endif class="cx-ribbon cx-reveal cx-d2">
                            <span class="cx-rflag"><span class="cx-rbeacon" aria-hidden="true"></span>Priority</span>
                            <span class="cx-rnm">{{ $spotlight['name'] }}</span>
                            <span class="cx-rmeta">{{ collect([$daysLabel($spotlight['daysOut'] ?? null), $spotlight['milestone']['title'] ?? null])->filter()->implode(' · ') }}</span>
                            <span class="cx-rgo">Open hub →</span>
                        </a>
                    @endif

                    <div class="cx-hlegend cx-reveal cx-d2">
                        <span class="cx-lg"><i style="background:var(--cx-accent)"></i> Ready</span>
                        <span class="cx-lg"><i style="background:var(--cx-surface-2);box-shadow:inset 0 0 0 1.5px var(--cx-line)"></i> Pending</span>
                        <span class="cx-lg"><i style="background:var(--cx-risk-wash)"></i> Blocked</span>
                        <span style="margin-left:auto;color:var(--cx-faint)">Each hex is a module inside the event's hub</span>
                    </div>

                    @foreach ($board as $group)
                        @continue ($group['missions']->isEmpty())
                        <div class="cx-hgroup cx-reveal cx-d3">{{ $group['label'] }} <span class="cx-gc">{{ $group['missions']->count() }}</span> <span class="cx-gnote">{{ match ($group['label']) {
                            'Needs Attention' => 'at risk, blocked, or waiting on a decision',
                            'Live / Active' => 'running now, day by day',
                            'Upcoming' => 'booked and ahead of you',
                            default => 'wrapped in the last 30 days',
                        } }}</span></div>
                        <div class="cx-hgrid cx-reveal cx-d3">
                            @foreach ($group['missions'] as $m)
                                @php
                                    $g = $gates[$m['id']] ?? null;
                                    $ready = $g['pct'] ?? (is_null($m['progress'] ?? null) ? null : max(0, min(100, (int) $m['progress'])));
                                    $hg = $m['healthGroup'] ?? null;
                                    $dl = $daysLabel($m['daysOut'] ?? null);
                                    $sel = $active && $active['id'] === $m['id'];
                                @endphp
                                <div class="cx-hub {{ $hg === 'risk' ? 'is-flag' : '' }} {{ $sel ? 'is-selected' : '' }}" role="button" tabindex="0"
                                     wire:key="cx-hub-{{ $m['id'] }}" wire:click="activate({{ $m['id'] }})"
                                     wire:keydown.enter="activate({{ $m['id'] }})" wire:keydown.space.prevent="activate({{ $m['id'] }})"
                                     aria-pressed="{{ $sel ? 'true' : 'false' }}" aria-label="Select {{ $m['name'] }}">
                                    <div class="cx-hub-top">
                                        <div class="cx-core" style="--v:{{ $ready ?? 0 }}">
                                            <div class="cx-hex"><i></i></div>
                                            <div class="cx-cval"><b>{{ $ready === null ? '—' : $ready }}</b><s>{{ $ready === null ? 'early' : 'ready' }}</s></div>
                                        </div>
                                        <div class="cx-idn">
                                            <p class="cx-inm">{{ $m['name'] }}</p>
                                            <p class="cx-icl">{{ collect([$m['client'] ?? null, $m['where'] ?? null, $m['shortDates'] ?? $m['dates'] ?? null])->filter()->implode(' · ') ?: 'No client' }}</p>
                                            <div class="cx-irow">
                                                @if ($m['statusLabel'] ?? null)<span class="cx-hpill">{{ $m['statusLabel'] }}</span>@endif
                                                <span class="cx-hstat"><span class="cx-hd" style="background:{{ $healthHex($hg) }}"></span>Health <b>{{ $m['healthScore'] ?? '—' }}</b></span>
                                                @if ($dl)<span class="cx-hstat">· {{ $dl }}</span>@endif
                                            </div>
                                        </div>
                                    </div>

                                    @if ($g && ! empty($g['gates']))
                                        <div class="cx-mods">
                                            <div class="cx-mods-h"><span class="cx-mt">Modules in this hub</span><span class="cx-mr"><b>{{ $g['met'] }}</b> of {{ $g['total'] }} ready</span></div>
                                            <div class="cx-comb">
                                                @foreach ($g['gates'] as $gate)
                                                    @php [$gi, $gl] = $gateMeta[$gate['label']] ?? ['grid', (string) str($gate['label'])->words(1)];
                                                        $cls = $gate['met'] ? 'done' : (str_contains(strtolower($gate['label']), 'risk') ? 'risk' : 'pend'); @endphp
                                                    <span class="cx-cell {{ $cls }}" title="{{ $gate['label'] }} — {{ $gate['note'] }}">
                                                        <x-icon :name="$gi" class="h-3.5 w-3.5" />
                                                        <span class="cx-clbl">{{ $gl }}</span>
                                                    </span>
                                                @endforeach
                                            </div>
                                        </div>
                                    @endif

                                    <div class="cx-hfoot">
                                        <div class="cx-fx">
                                            <span class="cx-fk">{{ ($m['milestone'] ?? null) ? 'Needs you first' : 'Stage' }}</span>
                                            <span class="cx-fv {{ ($m['milestone']['overdue'] ?? false) ? 'is-risk' : '' }}">{{ $m['milestone']['title'] ?? ($m['statusLabel'] ?? '—') }}</span>
                                        </div>
                                        @if ($m['event'] ?? null)
                                            <a href="{{ route('events.hub', $m['event']) }}" wire:navigate onclick="event.stopPropagation()" class="cx-enter">Enter hub →</a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endforeach

                {{-- ── TIMELINE / TABLE / CALENDAR — not yet on Concourse, framed on a
                     surface so the seam reads as "coming next", not broken. ── --}}
                @elseif ($view === 'path')
                    <div class="cx-legacy cx-reveal cx-d3"><x-events.timeline-view :months="$months" :lanes="$lanes" :active="$active" /></div>
                @elseif ($view === 'list')
                    <div class="cx-legacy cx-reveal cx-d3"><x-events.table-view :rows="$rows" :selected-ids="$selectedIds" :active="$active" :favorite-ids="$favoriteIds" /></div>
                @else
                    <div class="cx-empty cx-reveal cx-d3">
                        <h3>Calendar is coming soon</h3>
                        <p>Month and week views are next for the portfolio. Board, Timeline and Table already cover the same missions, sorted a different way each time.</p>
                    </div>
                @endif
            </div>

            @include('livewire.partials.event-detail-panel')
        </div>
    @endif

    <div class="cx-footnote">
        <span>Concourse — a visual language for Elite Business Hub</span>
        <span>Fraunces · Hanken Grotesk · Spline Sans Mono</span>
    </div>
</div>
