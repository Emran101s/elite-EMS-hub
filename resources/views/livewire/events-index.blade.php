@php
    // KPI tone → the tile's fill and its ink. The reference gives each figure a
    // solid coloured tile rather than a tinted chip: five numbers in a row need
    // something to tell them apart at a glance.
    $tone = [
        'navy'  => ['bg-navy-950', 'text-gold-400'],
        'green' => ['bg-emerald-500', 'text-white'],
        'blue'  => ['bg-blue-500', 'text-white'],
        'red'   => ['bg-red-500', 'text-white'],
        'gold'  => ['bg-gold-500', 'text-navy-950'],
    ];
    $typeTabs = ['all' => 'All Events', 'conference' => 'Conferences', 'workshop' => 'Workshops',
        'exhibition' => 'Exhibitions', 'gala' => 'Galas', 'vip' => 'VIP', 'outdoor' => 'Outdoor'];
@endphp

<div class="space-y-4">

    {{-- ══════════ The five figures ══════════ --}}
    <div class="card overflow-hidden">
        <div class="grid grid-cols-2 divide-x divide-y divide-line sm:grid-cols-3 xl:grid-cols-5 xl:divide-y-0">
            @foreach ($kpis as $k)
                @php [$fill, $ink] = $tone[$k['tone']] ?? $tone['blue']; @endphp
                <div class="flex items-center gap-3.5 px-4 py-4">
                    <span class="grid h-11 w-11 shrink-0 place-items-center rounded-xl {{ $fill }} {{ $ink }}">
                        <x-icon :name="$k['icon']" class="h-5 w-5" />
                    </span>
                    <div class="min-w-0">
                        <p class="pf text-[26px] font-black leading-none text-navy-950">{{ $k['value'] }}</p>
                        <p class="mt-1 truncate text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-500">{{ $k['label'] }}</p>
                        <p class="truncate text-[10.5px] text-muted">{{ $k['note'] }}</p>
                    </div>
                    @if ($k['trend'])
                        {{-- Two windows compared, where the record carries a date
                             to compare them with. Never a trend nobody measured. --}}
                        <span @class([
                            'ms-auto shrink-0 self-start text-[11px] font-bold',
                            'text-emerald-600' => $k['trend']['up'],
                            'text-navy-400' => ! $k['trend']['up'],
                        ])>{{ $k['trend']['label'] }}</span>
                    @else
                        <span class="ms-auto shrink-0 self-start text-[11px] font-bold text-navy-200">—</span>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    {{-- ══════════ Toolbar ══════════ --}}
    {{-- The work on the left, the portfolio's own news on the right. The rail
         rides the journey and the wall; lanes and the calendar need the whole
         width to themselves. --}}
    <div @class(['grid gap-4', '2xl:grid-cols-[minmax(0,1fr)_324px]' => in_array($view, ['journey', 'grid'], true)])>
    <div class="min-w-0 space-y-4">

    {{-- ══════════ Toolbar ══════════
         Filters on the left, how you are looking at them on the right — in one
         bar, wrapping onto a second line before it ever squeezes. --}}
    <div class="card flex flex-wrap items-center gap-x-3 gap-y-2.5 px-3 py-2.5">

        <div class="scrollbar-none -mx-1 flex min-w-0 flex-1 items-center gap-1 overflow-x-auto px-1">
            @foreach ($typeTabs as $key => $label)
                <button type="button" wire:click="setTab('{{ $key }}')"
                        @class([
                            'shrink-0 rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                            'bg-navy-950 text-white' => $tab === $key && ! $starred,
                            'text-navy-500 hover:bg-navy-50 hover:text-navy-900' => ! ($tab === $key && ! $starred),
                        ])>{{ $label }}</button>
            @endforeach
            <span class="mx-1 h-5 w-px shrink-0 bg-line"></span>
            <button type="button" wire:click="toggleStarred"
                    @class([
                        'flex shrink-0 items-center gap-1.5 rounded-full px-3.5 py-1.5 text-xs font-bold transition',
                        'bg-gold-400 text-navy-950' => $starred,
                        'text-navy-500 hover:bg-gold-50 hover:text-gold-700' => ! $starred,
                    ])>
                <x-icon name="star" class="h-3.5 w-3.5 {{ $starred ? 'fill-current' : '' }}" /> Starred
            </button>
        </div>

        <div class="flex flex-wrap items-center gap-2">
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" wire:model.live.debounce.300ms="q" placeholder="Search events, clients, venues…"
                       class="input h-9 w-44 !rounded-xl !py-0 !ps-9 text-xs sm:w-52">
            </div>

            <select wire:model.live="sort" class="input h-9 w-auto shrink-0 !rounded-xl !py-0 text-xs">
                <option value="date">Sort: Date</option>
                <option value="health">Sort: Health</option>
                <option value="budget">Sort: Budget used</option>
            </select>

            {{-- Grid | Lanes | List | Calendar --}}
            <div class="flex h-9 shrink-0 items-center gap-0.5 rounded-xl border border-line bg-white p-0.5">
                @foreach (['journey' => 'chart', 'grid' => 'grid', 'lanes' => 'columns', 'list' => 'list', 'calendar' => 'calendar'] as $mode => $icon)
                    <button type="button" wire:click="$set('view', '{{ $mode }}')" title="{{ ucfirst($mode) }}"
                            @class([
                                'flex h-full items-center gap-1.5 rounded-lg px-2.5 text-xs font-bold capitalize transition',
                                'bg-navy-950 text-white' => $view === $mode,
                                'text-navy-500 hover:text-navy-900' => $view !== $mode,
                            ])>
                        <x-icon :name="$icon" class="h-3.5 w-3.5" /><span class="hidden 2xl:inline">{{ $mode }}</span>
                    </button>
                @endforeach
            </div>

            <a href="{{ route('events.create') }}" class="btn-gold btn-sm shrink-0">＋ Create Event</a>
        </div>
    </div>

    {{-- ══════════ LIST VIEW ══════════ --}}
    @if ($view === 'list')
        @php $pageIds = $events->pluck('id')->all(); $allOnPage = $pageIds && ! array_diff($pageIds, $selectedIds); @endphp

        {{-- bulk bar — only once something is ticked --}}
        @if (count($selectedIds))
            <div class="mb-3 flex flex-wrap items-center gap-3 rounded-2xl border border-gold-300 bg-gold-50/60 px-4 py-2.5">
                <span class="text-xs font-bold text-navy-900">{{ count($selectedIds) }} selected</span>
                @if ($events->total() > count($selectedIds))
                    <button type="button" wire:click="selectAllMatching"
                            class="text-eyebrow font-bold uppercase tracking-wide text-navy-600 hover:text-navy-900">Select all {{ $events->total() }} matching</button>
                @endif
                <button type="button" wire:click="clearSelection"
                        class="text-eyebrow font-bold uppercase tracking-wide text-navy-500 hover:text-navy-900">Clear</button>
                <button type="button" wire:click="deleteSelected"
                        wire:confirm="Permanently delete {{ count($selectedIds) }} {{ \Illuminate\Support\Str::plural('event', count($selectedIds)) }}? Everything inside them goes too. This cannot be undone."
                        class="ml-auto rounded-lg bg-risk px-3.5 py-1.5 text-xs font-bold text-white transition hover:brightness-110">
                    Delete selected ({{ count($selectedIds) }})
                </button>
            </div>
        @endif

        <div class="card overflow-x-auto">
            <table class="w-full min-w-[940px]">
                <thead>
                    <tr class="border-b border-line text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                        <th class="w-10 px-4 py-3">
                            <input type="checkbox" @checked($allOnPage)
                                   wire:click="toggleSelectPage({{ json_encode($pageIds) }})"
                                   title="Select everything on this page"
                                   class="h-4 w-4 cursor-pointer rounded border-line text-gold-500 focus:ring-gold-400">
                        </th>
                        <th class="px-3 py-3">Event</th>
                        <th class="px-3 py-3">Client</th>
                        <th class="px-3 py-3">Dates</th>
                        <th class="px-3 py-3">Stage</th>
                        <th class="px-3 py-3">Health</th>
                        <th class="px-3 py-3 text-right">Pax</th>
                        <th class="w-24 px-3 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($events as $event)
                        @php
                            $h = $health[$event->id] ?? null;
                            $m = $metrics[$event->id] ?? [];
                            $ticked = in_array($event->id, $selectedIds, true);
                        @endphp
                        <tr wire:key="row-{{ $event->id }}"
                            @class(['group border-b border-line last:border-0 transition hover:bg-page/50', 'bg-gold-50/40' => $ticked])>
                            <td class="px-4 py-3">
                                <input type="checkbox" @checked($ticked) wire:click="toggleSelect({{ $event->id }})"
                                       class="h-4 w-4 cursor-pointer rounded border-line text-gold-500 focus:ring-gold-400">
                            </td>
                            <td class="px-3 py-3">
                                <a href="{{ route('events.hub', $event) }}" class="block">
                                    <span class="block truncate text-sm font-bold text-navy-900 group-hover:text-gold-700">{{ $event->name }}</span>
                                    <span class="mt-0.5 block truncate text-eyebrow text-muted">
                                        {{ str($event->type)->replace('_', ' ')->title() }} ·
                                        {{ $event->venue?->name ?? trim($event->city.($event->country ? ', '.$event->country : ''), ', ') ?: 'Venue TBC' }}
                                    </span>
                                </a>
                            </td>
                            <td class="px-3 py-3 text-xs text-navy-700">{{ $event->client?->name ?? '—' }}</td>
                            <td class="px-3 py-3 text-xs text-navy-700 whitespace-nowrap">
                                {{ $event->starts_at?->format('d M Y') ?? '—' }}
                                @if ($event->ends_at && $event->starts_at && ! $event->ends_at->isSameDay($event->starts_at))
                                    <span class="text-muted">– {{ $event->ends_at->format('d M Y') }}</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="inline-block rounded-full px-2 py-0.5 text-eyebrow font-bold uppercase tracking-wide text-white"
                                      style="background: {{ \App\Models\Event::stageColor($event->stage) }}">
                                    {{ str($event->stage)->replace('_', ' ')->title() }}
                                </span>
                            </td>
                            <td class="px-3 py-3">
                                @if ($h)
                                    <span class="flex items-center gap-1.5 text-xs font-bold text-navy-900">
                                        <x-health-ring :percent="$h['score']" :group="$h['group']" size="h-6 w-6" />
                                        {{ $h['score'] !== null ? $h['score'].'%' : 'Not started' }}
                                    </span>
                                @else
                                    <span class="text-xs text-muted">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3 text-right text-xs font-semibold text-navy-900">{{ $m['participants'] ?? '—' }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                    <a href="{{ route('events.hub', $event) }}"
                                       class="rounded-lg bg-navy-50 px-2 py-1 text-eyebrow font-bold text-navy-700 hover:bg-navy-100">Open</a>
                                    <button type="button" wire:click="deleteEvent({{ $event->id }})"
                                            wire:confirm="Permanently delete “{{ $event->name }}”? Everything inside it goes too. This cannot be undone."
                                            class="rounded-lg bg-risk/10 px-2 py-1 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-16 text-center text-sm text-muted">No events match these filters.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if ($events->hasPages())
            <div class="mt-4">{{ $events->links() }}</div>
        @endif
    @endif

    {{-- ══════════ LANES VIEW ══════════ --}}
    @if ($view === 'lanes')

        {{-- ══ Next up ══
             This was the page's own navy slab — a 110px hero for one event,
             above a board that shows every event. It is now the same light bar
             the event hub wears, with the crest carrying the navy. One thing
             still shouts: a live event turns its countdown gold. ══ --}}
        @if ($nextUp)
            @php
                $nuStart = $nextUp->starts_at?->copy()->startOfDay();
                $nuDays = $nuStart ? (int) round(now()->startOfDay()->diffInDays($nuStart, false)) : null;
            @endphp
            <a href="{{ route('events.hub', $nextUp) }}"
               class="mb-4 flex flex-wrap items-center gap-x-5 gap-y-3 rounded-2xl border border-line bg-white px-4 py-2.5 shadow-[0_10px_26px_-20px_rgba(11,31,58,0.4)] transition hover:border-gold-300">

                {{-- crest — the navy on this bar --}}
                <span class="h-11 w-14 shrink-0 overflow-hidden rounded-lg ring-1 ring-line">
                    @if ($nextUp->cover_path)
                        <x-event-avatar :event="$nextUp" :ring="false" size="lg" class="h-full w-full [&>span]:h-full [&>span]:w-full [&>span]:rounded-none [&>span]:!bg-transparent [&>span]:ring-0" />
                    @else
                        <x-event-crest :event="$nextUp" class="h-full w-full" />
                    @endif
                </span>

                <span class="min-w-0 flex-1">
                    <span class="flex items-center gap-2">
                        <span class="eyebrow-gold">{{ $nextUpLive ? 'Happening now' : 'Next up' }}</span>
                        @if ($nextUpLive)
                            <span class="flex items-center gap-1 rounded-md bg-gold-500 px-1.5 py-0.5 text-[9px] font-bold uppercase tracking-wider text-navy-950">
                                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-navy-950"></span>Live
                            </span>
                        @endif
                    </span>
                    <span class="pf mt-0.5 block truncate text-[16px] font-bold text-navy-900">{{ $nextUp->name }}</span>
                    <span class="scrollbar-none mt-0.5 flex items-center gap-x-3 overflow-hidden whitespace-nowrap text-[11.5px] text-muted">
                        <span>{{ $nextUp->client?->name ?? str($nextUp->type)->replace('_', ' ')->title() }}</span>
                        @if ($nextUp->venue)<span class="flex items-center gap-1"><x-icon name="building" class="h-3 w-3 shrink-0 text-navy-300" />{{ $nextUp->venue->name }}</span>@endif
                        @if ($nextUp->starts_at)<span class="flex items-center gap-1"><x-icon name="calendar" class="h-3 w-3 shrink-0 text-navy-300" />{{ $nextUp->starts_at->format('j M Y') }}</span>@endif
                    </span>
                </span>

                <span class="flex shrink-0 items-center gap-x-5">
                    @foreach ([
                        ['Guests', $nextUpMetrics['participants'] ? number_format($nextUpMetrics['participants']) : '—'],
                        ['Sponsors', $nextUpMetrics['sponsors'] ?: '—'],
                        ['Budget', $nextUpMetrics['budget_used'] !== null ? $nextUpMetrics['budget_used'].'%' : '—'],
                    ] as [$l, $v])
                        <span class="hidden text-center sm:block">
                            <span class="pf block text-[17px] font-bold leading-none text-navy-900">{{ $v }}</span>
                            <span class="mt-1 block text-[9px] font-bold uppercase tracking-wider text-navy-300">{{ $l }}</span>
                        </span>
                    @endforeach

                    @if ($nuDays !== null)
                        <span class="border-l border-line pl-5 text-center">
                            <span class="pf block text-[17px] font-bold leading-none {{ $nextUpLive ? 'text-gold-600' : 'text-navy-900' }}">{{ $nextUpLive ? 'LIVE' : abs($nuDays) }}</span>
                            <span class="mt-1 block text-[9px] font-bold uppercase tracking-wider text-navy-300">{{ $nextUpLive ? 'now' : ($nuDays > 0 ? 'days to go' : 'days ago') }}</span>
                        </span>
                    @endif

                    <x-health-ring :percent="$nextUpHealth['score']" :group="$nextUpHealth['group']" size="h-9 w-9" textSize="text-[9px]" />
                    <span class="btn-gold btn-sm shrink-0">Open hub →</span>
                </span>
            </a>
        @endif

        {{-- ══ THE BOARD ══
             Every event on its way to the floor. Lanes replaced the card grid:
             a grid says nothing about where an event is, and the board is the
             only view where the shape of the pipeline is visible at a glance.
             The route between lanes is drawn from the real column positions
             after layout, so it stays true at any width. ══ --}}
        <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_320px]">
            <div class="min-w-0">
                <div class="mb-3 flex flex-wrap items-baseline gap-2">
                    <h2 class="pf text-h1 font-bold text-navy-900">The Board</h2>
                    <span class="text-xs text-muted">{{ $events->total() }} {{ str('event')->plural($events->total()) }} · hover to trace a route · click to inspect</span>
                </div>

                @if ($events->total() === 0)
                    <x-empty icon="calendar" title="No events match these filters"
                             hint="Clear the search or filters, or create a new event to get started.">
                        <x-slot:actions>
                            <a href="{{ route('events.create') }}" class="btn-gold btn-sm">＋ Create Event</a>
                        </x-slot:actions>
                    </x-empty>
                @else
                    <div class="relative" data-board>
                        <svg data-wires class="pointer-events-none absolute inset-0 z-0 h-full w-full overflow-visible" aria-hidden="true"></svg>

                        <div class="relative z-10 grid gap-5 md:grid-cols-3">
                            @foreach ($lanes as $lane)
                                <div data-lane="{{ $lane['key'] }}">
                                    <div class="mb-2.5 flex items-center gap-2" data-lane-head>
                                        <span @class([
                                            'h-2.5 w-2.5 shrink-0 rounded-full',
                                            'bg-navy-200' => $lane['key'] === 0,
                                            'bg-gold-500' => $lane['key'] === 1,
                                            'bg-track' => $lane['key'] === 2,
                                        ])></span>
                                        <b class="text-xs font-bold text-navy-900">{{ $lane['title'] }}</b>
                                        <span class="hidden text-[10px] text-muted lg:inline">{{ $lane['note'] }}</span>
                                        <span class="ml-auto grid h-5 min-w-5 place-items-center rounded-full bg-white px-1.5 text-[10.5px] font-bold text-navy-600 ring-1 ring-line">{{ $lane['events']->count() }}</span>
                                    </div>

                                    @forelse ($lane['events'] as $event)
                                        @include('livewire.partials.events.lane-card', [
                                            'event' => $event,
                                            'h' => $health[$event->id] ?? null,
                                            'm' => $metrics[$event->id] ?? [],
                                            'isFav' => in_array($event->id, $favoriteIds, true),
                                            'isOpen' => $expandedId === $event->id,
                                        ])
                                    @empty
                                        <p class="rounded-2xl border border-dashed border-line px-3 py-6 text-center text-[11px] text-muted">Nothing here.</p>
                                    @endforelse
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>

            {{-- ══ INSPECTOR — replaces the card that used to expand in place ══ --}}
            <aside class="self-start xl:sticky xl:top-4">
                @if ($expanded && $selected)
                    <div class="card overflow-hidden">
                        <div class="flex items-start justify-between gap-3 border-b border-line px-4 py-3">
                            <div class="min-w-0">
                                <p class="eyebrow">Inspector</p>
                                <p class="pf mt-1 truncate text-[17px] font-bold text-navy-900">{{ $selected?->name }}</p>
                            </div>
                            <button type="button" wire:click="closeInspector"
                                    class="grid h-7 w-7 shrink-0 place-items-center rounded-lg text-navy-400 transition hover:bg-navy-50 hover:text-navy-900" aria-label="Close">✕</button>
                        </div>
                        <div class="p-4">
                            @include('livewire.partials.events.detail', ['event' => $selected, 'expanded' => $expanded])
                        </div>
                    </div>
                @else
                    <div class="card p-5 text-center">
                        <p class="eyebrow">Inspector</p>
                        <p class="mt-3 text-[12.5px] leading-relaxed text-muted">
                            Pick an event from the board. It opens here — nothing expands, nothing moves.
                        </p>
                    </div>
                @endif
            </aside>
        </div>
    @endif

    @script
    <script>
        /* The connectors. Measured from the live lane positions so they stay
           true at any width, and redrawn when Livewire replaces the board. */
        const draw = () => {
            const board = document.querySelector('[data-board]');
            const svg = board?.querySelector('[data-wires]');
            if (!board || !svg) return;

            const box = board.getBoundingClientRect();
            svg.setAttribute('viewBox', `0 0 ${box.width} ${box.height}`);

            const heads = [...board.querySelectorAll('[data-lane-head]')].map(h => {
                const r = h.getBoundingClientRect();
                return { l: r.left - box.left, r: r.right - box.left, y: r.top - box.top + r.height / 2 };
            });
            if (heads.length < 2 || heads[0].y !== heads[1].y) { svg.innerHTML = ''; return; }

            let out = `<defs><marker id="ev-arw" viewBox="0 0 10 10" refX="8" refY="5"
                        markerWidth="7" markerHeight="7" orient="auto-start-reverse">
                        <path d="M0 1 L9 5 L0 9 z" fill="#0B1F3A" fill-opacity=".34"/></marker></defs>`;

            for (let i = 0; i < heads.length - 1; i++) {
                const a = heads[i], b = heads[i + 1], y = a.y;
                out += `<path d="M${a.r + 6} ${y} C${a.r + 16} ${y - 14}, ${b.l - 16} ${y - 14}, ${b.l - 6} ${y}"
                         fill="none" stroke="#0B1F3A" stroke-opacity=".26" stroke-width="1.5"
                         stroke-dasharray="5 4" marker-end="url(#ev-arw)"/>`;
            }

            board.querySelectorAll('[data-card]').forEach(el => {
                const r = el.getBoundingClientRect();
                const lane = el.closest('[data-lane]');
                const h = heads[[...board.querySelectorAll('[data-lane]')].indexOf(lane)];
                if (!h) return;
                const x = r.left - box.left + 22, y = r.top - box.top;
                out += `<path d="M${h.l + 5} ${h.y + 8} C${h.l + 5} ${(h.y + y) / 2}, ${x} ${(h.y + y) / 2}, ${x} ${y - 3}"
                         fill="none" stroke="#B8942C" stroke-opacity=".22" stroke-width="1.2"/>`;
            });

            svg.innerHTML = out;
        };

        requestAnimationFrame(draw);
        addEventListener('resize', draw);
        Livewire.hook('morph.updated', () => requestAnimationFrame(draw));
    </script>
    @endscript

    {{-- ══════════ CALENDAR VIEW ══════════ --}}
    @if ($view === 'calendar' && $calendar)
        <div class="card overflow-hidden">
            <div class="flex items-center justify-between border-b border-line px-5 py-3.5">
                <h2 class="pf text-h1 font-bold text-navy-900">{{ $calendar['label'] }}</h2>
                <div class="flex items-center gap-1">
                    <button type="button" wire:click="prevMonth" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-navy-500 transition hover:border-gold-300 hover:text-navy-900">‹</button>
                    <button type="button" wire:click="nextMonth" class="flex h-8 w-8 items-center justify-center rounded-lg border border-line text-navy-500 transition hover:border-gold-300 hover:text-navy-900">›</button>
                </div>
            </div>

            {{-- weekday header --}}
            <div class="grid grid-cols-7 border-b border-line bg-page/40">
                @foreach (['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $d)
                    <div class="px-3 py-2 text-eyebrow font-bold uppercase tracking-wider text-muted">{{ $d }}</div>
                @endforeach
            </div>

            {{-- weeks --}}
            <div class="grid grid-cols-7">
                @foreach ($calendar['weeks'] as $week)
                    @foreach ($week as $day)
                        @php $isToday = $day['date']->isToday(); @endphp
                        <div @class([
                            'min-h-[104px] border-b border-r border-line p-1.5 last:border-r-0',
                            'bg-white' => $day['inMonth'],
                            'bg-page/40' => ! $day['inMonth'],
                        ])>
                            <div class="mb-1 flex justify-end">
                                <span @class([
                                    'flex h-6 w-6 items-center justify-center rounded-full text-xs font-bold',
                                    'bg-gold-400 text-navy-950' => $isToday,
                                    'text-navy-700' => ! $isToday && $day['inMonth'],
                                    'text-navy-300' => ! $day['inMonth'],
                                ])>{{ $day['date']->day }}</span>
                            </div>
                            <div class="space-y-1">
                                @foreach ($day['events']->take(3) as $ev)
                                    @php $evHex = \App\Models\Event::stageColor($ev->stage); @endphp
                                    <a href="{{ route('events.hub', $ev) }}"
                                       class="flex items-center gap-1.5 truncate rounded-md px-1.5 py-1 text-eyebrow font-bold text-navy-700 transition hover:bg-page"
                                       style="background: color-mix(in srgb, {{ $evHex }} 12%, transparent)"
                                       title="{{ $ev->name }}">
                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full" style="background: {{ $evHex }}"></span>
                                        <span class="truncate">{{ $ev->name }}</span>
                                    </a>
                                @endforeach
                                @if ($day['events']->count() > 3)
                                    <p class="px-1.5 text-eyebrow font-bold text-muted">+{{ $day['events']->count() - 3 }} more</p>
                                @endif
                            </div>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>
    @endif

    {{-- ══════════ JOURNEY — every event across its lifecycle ══════════
         The wall answers "how is it going" with one number, and one number
         cannot say WHERE an event is: a 60% that has not started production
         and a 60% three days from doors are the same figure describing two
         different situations. This says which of the five it is in. --}}
    @if ($view === 'journey')
        <div class="@container/j card overflow-hidden">
            <div class="border-b border-line px-4 py-3.5">
                <h2 class="pf text-[16px] font-bold text-navy-950">Event journey</h2>
                <p class="text-[11.5px] text-muted">Track every event across its lifecycle.</p>
            </div>

            {{-- the five phases, numbered, with the rail they sit on --}}
            <div class="hidden border-b border-line px-4 py-4 @3xl/j:block">
                <div class="relative grid grid-cols-5">
                    <span class="pointer-events-none absolute left-[10%] right-[10%] top-[13px] h-[3px] rounded-full bg-navy-50" aria-hidden="true"></span>
                    @foreach ($phases as $key => [$label, $note, $hex])
                        <div class="relative flex flex-col items-center text-center">
                            <span class="grid h-[27px] w-[27px] place-items-center rounded-full border-[2.5px] bg-white text-[11px] font-black"
                                  style="border-color: {{ $hex }}; color: {{ $hex }}">{{ $loop->iteration }}</span>
                            <span class="mt-2 text-[12.5px] font-bold text-navy-900">{{ $label }}</span>
                            <span class="text-[10.5px] text-muted">{{ $note }}</span>
                        </div>
                    @endforeach
                </div>
            </div>

            <div class="divide-y divide-line">
                @forelse ($journey as $r)
                    @php $e = $r['event']; $pct = max(0, min(100, (int) $r['progress'])); $ring = 2 * M_PI * 26; @endphp
                    <div wire:key="j-{{ $e->id }}" class="flex flex-wrap items-center gap-x-4 gap-y-3 px-4 py-3.5 transition hover:bg-page/40">

                        {{-- who --}}
                        <div class="flex min-w-[210px] flex-1 items-center gap-3 @3xl/j:max-w-[248px] @3xl/j:flex-none">
                            <span class="h-[52px] w-[70px] shrink-0 overflow-hidden rounded-xl bg-navy-50">
                                @if ($e->coverUrl())
                                    <img src="{{ $e->coverUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <x-event-crest :event="$e" class="h-full w-full" />
                                @endif
                            </span>
                            <div class="min-w-0">
                                <a href="{{ route('events.hub', $e) }}" class="pf line-clamp-2 text-[13.5px] font-bold leading-tight text-navy-950 transition hover:text-gold-700">{{ $e->name }}</a>
                                <p class="mt-1 flex items-center gap-1 truncate text-[10.5px] text-muted"><x-icon name="pin" class="h-3 w-3 shrink-0 text-navy-300" />{{ $r['where'] }}</p>
                                <p class="flex items-center gap-1 truncate text-[10.5px] text-muted"><x-icon name="calendar" class="h-3 w-3 shrink-0 text-navy-300" />{{ $r['when'] }}</p>
                            </div>
                        </div>

                        {{-- where it is in its own life --}}
                        <div class="order-last w-full min-w-[300px] @3xl/j:order-none @3xl/j:w-auto @3xl/j:flex-1">
                            <div class="grid grid-cols-5">
                                @foreach ($r['track'] as $p)
                                    @php
                                        $done = $p['state'] === 'completed';
                                        $here = in_array($p['state'], ['in_progress', 'live'], true);
                                    @endphp
                                    <div class="relative min-w-0 text-center">
                                        <p class="truncate text-[9.5px] font-semibold text-navy-400 @3xl/j:hidden">{{ $p['label'] }}</p>

                                        {{-- the rail: filled behind a phase you have done --}}
                                        <div class="relative mt-1.5 flex h-[18px] items-center @3xl/j:mt-0">
                                            @unless ($loop->first)
                                                <span class="absolute right-1/2 h-[3px] w-1/2 rounded-l-full" style="background: {{ $done || $here ? $p['hex'] : 'var(--color-navy-50)' }}"></span>
                                            @endunless
                                            @unless ($loop->last)
                                                <span class="absolute left-1/2 h-[3px] w-1/2 rounded-r-full" style="background: {{ $done ? $p['hex'] : 'var(--color-navy-50)' }}"></span>
                                            @endunless

                                            <span class="relative z-10 mx-auto grid place-items-center rounded-full transition"
                                                  @class(['h-[18px] w-[18px]' => $done, 'h-[16px] w-[16px] border-[3px] bg-white' => ! $done])
                                                  style="{{ $done ? 'background: '.$p['hex'] : 'border-color: '.($here ? $p['hex'] : 'var(--color-navy-100)') }}{{ $here ? '; box-shadow: 0 0 0 4px '.$p['hex'].'22' : '' }}"
                                                  title="{{ $p['label'] }} — {{ $p['word'] }}">
                                                @if ($done)<span class="text-[9px] font-black leading-none text-white">✓</span>@endif
                                            </span>
                                        </div>

                                        <p class="mt-1.5 text-[12px] font-black tabular-nums {{ $here || $done ? 'text-navy-900' : 'text-navy-300' }}">{{ $p['pct'] }}%</p>
                                        <p class="truncate text-[9.5px] {{ $here ? 'font-bold' : '' }}" style="color: {{ $here ? $p['hex'] : 'var(--color-muted, #7c8798)' }}">{{ $p['word'] }}</p>
                                    </div>
                                @endforeach
                            </div>
                        </div>

                        {{-- how it is doing --}}
                        <div class="flex shrink-0 items-center gap-2.5">
                            <span class="hidden rounded-full px-2 py-0.5 text-[9.5px] font-black uppercase tracking-[0.12em] {{ $r['chip'] }} @xl/j:inline-block">{{ $r['status'] }}</span>
                            <span class="relative grid h-[54px] w-[54px] shrink-0 place-items-center">
                                <svg class="h-[54px] w-[54px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                                    <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-navy-50)" stroke-width="6" />
                                    <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $r['hex'] }}" stroke-width="6" stroke-linecap="round"
                                            stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $pct / 100) }}" />
                                </svg>
                                <span class="absolute text-[12px] font-black text-navy-950">{{ $pct }}%</span>
                            </span>
                        </div>

                        {{-- how big, and what it is worth --}}
                        <div class="hidden shrink-0 items-center gap-4 @2xl/j:flex">
                            @foreach (array_slice($r['stats'], 0, 3) as [$icon, $value, $label])
                                <div class="min-w-0 text-center">
                                    <span class="flex items-center justify-center gap-1">
                                        <x-icon :name="$icon" class="h-3 w-3 shrink-0 text-navy-300" />
                                        <span class="pf text-[14px] font-black leading-none text-navy-950">{{ number_format($value) }}</span>
                                    </span>
                                    <span class="mt-0.5 block text-[9px] text-muted">{{ $label }}</span>
                                </div>
                            @endforeach
                            <div class="min-w-0 text-center">
                                <span class="flex items-center justify-center gap-1">
                                    <x-icon name="currency" class="h-3 w-3 shrink-0 text-navy-300" />
                                    <span class="whitespace-nowrap text-[12px] font-bold text-navy-950">{{ $r['budgetLine'] }}</span>
                                </span>
                                <span class="mt-0.5 block text-[9px] text-muted">Budget</span>
                            </div>
                        </div>

                        <div class="ms-auto flex shrink-0 items-center gap-1.5">
                            <a href="{{ route('events.hub', $e) }}"
                               class="flex h-8 items-center gap-1.5 rounded-lg px-3 text-[11px] font-bold transition {{ $r['button'] }}">
                                Open Command Center →
                            </a>
                            <details class="relative" data-menu>
                                <summary class="grid h-7 w-7 cursor-pointer list-none place-items-center rounded-lg text-[15px] leading-none text-navy-300 transition hover:bg-navy-50 hover:text-navy-700 [&::-webkit-details-marker]:hidden">⋮</summary>
                                <div class="absolute end-0 z-30 mt-1 w-44 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
                                    <button type="button" wire:click="toggleFavorite({{ $e->id }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">{{ in_array($e->id, $favoriteIds, true) ? 'Unstar' : 'Star' }} this event</button>
                                    <button type="button" wire:click="duplicate({{ $e->id }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">Duplicate</button>
                                    <button type="button" wire:click="archive({{ $e->id }})" wire:confirm="Archive “{{ $e->name }}”? It leaves every board and list."
                                            class="block w-full border-t border-line px-3 py-2 text-start text-[11.5px] font-semibold text-red-600 transition hover:bg-red-50">Archive</button>
                                </div>
                            </details>
                        </div>
                    </div>
                @empty
                    <p class="px-4 py-10 text-center text-[12px] text-muted">No event matches these filters.</p>
                @endforelse
            </div>

            <a href="{{ route('events.create') }}"
               class="m-3 flex h-11 items-center justify-center gap-1.5 rounded-xl border border-dashed border-line text-[12.5px] font-bold text-navy-500 transition hover:border-gold-300 hover:text-gold-700">
                ＋ Create New Event
            </a>
        </div>

    @endif

    {{-- ══════════ GRID — the portfolio wall ══════════ --}}
    @if ($view === 'grid')
        <div class="flex items-center gap-2.5">
            <h2 class="pf text-[17px] font-bold text-navy-950">All events</h2>
            <span class="chip">{{ $cards->where('live', true)->count() ?: $cards->count() }} {{ $cards->where('live', true)->count() ? 'live' : str('event')->plural($cards->count()) }}</span>
            <a href="{{ route('home') }}" class="ms-auto text-[12px] font-semibold text-navy-500 transition hover:text-navy-900">Operations Room →</a>
        </div>

        @if ($cards->isEmpty())
            <x-empty icon="calendar" title="No events match"
                     hint="Clear the filters, or create the first event of this kind." />
        @else
            {{-- Six columns so the first two cards run half-width and the rest
                 run in threes, as drawn. --}}
            <div class="@container/wall">
            <div class="grid gap-4 @2xl/wall:grid-cols-2 @6xl/wall:grid-cols-6">
                @foreach ($cards as $i => $c)
                    @php
                        $e = $c['event'];
                        $span = $i < 2 ? '@6xl/wall:col-span-3' : '@6xl/wall:col-span-2';
                        $ring = 2 * M_PI * 26;
                        $pct = max(0, min(100, (int) $c['progress']));
                    @endphp
                    <article wire:key="card-{{ $e->id }}" class="@container/card card relative flex flex-col overflow-hidden {{ $span }}">

                        {{-- ── the band ──
                             Dark only for the event that is actually happening;
                             every other card is a light wash of its own status,
                             so the wall reads at a glance without five dark
                             rectangles competing for the same attention. --}}
                        <div class="relative isolate">
                            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                                @if ($e->coverUrl())
                                    <img src="{{ $e->coverUrl() }}" alt="" class="h-full w-full object-cover" style="object-position: 50% 40%">
                                    <div class="absolute inset-0" style="background: {{ $c['dark']
                                        ? 'linear-gradient(100deg, rgba(6,17,33,.94) 0%, rgba(6,17,33,.80) 46%, rgba(6,17,33,.42) 100%)'
                                        : 'linear-gradient(100deg, rgba(255,255,255,.94) 0%, rgba(255,255,255,.80) 46%, rgba(255,255,255,.38) 100%)' }}"></div>
                                @elseif ($c['dark'])
                                    <div class="h-full w-full bg-navy-950" style="background-image:
                                        radial-gradient(120% 120% at 88% 0%, {{ $c['tint'] }} 0%, transparent 58%)"></div>
                                @else
                                    <div class="h-full w-full bg-white" style="background-image:
                                        radial-gradient(130% 130% at 92% 0%, {{ $c['tint'] }} 0%, transparent 62%)"></div>
                                @endif
                            </div>

                            {{-- the card's own menu, where every card carries one --}}
                            <details class="absolute end-2 top-2 z-20" data-menu>
                                <summary class="grid h-7 w-7 cursor-pointer list-none place-items-center rounded-lg text-[15px] leading-none transition {{ $c['dark'] ? 'text-white/60 hover:bg-white/10 hover:text-white' : 'text-navy-300 hover:bg-navy-50 hover:text-navy-700' }} [&::-webkit-details-marker]:hidden">⋮</summary>
                                <div class="absolute end-0 z-30 mt-1 w-44 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
                                    <a href="{{ route('events.hub', $e) }}" class="block px-3 py-2 text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">Open command center</a>
                                    <button type="button" wire:click="toggleFavorite({{ $e->id }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">
                                        {{ in_array($e->id, $favoriteIds, true) ? 'Unstar' : 'Star' }} this event
                                    </button>
                                    <button type="button" wire:click="duplicate({{ $e->id }})" class="block w-full px-3 py-2 text-start text-[11.5px] font-semibold text-navy-700 transition hover:bg-page">Duplicate</button>
                                    <button type="button" wire:click="archive({{ $e->id }})" wire:confirm="Archive “{{ $e->name }}”? It leaves every board and list."
                                            class="block w-full border-t border-line px-3 py-2 text-start text-[11.5px] font-semibold text-red-600 transition hover:bg-red-50">Archive</button>
                                </div>
                            </details>

                            <div class="flex items-start gap-3 p-4 pe-10">
                                <div class="min-w-0 flex-1">
                                    <span class="inline-flex items-center gap-1.5 rounded-full px-2 py-0.5 text-[9.5px] font-black uppercase tracking-[0.14em] {{ $c['chip'] }}">
                                        @if ($c['dark'])<span class="h-1.5 w-1.5 rounded-full bg-navy-950/70"></span>@endif{{ $c['status'] }}
                                    </span>

                                    <h3 class="pf mt-2 line-clamp-2 text-[21px] font-black leading-[1.12] {{ $c['dark'] ? 'text-white' : 'text-navy-950' }}">
                                        <a href="{{ route('events.hub', $e) }}" class="transition hover:opacity-80">{{ $e->name }}</a>
                                    </h3>

                                    <p class="mt-2 flex items-center gap-1.5 truncate text-[11.5px] {{ $c['dark'] ? 'text-white/75' : 'text-navy-600' }}">
                                        <x-icon name="pin" class="h-3.5 w-3.5 shrink-0 {{ $c['dark'] ? 'text-white/45' : 'text-navy-300' }}" />{{ $c['where'] }}
                                    </p>
                                    <p class="mt-1 flex items-center gap-1.5 truncate text-[11.5px] {{ $c['dark'] ? 'text-white/75' : 'text-navy-600' }}">
                                        <x-icon name="calendar" class="h-3.5 w-3.5 shrink-0 {{ $c['dark'] ? 'text-white/45' : 'text-navy-300' }}" />{{ $c['when'] }}
                                    </p>
                                </div>

                                {{-- The ring takes the card's colour too. --}}
                                <span class="relative grid h-[74px] w-[74px] shrink-0 place-items-center">
                                    <svg class="h-[74px] w-[74px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                                        <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $c['dark'] ? 'rgba(255,255,255,.18)' : 'var(--color-navy-100)' }}" stroke-width="5" />
                                        <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $c['hex'] }}" stroke-width="5" stroke-linecap="round"
                                                stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $pct / 100) }}" />
                                    </svg>
                                    <span class="absolute text-center leading-none">
                                        <span class="block text-[16px] font-black {{ $c['dark'] ? 'text-white' : 'text-navy-950' }}">{{ $pct }}%</span>
                                        <span class="mt-0.5 block text-[7px] font-bold uppercase tracking-[0.16em] {{ $c['dark'] ? 'text-white/55' : 'text-navy-400' }}">Progress</span>
                                    </span>
                                </span>
                            </div>

                            {{-- On the dark card the figures stay in the band, as
                                 drawn: it is one dark plate, not a plate and a
                                 stripe. --}}
                            @if ($c['dark'])
                                <div class="grid grid-cols-2 gap-y-3 border-t border-white/10 px-4 py-3 @sm/card:grid-cols-4">
                                    @foreach ($c['stats'] as [$icon, $value, $label])
                                        <div class="min-w-0">
                                            <span class="flex items-center gap-1.5">
                                                <x-icon :name="$icon" class="h-3.5 w-3.5 shrink-0 text-white/40" />
                                                <span class="pf text-[17px] font-black leading-none text-white">{{ number_format($value) }}</span>
                                            </span>
                                            <span class="mt-1 block truncate text-[9.5px] text-white/55">{{ $label }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </div>

                        @unless ($c['dark'])
                            <div class="grid grid-cols-2 gap-y-3 border-t border-line px-4 py-3 @sm/card:grid-cols-4">
                                @foreach ($c['stats'] as [$icon, $value, $label])
                                    <div class="min-w-0">
                                        <span class="flex items-center gap-1.5">
                                            <x-icon :name="$icon" class="h-3.5 w-3.5 shrink-0 text-navy-300" />
                                            <span class="pf text-[17px] font-black leading-none text-navy-950">{{ number_format($value) }}</span>
                                        </span>
                                        <span class="mt-1 block truncate text-[9.5px] text-muted">{{ $label }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @endunless

                        {{-- ── how it is going: every number over the bar it came from ── --}}
                        <div class="grid flex-1 grid-cols-2 divide-x divide-y divide-line border-t border-line @sm/card:grid-cols-4 @sm/card:divide-y-0">
                            @foreach ([
                                ['Budget', $c['budgetPct'] === null ? '—' : $c['budgetPct'].'% used', $c['budgetLine'], $c['budgetPct'], 'bg-emerald-500'],
                                ['Tasks', $c['tasksTotal'] ? $c['tasksDone'].' / '.$c['tasksTotal'] : '—', 'Open tasks', $c['tasksPct'], 'bg-blue-500'],
                                ['Risks', (string) $c['risks'], 'Active risks', $c['risks'] ? 100 : 0, $c['risks'] ? 'bg-red-500' : 'bg-navy-100'],
                            ] as [$label, $value, $note, $barPct, $barTone])
                                <div class="px-3.5 py-3">
                                    <p class="text-[10.5px] font-semibold text-navy-500">{{ $label }}</p>
                                    <p class="mt-1 truncate text-[12.5px] font-bold text-navy-950">{{ $value }}</p>
                                    <p class="truncate text-[10px] text-muted" title="{{ $note }}">{{ $note }}</p>
                                    <div class="mt-2 h-[3px] overflow-hidden rounded-full bg-navy-50">
                                        <div class="h-full rounded-full {{ $barTone }}" style="width: {{ max(0, min(100, (int) $barPct)) }}%"></div>
                                    </div>
                                </div>
                            @endforeach

                            <div class="px-3.5 py-3">
                                <p class="text-[10.5px] font-semibold text-navy-500">Health</p>
                                <p @class([
                                    'mt-1 flex items-center gap-1.5 text-[12.5px] font-bold',
                                    'text-red-600' => $c['group'] === 'risk',
                                    'text-amber-600' => $c['group'] === 'warn',
                                    'text-navy-300' => $c['group'] === 'neutral',
                                    'text-emerald-600' => ! in_array($c['group'], ['risk', 'warn', 'neutral'], true),
                                ])>
                                    <x-icon name="bell" class="h-3.5 w-3.5 shrink-0" />{{ $c['health'] }}
                                </p>
                            </div>
                        </div>

                        {{-- ── who is on it, and the way in ── --}}
                        <div class="flex items-center gap-2 border-t border-line px-4 py-3">
                            <div class="flex -space-x-2">
                                @forelse ($c['team'] as $member)
                                    <x-user-avatar :user="$member" size="h-7 w-7" class="ring-2 ring-white" />
                                @empty
                                    <span class="text-[11px] text-muted">No team assigned</span>
                                @endforelse
                                @if ($c['teamMore'])
                                    <span class="grid h-7 w-7 place-items-center rounded-full bg-navy-50 text-[9.5px] font-bold text-navy-500 ring-2 ring-white">+{{ $c['teamMore'] }}</span>
                                @endif
                            </div>

                            <a href="{{ route('events.hub', $e) }}"
                               class="ms-auto flex h-9 shrink-0 items-center gap-1.5 rounded-xl px-3.5 text-[11.5px] font-bold transition {{ $c['button'] }}">
                                Open Command Center →
                            </a>
                        </div>
                    </article>
                @endforeach
            </div>
            </div>
        @endif

    @endif

    {{-- ══════════ Under the board, whichever board it is ══════════
         When the events run, and where they are. Both views want these, so
         neither owns them. --}}
    @if (in_array($view, ['journey', 'grid'], true))
        <div class="@container/u">
            <div class="grid gap-4 @4xl/u:grid-cols-[minmax(0,1fr)_368px]">
            {{-- ══ Timeline Overview ══
                 Every event on one month scale. Two builds in the same week is
                 something you should see, not something you work out. --}}
            @if ($timeline)
                <div class="card overflow-hidden">
                    <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                        <h3 class="pf text-[15px] font-bold text-navy-950">Timeline overview</h3>
                        <p class="text-[11.5px] text-muted">All events schedule at a glance</p>
                    </div>

                    <div class="scrollbar-none overflow-x-auto p-4">
                        <div style="min-width: {{ max(640, count($timeline['months']) * 130) }}px">
                            {{-- month scale --}}
                            <div class="relative ms-[164px] h-6 border-b border-line">
                                @foreach ($timeline['months'] as $m)
                                    <span class="absolute top-0 text-[10px] font-bold uppercase tracking-[0.12em] text-navy-400" style="left: {{ $m['left'] }}%">{{ $m['label'] }}</span>
                                @endforeach
                            </div>

                            <div class="relative">
                                @foreach ($timeline['rows'] as $row)
                                    <div class="flex items-center border-b border-line/70 last:border-b-0">
                                        <a href="{{ route('events.hub', $row['event']) }}"
                                           class="flex w-[164px] shrink-0 items-center gap-2 py-2.5 pe-3 text-[12px] font-semibold text-navy-800 transition hover:text-gold-700">
                                            <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $row['hex'] }}"></span>
                                            <span class="truncate">{{ $row['event']->name }}</span>
                                        </a>

                                        <div class="relative flex-1 py-2.5">
                                            @foreach ($timeline['months'] as $m)
                                                <span class="absolute inset-y-0 w-px bg-line/60" style="left: {{ $m['left'] }}%"></span>
                                            @endforeach
                                            <a href="{{ route('events.hub', $row['event']) }}"
                                               class="relative flex h-7 items-center gap-1.5 overflow-hidden rounded-full px-2.5 text-[10.5px] font-bold text-white transition hover:brightness-110"
                                               style="margin-left: {{ $row['left'] }}%; width: {{ $row['width'] }}%; min-width: 92px; background: {{ $row['hex'] }}"
                                               title="{{ $row['event']->name }} · {{ $row['label'] }}">
                                                <span class="truncate">{{ $row['label'] }}</span>
                                            </a>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
            @endif
            {{-- ══ Events by region ══
                 Grouped rather than pinned: five regions is something you can read,
                 and a pin per event at this size is a smudge. --}}
            @if ($regions)
                <div class="card overflow-hidden">
                    <div class="flex items-baseline gap-2 border-b border-line px-4 py-3">
                        <h3 class="pf text-[15px] font-bold text-navy-950">Events by region</h3>
                        <span class="text-[11px] text-muted">All time</span>
                    </div>
                    <div class="relative h-[190px] overflow-hidden px-4 py-3">
                        {{-- A field, not a fake map: meridians and parallels place the
                             regions relative to one another without drawing coastlines
                             at a size where they would be a lie. --}}
                        <svg class="pointer-events-none absolute inset-0 h-full w-full" viewBox="0 0 200 100" preserveAspectRatio="none" aria-hidden="true">
                            @foreach ([20, 40, 60, 80] as $y)
                                <line x1="4" y1="{{ $y }}" x2="196" y2="{{ $y }}" stroke="var(--color-navy-50)" stroke-width="0.5" />
                            @endforeach
                            @foreach ([25, 50, 75, 100, 125, 150, 175] as $x)
                                <path d="M{{ $x }} 8 Q {{ $x + ($x - 100) * 0.12 }} 50 {{ $x }} 92" fill="none" stroke="var(--color-navy-50)" stroke-width="0.5" />
                            @endforeach
                        </svg>

                        @foreach ($regions as $region)
                            @php $size = 30 + min(26, $region['count'] * 7); @endphp
                            <div class="absolute -translate-x-1/2 -translate-y-1/2 text-center"
                                 style="left: {{ $region['x'] }}%; top: {{ $region['y'] }}%">
                                <span class="mx-auto grid place-items-center rounded-full font-black transition
                                             {{ $region['count'] ? 'bg-gold-400 text-navy-950 shadow-[0_6px_16px_-8px_rgba(212,175,55,0.9)]' : 'bg-white text-navy-300 ring-1 ring-line' }}"
                                      style="height: {{ $size }}px; width: {{ $size }}px; font-size: {{ $region['count'] > 9 ? 12 : 13 }}px">{{ $region['count'] }}</span>
                                <span class="mt-1 block whitespace-nowrap text-[10px] font-semibold text-navy-600">{{ $region['label'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif
            </div>
        </div>
    @endif

    </div>{{-- /main column --}}

    @if (in_array($view, ['journey', 'grid'], true))
        {{-- ══════════ THE RAIL ══════════ --}}
        <aside class="space-y-4">

            <div class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-line px-4 py-3">
                    <h3 class="pf text-[14px] font-bold text-navy-950">Today's actions</h3>
                    <a href="{{ route('tasks.index') }}" class="ms-auto text-[11px] font-semibold text-navy-400 transition hover:text-navy-900">View all</a>
                </div>
                <div class="divide-y divide-line">
                    @forelse ($todayActions as $a)
                        <a href="{{ $a['href'] ?? route('tasks.index') }}" class="flex items-start gap-2.5 px-4 py-2.5 transition hover:bg-page/60">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-navy-50 text-navy-500">
                                <x-icon :name="$a['icon']" class="h-3.5 w-3.5" />
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $a['title'] }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $a['where'] }}</span>
                            </span>
                            <span class="flex shrink-0 items-center gap-1.5 pt-0.5 text-[10px] font-semibold {{ $a['late'] ? 'text-red-600' : 'text-muted' }}">
                                {{ $a['when'] }}
                                <span class="h-1.5 w-1.5 rounded-full {{ $a['late'] ? 'bg-risk' : 'bg-track' }}"></span>
                            </span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-[11.5px] text-muted">Nothing is dated today, and nothing is late.</p>
                    @endforelse
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-baseline gap-2 border-b border-line px-4 py-3">
                    <h3 class="pf text-[14px] font-bold text-navy-950">Upcoming events</h3>
                    <span class="text-[10.5px] text-muted">Next 30 days</span>
                    <button type="button" wire:click="$set('view', 'calendar')" class="ms-auto text-[11px] font-semibold text-navy-400 transition hover:text-navy-900">View all</button>
                </div>
                <div class="divide-y divide-line">
                    @forelse ($upcoming as $u)
                        <a href="{{ route('events.hub', $u['event']) }}" class="flex items-start gap-3 px-4 py-2.5 transition hover:bg-page/60">
                            <span class="w-[62px] shrink-0 pt-0.5 text-[11px] font-bold text-navy-700">{{ $u['when'] }}</span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $u['event']->name }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $u['where'] }}</span>
                            </span>
                            <span @class([
                                'shrink-0 rounded-full px-1.5 py-0.5 text-[9px] font-black uppercase tracking-wide',
                                'bg-gold-400 text-navy-950' => $u['live'],
                                'bg-track/12 text-emerald-700' => ! $u['live'],
                            ])>{{ $u['live'] ? 'Live' : 'Upcoming' }}</span>
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-[11.5px] text-muted">Nothing in the next thirty days.</p>
                    @endforelse
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-line px-4 py-3">
                    <h3 class="pf text-[14px] font-bold text-navy-950">Event health overview</h3>
                </div>
                <div class="flex items-center gap-4 px-4 py-4">
                    @php $r = 2 * M_PI * 26; $done = 0; @endphp
                    <span class="relative grid h-[92px] w-[92px] shrink-0 place-items-center">
                        <svg class="h-[92px] w-[92px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                            <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-navy-50)" stroke-width="7" />
                            @foreach ($healthSplit['rows'] as $row)
                                @continue (! $row['count'])
                                @php $slice = $healthSplit['total'] ? $row['count'] / $healthSplit['total'] : 0; @endphp
                                <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $row['hex'] }}" stroke-width="7"
                                        stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $r - ($r * $slice) }}"
                                        transform="rotate({{ $done * 360 }} 30 30)" />
                                @php $done += $slice; @endphp
                            @endforeach
                        </svg>
                        <span class="absolute text-center leading-none">
                            <span class="pf block text-[20px] font-black text-navy-950">{{ $healthSplit['total'] }}</span>
                            <span class="mt-1 block text-[8.5px] font-bold uppercase tracking-[0.1em] text-muted">Total events</span>
                        </span>
                    </span>

                    <ul class="min-w-0 flex-1 space-y-1.5">
                        @foreach ($healthSplit['rows'] as $row)
                            <li class="flex items-center gap-2 text-[11.5px]">
                                <span class="h-2.5 w-2.5 shrink-0 rounded-sm" style="background: {{ $row['hex'] }}"></span>
                                <span class="w-4 shrink-0 font-bold tabular-nums text-navy-900">{{ $row['count'] }}</span>
                                <span class="truncate text-muted">{{ $row['label'] }}</span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </aside>
    @endif

    </div>{{-- /shell --}}

</div>
