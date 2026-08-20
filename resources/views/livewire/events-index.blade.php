@php
    use App\Models\Event;
    use App\Models\EventApproval;
    use App\Models\EventContractPayment;

    // Event Type — one click each, no dropdown to open first.
    $typeTabs = ['all' => 'All Types', 'conference' => 'Conference', 'summit' => 'Summit',
        'exhibition' => 'Exhibition', 'workshop' => 'Workshop', 'gala' => 'Gala',
        'corporate' => 'Corporate', 'awards' => 'Awards'];

    // Mission Board · Radar · Timeline · Table · Calendar — exactly these
    // five, in this order, every time. Calendar is real and selectable; it
    // just doesn't have a calendar to show yet, and says so rather than
    // faking one.
    $views = [
        'board' => ['Mission Board', 'grid'],
        'radar' => ['Radar', 'chart'],
        'path' => ['Timeline', 'calendar'],
        'list' => ['Table', 'list'],
        'calendar' => ['Calendar', 'clock'],
    ];

    // Smart Views — the same queue/stage query params EventsIndex reads in
    // mount(), just presented as one command bar instead of a sidebar.
    $smartViews = [
        ['label' => 'All', 'href' => route('events.index'), 'count' => null, 'active' => ! $queue && ! $stage && $tab === 'all' && ! $starred, 'tone' => null],
        ['label' => 'Live', 'href' => route('events.index', ['stage' => 'live']), 'count' => Event::whereNull('archived_at')->where('stage', 'live')->count(), 'active' => $stage === 'live', 'tone' => null],
        ['label' => 'At Risk', 'href' => route('events.index', ['queue' => 'at_risk', 'sort' => 'health']), 'count' => null, 'active' => $queue === 'at_risk', 'tone' => 'risk'],
        ['label' => 'Awaiting Approval', 'href' => route('events.index', ['queue' => 'awaiting_approval']), 'count' => EventApproval::where('status', 'pending')->count(), 'active' => $queue === 'awaiting_approval', 'tone' => 'warn'],
        ['label' => 'Payment Required', 'href' => route('events.index', ['queue' => 'payment']), 'count' => EventContractPayment::query()->whereColumn('paid_cents', '<', 'amount_cents')->count() ?: null, 'active' => $queue === 'payment', 'tone' => 'warn'],
        ['label' => 'This Week', 'href' => route('events.index', ['queue' => 'this_week']), 'count' => Event::whereNull('archived_at')->whereBetween('starts_at', [now()->startOfWeek(), now()->endOfWeek()])->count(), 'active' => $queue === 'this_week', 'tone' => null],
        ['label' => 'VIP', 'href' => route('events.index', ['tab' => 'vip']), 'count' => Event::whereNull('archived_at')->whereIn('type', ['vip_reception', 'embassy_event', 'private_dinner'])->count() ?: null, 'active' => $tab === 'vip', 'tone' => null],
        ['label' => 'High Value', 'href' => route('events.index', ['queue' => 'high_value', 'sort' => 'budget']), 'count' => Event::whereNull('archived_at')->where('budget_cents', '>=', 10000000)->count() ?: null, 'active' => $queue === 'high_value', 'tone' => null],
    ];

    $hasActiveFilters = $q !== '' || $tab !== 'all' || $queue || $stage || $starred;
@endphp

<div class="eo-event-atmosphere space-y-5 rounded-[24px]">

    {{-- ══════════════════════════════════════════════════════════════
         1 · EVENT PORTFOLIO HEADER
         ══════════════════════════════════════════════════════════════ --}}
    <x-eo.page-header
        :eyebrow="$archived ? 'Archived Events · Event Portfolio Command' : 'Event Portfolio Command'"
        :title="$archived ? 'Archived missions' : 'Event Portfolio'"
        :subtitle="$archived ? 'Closed out and off the live board — still here if you need them.' : 'Manage every event mission across planning, commercial, and delivery.'"
    >
        <x-slot:actions>
            <x-eo.button variant="ghost" size="sm" href="{{ route('events.create') }}">Open Event Studio</x-eo.button>
            <x-eo.button size="sm" href="{{ route('events.create') }}">＋ Create Event</x-eo.button>
        </x-slot:actions>
    </x-eo.page-header>

    {{-- Portfolio pulse — exactly four figures, so the header reads at a
         glance rather than repeating what the filter bar and board already
         say. --}}
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
                <a href="{{ $f['href'] }}" wire:navigate class="block transition hover:-translate-y-0.5">
                    <x-eo.metric-pill :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$tone" />
                </a>
            @else
                <x-eo.metric-pill :label="$f['label']" :value="$f['value']" :hint="$f['note'] ?? null" :tone="$tone" />
            @endif
        @endforeach
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         2 · PORTFOLIO COMMAND FILTER BAR
         One panel, two rows — Smart Views, then Event Type — plus search,
         sort and clear along its own top edge. Never floating chips on the
         bare page background.
         ══════════════════════════════════════════════════════════════ --}}
    <div class="eo-soft-card space-y-4 p-4 sm:p-5">
        <div class="flex flex-wrap items-center gap-3">
            <p class="eo-label">Portfolio Command Filter</p>

            <div class="relative ms-auto">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search missions…"
                       class="eo-input h-9 w-44 !py-0 !ps-9 text-xs sm:w-52">
            </div>

            <div class="inline-flex items-center gap-0.5 rounded-xl border border-eo-line bg-white p-0.5">
                @foreach (['date' => 'Date', 'health' => 'Health', 'budget' => 'Budget'] as $key => $label)
                    <button type="button" wire:click="$set('sort', '{{ $key }}')"
                            @class(['rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition',
                                    'bg-eo-navy text-white' => $sort === $key,
                                    'text-eo-muted hover:text-eo-text' => $sort !== $key])>{{ $label }}</button>
                @endforeach
            </div>

            <button type="button" wire:click="toggleStarred"
                    @class(['eo-btn-ghost eo-btn-sm inline-flex items-center gap-1.5', '!text-eo-teal-ink' => $starred])>
                <x-icon name="star" class="h-3.5 w-3.5 {{ $starred ? 'fill-current' : '' }}" /> Starred
            </button>

            @if ($hasActiveFilters)
                <a href="{{ route('events.index') }}" wire:navigate
                   class="text-[11.5px] font-semibold text-eo-muted transition hover:text-eo-risk-ink">Clear filters</a>
            @endif
        </div>

        <div>
            <p class="eo-label mb-2">Smart views</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($smartViews as $item)
                    <a href="{{ $item['href'] }}" wire:navigate
                       @class([
                           'flex items-center gap-1.5 rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition',
                           'border-eo-navy bg-eo-navy text-white' => $item['active'],
                           'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30 hover:text-eo-text' => ! $item['active'],
                       ])>
                        {{ $item['label'] }}
                        @if ($item['count'])
                            <span @class([
                                'rounded-full px-1.5 text-[10px] font-bold',
                                'bg-white/20' => $item['active'],
                                'bg-eo-risk/15 text-eo-risk-ink' => ! $item['active'] && $item['tone'] === 'risk',
                                'bg-eo-warn/15 text-eo-warn-ink' => ! $item['active'] && $item['tone'] === 'warn',
                                'bg-eo-workspace text-eo-muted' => ! $item['active'] && ! in_array($item['tone'], ['risk', 'warn'], true),
                            ])>{{ $item['count'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>

        <div class="border-t border-eo-line pt-4">
            <p class="eo-label mb-2">Event type</p>
            <div class="flex flex-wrap gap-1.5">
                @foreach ($typeTabs as $key => $label)
                    <button type="button" wire:click="setTab('{{ $key }}')"
                            @class([
                                'rounded-full border px-3 py-1.5 text-[11.5px] font-semibold transition',
                                'border-eo-navy bg-eo-navy text-white' => $tab === $key,
                                'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30 hover:text-eo-text' => $tab !== $key,
                            ])>{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ══════════════════════════════════════════════════════════════
         3 · VIEW SWITCHER — fixed position, same row, every view. Radar's
         own visualisation lives inside the workspace below, never above
         this row, so nothing here ever shifts when Radar is selected.
         ══════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="inline-flex items-center gap-1 rounded-2xl border border-eo-line bg-white p-1 shadow-eo">
            @foreach ($views as $key => [$label, $icon])
                <button type="button" wire:click="setView('{{ $key }}')"
                        @class([
                            'relative flex items-center gap-2 rounded-xl px-3.5 py-2 text-[12.5px] font-bold transition',
                            'bg-gradient-to-b from-eo-teal-lit to-eo-teal-deep text-white shadow-eo-teal' => $view === $key,
                            'text-eo-muted hover:bg-eo-workspace hover:text-eo-text' => $view !== $key,
                        ])>
                    <x-icon :name="$icon" class="h-3.5 w-3.5" />{{ $label }}
                    @if ($key === 'calendar')
                        <span @class(['rounded-full px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wide',
                            'bg-white/20 text-white' => $view === $key,
                            'bg-eo-bg text-eo-muted' => $view !== $key])>Soon</span>
                    @endif
                </button>
            @endforeach
        </div>

        <p class="flex items-center gap-1.5 text-[11.5px] font-semibold text-eo-muted">
            <span class="h-1.5 w-1.5 rounded-full bg-eo-teal"></span>
            {{-- Every matching mission, not just what $deck holds on this
                 page — List's $deck is now just the current page (see
                 EventsIndex::render()), so the whole-book total has to come
                 from the paginator, which always knows it regardless of view. --}}
            {{ $rows->total() }} {{ str('mission')->plural($rows->total()) }} in view
        </p>

        <div class="ms-auto flex flex-wrap items-center gap-x-3.5 gap-y-1">
            @foreach ($statuses as $key => [$label, $tone, $hex])
                <span class="flex items-center gap-1.5 text-[10.5px] font-semibold text-eo-muted">
                    <span class="h-2 w-2 rounded-full ring-2 ring-white" style="background: {{ $hex }}; box-shadow: 0 0 0 1px {{ $hex }}33"></span>{{ $label }}
                </span>
            @endforeach
        </div>
    </div>

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
                    <div class="space-y-7">
                        @foreach ($board as $group)
                            @continue ($group['missions']->isEmpty())
                            <div>
                                <div class="mb-1 flex items-center gap-2">
                                    <h2 class="text-[14px] font-bold text-eo-text">{{ $group['label'] }}</h2>
                                    <span class="rounded-full bg-eo-workspace px-2 py-0.5 text-[11px] font-bold tabular-nums text-eo-muted">{{ $group['missions']->count() }}</span>
                                </div>
                                <p class="mb-3 text-[11.5px] text-eo-muted">
                                    {{ match ($group['label']) {
                                        'Needs Attention' => 'At risk, blocked, or waiting on a decision — open these first.',
                                        'Live / Active' => 'Running now, day by day.',
                                        'Upcoming' => 'Booked and ahead of you, soonest first.',
                                        default => 'Wrapped in the last 30 days.',
                                    } }}
                                </p>
                                <div class="grid gap-3.5 sm:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($group['missions'] as $m)
                                        <x-eo.mission-card :mission="$m" variant="board" selectAction="activate"
                                            :selected="$active && $active['id'] === $m['id']"
                                            wire:key="board-{{ $m['id'] }}" />
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>

                {{-- ── RADAR ── --}}
                @elseif ($view === 'radar')
                    @php
                        $radarMaxDays = 120;
                        $radarPool = $deck->reject(fn ($m) => $m['past'] ?? false)
                            ->sortBy(fn ($m) => $m['daysOut'] ?? 999)->take(14)->values();
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
                        $riskiest = $radarPool->sortByDesc($riskRank)->first();
                        $onTrack = $radarPool->filter(fn ($m) => ($m['healthGroup'] ?? null) === 'track')->count();

                        $heroMissions = $radarNodes->map(function ($node, $i) {
                            $m = $node['m'];

                            return [
                                'tone' => ($node['alert'] ?? false) ? 'risk' : (($m['live'] ?? false) ? 'live' : ((($m['healthGroup'] ?? null) === 'warn') ? 'warn' : 'ok')),
                                'x' => $node['left'], 'y' => $node['top'],
                                'label' => str($m['name'] ?? 'Mission')->limit(20),
                                'href' => isset($m['event']) ? route('events.hub', $m['event']) : null,
                                'featured' => $i === 0 || ($node['alert'] ?? false),
                                'readiness' => $m['progress'] ?? null,
                            ];
                        })->all();

                        $heroStats = [
                            ['value' => $radarPool->count(), 'label' => 'On sweep'],
                            ['value' => $radarPool->where('live', true)->count(), 'label' => 'Live', 'tone' => 'live'],
                            ['value' => $radarPool->filter(fn ($m) => ($m['healthGroup'] ?? null) === 'risk' || ($m['risk'] ?? null) === 'Critical')->count(), 'label' => 'At risk', 'tone' => 'risk'],
                            ['value' => $onTrack, 'label' => 'On track', 'tone' => 'ok'],
                        ];
                    @endphp
                    <x-eo.mission-radar
                        variant="hero"
                        label="Mission Radar"
                        story="Event orbit for the book — closer nodes land sooner; colour carries health; clusters share the same window. Open a node to view that mission, or pick it below to inspect it here first."
                        :missions="$heroMissions ?: null"
                        :stats="$heroStats"
                    />

                    {{-- Node clicks navigate straight to the event — the radar
                         component is shared with Command Center and isn't
                         wired for selection, so rather than fake it, these
                         rows give a real, working way to inspect a mission in
                         the panel first without leaving the page. --}}
                    @if ($radarPool->isNotEmpty())
                        <div class="mt-4 eo-soft-card !p-0 overflow-hidden">
                            <div class="border-b border-eo-line px-4 py-3">
                                <p class="eo-label">Inspect from the sweep</p>
                            </div>
                            <div class="divide-y divide-eo-line">
                                @foreach ($radarPool->take(6) as $m)
                                    <button type="button" wire:click="activate({{ $m['id'] }})" wire:key="radar-pick-{{ $m['id'] }}"
                                            @class(['flex w-full items-center gap-3 px-4 py-2.5 text-left transition',
                                                    'bg-eo-teal-soft/40' => $active && $active['id'] === $m['id'],
                                                    'hover:bg-eo-workspace' => ! $active || $active['id'] !== $m['id']])>
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $m['statusHex'] }}"></span>
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-[12px] font-semibold text-eo-text">{{ $m['name'] }}</span>
                                            <span class="block truncate text-[10.5px] text-eo-muted">{{ $m['client'] ?: 'No client' }} · {{ $m['timeline'] }}</span>
                                        </span>
                                        @if ($m['id'] === ($riskiest['id'] ?? null) && $riskRank($riskiest) > 1)
                                            <x-eo.status-pill tone="risk" class="!text-[9.5px] shrink-0">{{ $m['risk'] }}</x-eo.status-pill>
                                        @endif
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                {{-- ── TIMELINE ── --}}
                @elseif ($view === 'path')
                    <div class="eo-soft-card overflow-hidden !p-0">
                        <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-eo-line bg-eo-workspace px-4 py-3.5">
                            <div>
                                <p class="eo-label">Timeline</p>
                                <h2 class="text-[16px] font-bold text-eo-text">{{ $months['label'] ?? 'Strategic timeline' }}</h2>
                            </div>
                            <p class="hidden text-[11.5px] text-eo-muted sm:block">Where every mission sits in the year.</p>

                            <div class="ms-auto flex items-center gap-2">
                                <button type="button" data-fp-today class="eo-btn-ghost eo-btn-sm">
                                    <x-icon name="calendar" class="h-3.5 w-3.5" /> Today
                                </button>
                                <div class="flex h-8 items-center gap-0.5 rounded-xl border border-eo-line bg-white px-1">
                                    <button type="button" data-fp-zoom="-1" class="grid h-6 w-6 place-items-center rounded-lg text-eo-muted transition hover:bg-eo-bg" aria-label="Zoom out">−</button>
                                    <span data-fp-level class="w-10 text-center text-[10.5px] font-bold tabular-nums text-eo-text">100%</span>
                                    <button type="button" data-fp-zoom="1" class="grid h-6 w-6 place-items-center rounded-lg text-eo-muted transition hover:bg-eo-bg" aria-label="Zoom in">+</button>
                                </div>
                            </div>
                        </div>

                        @if (! $months)
                            <p class="px-4 py-10 text-center text-[12px] text-eo-muted">No mission carries a date yet, so there is no path to draw.</p>
                        @else
                            <div class="scrollbar-none overflow-x-auto p-4">
                                <div data-fp-canvas data-fp-base="{{ max(880, count($months['list']) * 190) }}"
                                     style="min-width: {{ max(880, count($months['list']) * 190) }}px">

                                    <div class="relative ms-[172px] h-9 border-b border-eo-line">
                                        @foreach ($months['list'] as $month)
                                            <span class="absolute top-1" style="left: {{ $month['left'] }}%">
                                                <span @class(['rounded-lg px-2 py-1 text-[10.5px] font-bold uppercase tracking-[0.14em]',
                                                    'bg-eo-teal text-white' => $month['current'],
                                                    'text-eo-muted' => ! $month['current']])>{{ $month['label'] }}</span>
                                            </span>
                                        @endforeach
                                    </div>

                                    <div class="relative">
                                        <div class="pointer-events-none absolute inset-y-0 left-[172px] right-0 z-0" aria-hidden="true">
                                            @foreach ($months['list'] as $month)
                                                <span class="absolute inset-y-0 w-px bg-eo-line/60" style="left: {{ $month['left'] }}%"></span>
                                            @endforeach
                                            @if ($months['todayLeft'] !== null)
                                                <span data-fp-line class="absolute inset-y-0 w-px bg-eo-teal/70" style="left: {{ $months['todayLeft'] }}%">
                                                    <span class="absolute -top-[3px] left-0 h-0 w-0 border-y-[6px] border-l-[9px] border-y-transparent border-l-eo-teal drop-shadow-[0_2px_3px_rgba(30,172,172,0.4)]"></span>
                                                </span>
                                            @endif
                                        </div>

                                        @foreach ($lanes as $lane)
                                            <div @class(['relative flex items-stretch border-b border-eo-line/70 last:border-b-0', 'bg-eo-workspace/60' => $loop->even])>
                                                <div class="z-10 flex w-[172px] shrink-0 items-center gap-2.5 py-4 pe-4 {{ $loop->even ? 'bg-eo-workspace/60' : 'bg-white' }}">
                                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-eo-bg text-eo-muted">
                                                        <x-icon :name="$lane['icon']" class="h-4 w-4" />
                                                    </span>
                                                    <span class="min-w-0">
                                                        <span class="block truncate text-[12px] font-bold text-eo-text">{{ $lane['label'] }}</span>
                                                        <span class="block text-[10px] text-eo-muted">{{ $lane['missions']->count() }} {{ str('mission')->plural($lane['missions']->count()) }}</span>
                                                    </span>
                                                </div>

                                                <div class="relative min-h-[108px] flex-1 py-3">
                                                    <div class="pointer-events-none absolute inset-x-0 bottom-2 h-px bg-eo-line/50" aria-hidden="true"></div>

                                                    @foreach ($lane['missions'] as $m)
                                                        @php
                                                            $start = $m['event']->starts_at;
                                                            $left = $start ? round($months['from']->diffInDays($start) / $months['span'] * 100, 3) : 0;
                                                            $on = $active && $m['id'] === $active['id'];
                                                        @endphp
                                                        <span class="pointer-events-none absolute bottom-2 z-0 h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 rounded-full ring-2 ring-white"
                                                              style="left: {{ $left }}%; background: {{ $m['statusHex'] }}" aria-hidden="true"></span>
                                                        <button type="button" wire:click="activate({{ $m['id'] }})" wire:key="fp-{{ $m['id'] }}"
                                                                @class(['absolute top-3 z-10 flex w-[236px] items-center gap-2.5 rounded-2xl border p-2 text-left transition',
                                                                    'border-eo-teal bg-white shadow-eo-teal ring-2 ring-eo-teal/25' => $on,
                                                                    'border-eo-line bg-white/90 shadow-sm backdrop-blur hover:-translate-y-0.5 hover:border-eo-teal/30 hover:shadow-lg' => ! $on])
                                                                style="left: min({{ $left }}%, calc(100% - 236px))">
                                                            <span class="min-w-0 flex-1">
                                                                <span class="block truncate text-[12px] font-bold text-eo-text">{{ $m['name'] }}</span>
                                                                <span class="block truncate text-[10px] text-eo-muted">{{ $m['shortDates'] }} · {{ $m['where'] }}</span>
                                                                <span class="mt-1 flex items-center gap-1.5">
                                                                    @if ($m['statusLabel'])<x-eo.status-pill tone="live" class="!text-[8.5px]">{{ $m['statusLabel'] }}</x-eo.status-pill>@endif
                                                                    <span class="text-[10px] font-bold tabular-nums text-eo-muted">{{ $m['progress'] }}%</span>
                                                                </span>
                                                                <span class="mt-1 block h-[3px] overflow-hidden rounded-full bg-eo-bg">
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

                {{-- ── TABLE ── --}}
                @elseif ($view === 'list')
                    <div class="space-y-4">
                        @if ($selectedIds)
                            <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-eo-risk/25 bg-eo-risk-soft px-4 py-2.5">
                                <p class="text-[12.5px] font-bold text-eo-text">
                                    {{ count($selectedIds) }} {{ str('event')->plural(count($selectedIds)) }} selected
                                </p>
                                <button type="button" wire:click="selectAllMatching"
                                        class="text-[11.5px] font-semibold text-eo-risk-ink underline-offset-2 hover:underline">Select everything matching these filters</button>
                                <button type="button" wire:click="clearSelection"
                                        class="text-[11.5px] font-semibold text-eo-muted hover:text-eo-text">Clear</button>

                                <x-confirm
                                        title="Delete {{ count($selectedIds) }} {{ str('event')->plural(count($selectedIds)) }} permanently?"
                                        :body="'Their tasks, budgets, documents, contracts and bookings go with them. Invoices and proposals are kept, unattached.'.PHP_EOL.PHP_EOL.'This cannot be undone.'"
                                        confirm="Delete permanently"
                                        run="$wire.deleteSelected()"
                                        class="ms-auto eo-btn-danger eo-btn-sm">
                                    Delete permanently
                                </x-confirm>
                            </div>
                        @endif

                        <div class="eo-table-wrap">
                            <div class="scrollbar-none overflow-x-auto">
                                <div class="min-w-[1080px]">
                                    @php
                                        $cols = 'grid-cols-[28px_minmax(220px,2.2fr)_100px_130px_150px_78px_130px_92px_112px_128px_36px]';
                                        $pageIds = $rows->pluck('id')->all();
                                        $pageAllOn = $pageIds !== [] && ! array_diff($pageIds, $selectedIds);
                                    @endphp

                                    <div class="eo-label grid {{ $cols }} items-center gap-3 border-b border-eo-line bg-eo-workspace px-4 py-3">
                                        <span>
                                            @can('manage-events')
                                                <input type="checkbox" @checked($pageAllOn)
                                                       wire:click="toggleSelectPage({{ \Illuminate\Support\Js::from($pageIds) }})"
                                                       class="h-3.5 w-3.5 cursor-pointer rounded border-eo-line">
                                            @endcan
                                        </span>
                                        <span>Event</span><span>Status</span><span>Dates</span><span>Location</span>
                                        <span class="text-center">Progress</span><span>Budget</span>
                                        <span class="text-center">Attendees</span><span>Health</span><span>Next action</span><span></span>
                                    </div>

                                    <div class="divide-y divide-eo-line">
                                        @foreach ($rows as $m)
                                            @php $on = $active && $m['id'] === $active['id']; @endphp
                                            @php $ticked = in_array($m['id'], $selectedIds, true); @endphp
                                            <div wire:key="row-{{ $m['id'] }}" wire:click="activate({{ $m['id'] }})"
                                                 @class(['relative grid cursor-pointer '.$cols.' items-center gap-3 px-4 py-3 transition',
                                                     'bg-eo-teal-soft/40' => $on && ! $ticked,
                                                     'bg-eo-risk-soft/60' => $ticked,
                                                     'hover:bg-eo-workspace' => ! $on && ! $ticked])>
                                                @unless ($on && ! $ticked)
                                                    <span class="absolute inset-y-2 left-0 w-[3px] rounded-full opacity-70" style="background: {{ $m['statusHex'] }}"></span>
                                                @endunless

                                                <span wire:click.stop>
                                                    @can('manage-events')
                                                        <input type="checkbox" @checked($ticked) wire:click="toggleSelect({{ $m['id'] }})"
                                                               class="h-3.5 w-3.5 cursor-pointer rounded border-eo-line">
                                                    @endcan
                                                </span>

                                                <div class="min-w-0">
                                                    <span class="block truncate text-[13px] font-bold text-eo-text">{{ $m['name'] }}</span>
                                                    <span class="block truncate text-[10.5px] text-eo-muted">{{ $m['client'] ?: ($m['description'] ?: '—') }}</span>
                                                </div>

                                                <span>@if ($m['statusLabel'])<x-eo.status-pill tone="live" class="!text-[9.5px]">{{ $m['statusLabel'] }}</x-eo.status-pill>@endif</span>

                                                <span class="min-w-0">
                                                    <span class="block truncate text-[11.5px] font-semibold text-eo-text">{{ $m['shortDates'] }}</span>
                                                    <span class="block text-[10px] text-eo-muted">{{ $m['duration'] }}</span>
                                                </span>

                                                <span class="block truncate text-[11.5px] text-eo-muted">{{ $m['where'] }}</span>

                                                <span class="text-center text-[12px] font-bold tabular-nums text-eo-text">{{ $m['progress'] }}%</span>

                                                <span class="min-w-0">
                                                    <span class="block truncate text-[11.5px] font-bold text-eo-text">{{ $m['budgetLabel'] }}</span>
                                                    <span class="mt-1 block h-[3px] overflow-hidden rounded-full bg-eo-bg">
                                                        <span class="block h-full rounded-full bg-eo-teal" style="width: {{ $m['budgetPct'] ?? 0 }}%"></span>
                                                    </span>
                                                </span>

                                                <span class="text-center">
                                                    <span class="block text-[13px] font-bold text-eo-text">{{ number_format($m['attendees']) }}</span>
                                                    <span class="block text-[9.5px] text-eo-muted">{{ $m['attendeeWord'] }}</span>
                                                </span>

                                                <span class="flex min-w-0 items-center gap-1.5 text-[11.5px] font-semibold">
                                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $m['riskHex'] }}"></span>
                                                    <span class="truncate text-eo-text">{{ $m['health'] }}</span>
                                                </span>

                                                <span class="min-w-0">
                                                    <span class="block truncate text-[11px] font-semibold text-eo-text">{{ $m['milestone']['title'] }}</span>
                                                    <span class="block truncate text-[10px] {{ $m['milestone']['overdue'] ? 'text-eo-risk-ink' : 'text-eo-muted' }}">{{ $m['milestone']['due'] }}</span>
                                                </span>

                                                <details class="relative justify-self-end" data-menu>
                                                    <summary class="grid h-7 w-7 cursor-pointer list-none place-items-center rounded-lg text-[15px] leading-none text-eo-muted transition hover:bg-eo-bg hover:text-eo-text [&::-webkit-details-marker]:hidden">⋮</summary>
                                                    <div class="absolute end-0 z-30 mt-1 w-44 overflow-hidden rounded-xl border border-eo-line bg-white py-1 shadow-eo-float">
                                                        <a href="{{ route('events.hub', $m['event']) }}" wire:navigate class="block px-3 py-2 text-[11.5px] font-semibold text-eo-text transition hover:bg-eo-workspace">Open event</a>
                                                        <button type="button" wire:click="toggleFavorite({{ $m['id'] }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-eo-text transition hover:bg-eo-workspace">{{ in_array($m['id'], $favoriteIds, true) ? 'Unstar' : 'Star' }}</button>
                                                        <button type="button" wire:click="duplicate({{ $m['id'] }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-eo-text transition hover:bg-eo-workspace">Duplicate</button>
                                                        <x-confirm title="Archive “{{ $m['name'] }}”?" body="It leaves every board and list." confirm="Archive" tone="warn" run="$wire.archive({{ $m['id'] }})"
                                                                   class="block w-full border-t border-eo-line px-3 py-2 text-start text-[11.5px] font-semibold text-eo-risk-ink transition hover:bg-eo-risk-soft">Archive</x-confirm>
                                                        @can('manage-events')
                                                            <x-confirm
                                                                    title="Delete “{{ $m['name'] }}” permanently?"
                                                                    :body="'Its tasks, budget, documents, contracts and bookings go with it. Invoices and proposals are kept, unattached.'.PHP_EOL.PHP_EOL.'This cannot be undone.'"
                                                                    confirm="Delete permanently"
                                                                    run="$wire.deleteEvent({{ $m['id'] }})"
                                                                    class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-eo-risk-ink transition hover:bg-eo-risk-soft">Delete permanently</x-confirm>
                                                        @endcan
                                                    </div>
                                                </details>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>

                            @if ($rows->hasPages())
                                <div class="flex flex-wrap items-center gap-3 border-t border-eo-line px-4 py-2.5">
                                    <p class="text-[11px] text-eo-muted">{{ $rows->firstItem() }}–{{ $rows->lastItem() }} of {{ $rows->total() }}</p>
                                    <div class="ms-auto">{{ $rows->links() }}</div>
                                </div>
                            @endif
                        </div>
                    </div>

                {{-- ── CALENDAR — real, selectable, honestly unbuilt ── --}}
                @else
                    <x-eo.empty-state icon="calendar" title="Calendar is coming soon"
                        hint="Month and week views are next for the portfolio. Mission Board, Radar, Timeline and Table already cover the same missions, sorted and grouped a different way each time." />
                @endif
            </div>

            @include('livewire.partials.event-detail-panel')
        </div>
    @endif
</div>
