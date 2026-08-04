@php
    $user = auth()->user();
    $hour = (int) $now->copy()->setTimeFrom(now())->format('H');
    $greeting = match (true) { $hour < 12 => 'Good morning', $hour < 18 => 'Good afternoon', default => 'Good evening' };

    // ── instrument cluster: each headline figure gets a dial, not a tile ──
    // "In the book" is the denominator everything else is read against, so its
    // dial is always full — it is the panel's own zero-point, not a metric.
    $ringR = 30;
    $ring = 2 * M_PI * $ringR;
    $dialHex = ['navy' => 'var(--color-gold-400)', 'green' => '#10b981', 'blue' => '#3b82f6', 'red' => '#ef4444', 'gold' => 'var(--color-gold-500)'];
    $totalEvents = $events->count();
    $figures = collect($figures)->map(function ($f) use ($totalEvents, $dialHex) {
        preg_match('/^(\d+)\s+past/', $f['note'] ?? '', $m);
        $pct = match ($f['label']) {
            'In the book' => 100,
            'Live now', 'At risk' => $totalEvents ? round($f['value'] / $totalEvents * 100) : 0,
            'Open tasks' => $f['value'] ? round((int) ($m[1] ?? 0) / $f['value'] * 100) : 0,
            'Signals' => min(100, round($f['value'] / 10 * 100)),
            default => 100,
        };

        return $f + ['pct' => $pct, 'hex' => $dialHex[$f['tone']] ?? $dialHex['blue']];
    })->all();
@endphp

<div class="space-y-4">

    {{-- ══════════ THE SPOTLIGHT ══════════
         The one thing on the floor, or the next one in. It gets the photograph
         and the whole width because on any given morning it is the answer to
         "what am I actually doing today". --}}
    @if ($spotlight)
        @php
            $h = $spotlightHeader;
            $ready = $h['readiness'];
            $days = $spotlight->starts_at ? (int) $now->diffInDays($spotlight->starts_at, false) : null;
        @endphp

        <div class="relative isolate -mx-4 -mt-1 overflow-hidden bg-navy-950 lg:-mx-6">
            <div class="pointer-events-none absolute inset-0 -z-10" aria-hidden="true">
                @if ($spotlight->coverUrl())
                    <img src="{{ $spotlight->coverUrl() }}" alt="" class="h-full w-full object-cover" style="object-position: 50% 38%">
                @else
                    <x-event-crest :event="$spotlight" class="h-full w-full opacity-70" />
                @endif
                <div class="absolute inset-0" style="background: linear-gradient(100deg,
                    rgba(6,17,33,.96) 0%, rgba(6,17,33,.88) 42%, rgba(6,17,33,.55) 72%, rgba(6,17,33,.35) 100%)"></div>
                {{-- faint instrument-panel rings, the same radial language as the
                     mission radar on Events, at rest instead of in motion --}}
                <svg class="absolute -right-16 -top-24 h-[420px] w-[420px] opacity-[0.14]" viewBox="0 0 100 100" aria-hidden="true">
                    <circle cx="50" cy="50" r="46" fill="none" stroke="var(--color-gold-400)" stroke-width="0.4" />
                    <circle cx="50" cy="50" r="34" fill="none" stroke="var(--color-gold-400)" stroke-width="0.4" stroke-dasharray="2 3" />
                    <circle cx="50" cy="50" r="22" fill="none" stroke="var(--color-gold-400)" stroke-width="0.4" />
                </svg>
            </div>

            <div class="flex flex-wrap items-center gap-x-8 gap-y-5 px-4 py-6 lg:px-6 lg:py-7">

                <div class="min-w-[260px] flex-1">
                    <p class="flex items-center gap-2 text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-300/90">
                        @if ($spotlightLive)
                            <span class="relative flex h-2 w-2">
                                <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-emerald-400 opacity-70"></span>
                                <span class="relative inline-flex h-2 w-2 rounded-full bg-emerald-400"></span>
                            </span>
                            On the floor now
                        @else
                            <span class="h-px w-4 bg-gold-400"></span>Next in
                        @endif
                    </p>

                    <h1 class="pf mt-2 max-w-[20ch] text-[26px] font-black leading-[1.08] text-white lg:text-[34px]">
                        <a href="{{ route('events.hub', $spotlight) }}" class="transition hover:text-gold-300">{{ $spotlight->name }}</a>
                    </h1>

                    <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-1.5 text-[12.5px] text-white/70">
                        @if ($spotlight->venue || $spotlight->city)
                            <span class="flex items-center gap-1.5"><x-icon name="pin" class="h-3.5 w-3.5 text-white/40" />{{ $spotlight->venue?->name ?? $spotlight->city }}</span>
                        @endif
                        @if ($spotlight->starts_at)
                            <span class="flex items-center gap-1.5"><x-icon name="calendar" class="h-3.5 w-3.5 text-white/40" />{{ $spotlight->starts_at->format('j M') }} – {{ $spotlight->ends_at?->format('j M Y') ?? $spotlight->starts_at->format('Y') }}</span>
                        @endif
                        @if ($spotlight->client)
                            <span class="flex items-center gap-1.5"><x-icon name="identification" class="h-3.5 w-3.5 text-white/40" />{{ $spotlight->client->name }}</span>
                        @endif
                    </div>
                </div>

                {{-- the two numbers that matter about it, read off dials rather
                     than flat boxes — the same instrument face the panel below
                     is built from, so the hero and the cluster read as one idea --}}
                <div class="flex shrink-0 items-center gap-3">
                    @php
                        $runDay = $spotlightLive ? (int) $spotlight->starts_at->diffInDays($now) + 1 : null;
                        $runTotal = $spotlightLive ? max($runDay, (int) $spotlight->starts_at->diffInDays($spotlight->ends_at ?? $spotlight->starts_at) + 1) : null;
                        $readyTone = $ready['pct'] >= 70 ? '#34d399' : ($ready['pct'] >= 40 ? '#fbbf24' : '#f87171');
                    @endphp
                    <div class="relative h-[78px] w-[78px] shrink-0">
                        <svg class="h-full w-full -rotate-90" viewBox="0 0 78 78" aria-hidden="true">
                            <circle cx="39" cy="39" r="{{ $ringR }}" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="5" />
                            <circle cx="39" cy="39" r="{{ $ringR }}" fill="none" stroke="var(--color-gold-400)" stroke-width="5" stroke-linecap="round"
                                    stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * ($spotlightLive ? min($runDay / max($runTotal, 1) * 100, 100) : ($days === null ? 0 : 40)) / 100) }}" />
                        </svg>
                        <span class="absolute inset-0 grid place-items-center text-center leading-none">
                            <span class="pf text-[19px] font-black text-gold-300">{{ $spotlightLive ? $runDay : ($days === null ? '—' : $days.'d') }}</span>
                            <span class="mt-6 text-[8px] font-bold uppercase tracking-[0.12em] text-white/45">{{ $spotlightLive ? 'of '.$runTotal : 'to go' }}</span>
                        </span>
                    </div>

                    <div class="relative h-[78px] w-[78px] shrink-0" title="{{ collect($ready['gates'])->map(fn ($g) => ($g['met'] ? '✓ ' : '✗ ').$g['label'])->implode(chr(10)) }}">
                        <svg class="h-full w-full -rotate-90" viewBox="0 0 78 78" aria-hidden="true">
                            <circle cx="39" cy="39" r="{{ $ringR }}" fill="none" stroke="rgba(255,255,255,.1)" stroke-width="5" />
                            <circle cx="39" cy="39" r="{{ $ringR }}" fill="none" stroke="{{ $readyTone }}" stroke-width="5" stroke-linecap="round"
                                    stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * min($ready['pct'], 100) / 100) }}" />
                        </svg>
                        <span class="absolute inset-0 grid place-items-center text-center leading-none">
                            <span class="pf text-[19px] font-black" style="color: {{ $readyTone }}">{{ $ready['pct'] }}%</span>
                            <span class="mt-6 text-[8px] font-bold uppercase tracking-[0.12em] text-white/45">ready</span>
                        </span>
                    </div>
                </div>

                {{-- and the one thing it needs from a person --}}
                <div class="w-full shrink-0 rounded-2xl bg-white/95 p-3.5 shadow-[0_14px_36px_-24px_rgba(0,0,0,.7)] backdrop-blur sm:w-[280px]">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.18em] text-navy-400">Next critical action</p>
                    @if ($h['critical'])
                        <p class="pf mt-1.5 line-clamp-2 text-[13.5px] font-bold leading-snug text-navy-950">{{ $h['critical']['title'] }}</p>
                        <p class="mt-0.5 truncate text-[11px] text-muted">{{ $h['critical']['where'] }} · due {{ $h['critical']['due'] }}</p>
                        <a href="{{ route('events.hub', [$spotlight, 'tab' => $h['critical']['tab']]) }}"
                           class="mt-2.5 flex h-9 items-center justify-center rounded-xl bg-navy-950 text-[11.5px] font-bold text-white transition hover:bg-navy-800">{{ $h['critical']['cta'] }} →</a>
                    @else
                        <p class="pf mt-1.5 text-[13.5px] font-bold text-navy-950">Nothing is waiting on you</p>
                        <a href="{{ route('events.hub', $spotlight) }}"
                           class="mt-2.5 flex h-9 items-center justify-center rounded-xl bg-navy-50 text-[11.5px] font-bold text-navy-700 transition hover:bg-navy-100">Open command center →</a>
                    @endif
                </div>
            </div>
        </div>
    @elseif ($events->isEmpty())
        {{-- ══════════ FRESH WORKSPACE ══════════
             With nothing booked yet there is no spotlight to show — the hero
             used to just vanish, dropping straight into a wall of zeroes below.
             Same band, same tokens, but it explains what this page becomes
             once an event exists instead of looking like a page that broke. --}}
        <div class="relative isolate -mx-4 -mt-1 overflow-hidden bg-navy-950 lg:-mx-6">
            <svg class="pointer-events-none absolute -right-16 -top-24 h-[420px] w-[420px] opacity-[0.14]" viewBox="0 0 100 100" aria-hidden="true">
                <circle cx="50" cy="50" r="46" fill="none" stroke="var(--color-gold-400)" stroke-width="0.4" />
                <circle cx="50" cy="50" r="34" fill="none" stroke="var(--color-gold-400)" stroke-width="0.4" stroke-dasharray="2 3" />
                <circle cx="50" cy="50" r="22" fill="none" stroke="var(--color-gold-400)" stroke-width="0.4" />
            </svg>
            <div class="flex flex-wrap items-center gap-x-8 gap-y-5 px-4 py-7 lg:px-6">
                <div class="min-w-[260px] flex-1">
                    <p class="flex items-center gap-2 text-eyebrow font-bold uppercase tracking-[0.24em] text-gold-300/90">
                        <span class="h-px w-4 bg-gold-400"></span>Welcome to Elite Business Hub
                    </p>
                    <h1 class="pf mt-2 max-w-[34ch] text-[26px] font-black leading-[1.15] text-white lg:text-[30px]">
                        Nothing on the book yet — this is where it'll surface once there is.
                    </h1>
                    <p class="mt-2 max-w-[52ch] text-[12.5px] text-white/60">
                        Once you add an event, this page scans it every day and puts what needs
                        you — overdue items, approvals waiting, money, risk — right here.
                    </p>
                </div>
                <a href="{{ route('events.create') }}" class="btn-gold shrink-0">＋ Create your first event</a>
            </div>
        </div>
    @endif

    {{-- who is reading this, and what the day looks like --}}
    <div class="flex flex-wrap items-baseline gap-x-3 gap-y-1 pt-1">
        <h2 class="pf text-[22px] font-black text-navy-950">{{ $greeting }}, {{ str($user->name)->before(' ') }}</h2>
        <p class="text-[12.5px] text-muted">{{ $now->format('l, j F Y') }} · {{ $headline }}</p>
        <a href="{{ route('ai.index') }}" class="ms-auto text-[12px] font-semibold text-navy-500 transition hover:text-navy-900">Open the briefing →</a>
    </div>

    {{-- ══════════ THE INSTRUMENT PANEL ══════════
         Every other page on the platform opens with the same flat figure strip
         — five coloured tiles with a number each. This is the one page whose
         whole job is "what does right now look like across everything", so its
         headline reads off dials, not tiles: a needle position says "how much
         of the book" before the digit underneath confirms it. "In the book" is
         always full — it is the panel's own reference circle, not a metric. --}}
    <div class="relative isolate overflow-hidden rounded-[20px] bg-navy-950 shadow-[0_20px_50px_-30px_rgba(11,31,58,0.55)]">
        <p class="flex items-center gap-2 px-4 pt-4 text-eyebrow font-bold uppercase tracking-[0.22em] text-white/30 lg:px-5">Instrument panel</p>
        <div class="grid grid-cols-2 divide-x divide-y divide-white/[0.05] sm:grid-cols-3 sm:divide-y-0 lg:grid-cols-5">
            @foreach ($figures as $f)
                @php $tag = ($f['href'] ?? null) ? 'a' : 'div'; @endphp
                <{{ $tag }} @if ($f['href'] ?? null) href="{{ $f['href'] }}" @endif
                   class="group flex flex-col items-center gap-2.5 px-3 py-5 text-center transition {{ ($f['href'] ?? null) ? 'hover:bg-white/[0.035]' : '' }}">
                    <div class="relative h-[72px] w-[72px] shrink-0">
                        <svg class="h-full w-full -rotate-90 transition group-hover:scale-[1.04]" viewBox="0 0 78 78" aria-hidden="true">
                            <circle cx="39" cy="39" r="{{ $ringR }}" fill="none" stroke="rgba(255,255,255,.08)" stroke-width="5" />
                            <circle cx="39" cy="39" r="{{ $ringR }}" fill="none" stroke="{{ $f['hex'] }}" stroke-width="5" stroke-linecap="round"
                                    stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * $f['pct'] / 100) }}" />
                        </svg>
                        <span class="absolute inset-0 grid place-items-center">
                            <x-icon :name="$f['icon']" class="h-3.5 w-3.5" style="color: {{ $f['hex'] }}" />
                        </span>
                        <span class="pf absolute -bottom-1 left-1/2 -translate-x-1/2 rounded-full bg-navy-950 px-1.5 text-[15px] font-black leading-tight text-white ring-1 ring-white/10">{{ $f['value'] }}</span>
                    </div>
                    <p class="mt-1.5 text-[10.5px] font-bold uppercase tracking-[0.08em] text-white/70">{{ $f['label'] }}</p>
                    <p class="truncate text-[9.5px] text-white/35">{{ $f['note'] }}</p>
                </{{ $tag }}>
            @endforeach
        </div>
    </div>

    <div class="grid gap-4 2xl:grid-cols-[minmax(0,1fr)_324px]">
        <div class="min-w-0 space-y-4">

            {{-- ══════════ TODAY ══════════
                 Every module's share of one day, in one line. Nothing else on
                 the platform crosses the modules like this. --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                    <h3 class="pf text-[15px] font-bold text-navy-950">Today</h3>
                    <p class="text-[11.5px] text-muted">{{ $now->format('l, j F') }} — across every event.</p>
                    @if ($today['load'] === 0)
                        <span class="ms-auto chip">Clear</span>
                    @endif
                </div>

                <div class="grid grid-cols-2 divide-x divide-y divide-line sm:grid-cols-4 sm:divide-y-0">
                    @foreach ([
                        ['Sessions', $today['sessions'], 'On stage today', 'calendar', route('events.index'), \App\Models\Event::moduleColor('agenda')],
                        ['Movements', $today['movements'], 'Cars and coaches', 'truck', route('events.index'), \App\Models\Event::moduleColor('transportation')],
                        ['Arrivals', $today['arrivals'], 'People landing', 'users', route('events.index'), \App\Models\Event::moduleColor('attendees')],
                        ['Due today', $today['tasks'], 'Tasks dated now', 'clipboard', route('tasks.index'), \App\Models\Event::moduleColor('tasks')],
                    ] as [$label, $value, $note, $icon, $href, $hex])
                        <a href="{{ $href }}" class="flex items-start gap-2.5 px-4 py-3.5 transition hover:bg-page/60">
                            <span class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-xl" style="color: {{ $hex }}; background: {{ $hex }}15">
                                <x-icon :name="$icon" class="h-4 w-4" />
                            </span>
                            <span class="min-w-0">
                                <span class="block text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">{{ $label }}</span>
                                <span class="pf mt-1 block text-[22px] font-black leading-none {{ $value ? 'text-navy-950' : 'text-navy-200' }}">{{ $value }}</span>
                                <span class="mt-1 block truncate text-[10.5px] text-muted">{{ $note }}</span>
                            </span>
                        </a>
                    @endforeach
                </div>
            </div>

            {{-- ══════════ THE WEEK ══════════
                 Seven days, each carrying its own load, so nothing lands as a
                 surprise on the morning it happens. --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                    <h3 class="pf text-[15px] font-bold text-navy-950">The week ahead</h3>
                    <p class="text-[11.5px] text-muted">Sessions, movements and deadlines, day by day.</p>
                </div>

                @php
                    $peak = max(1, $week->max('load'));
                    $peakLoad = $week->max('load');
                    // Module colours, not chart-library defaults — the same key
                    // that lights up a dot in the hub's own nav, so "movements"
                    // means the same amber everywhere it appears on the platform.
                    $series = [
                        ['sessions', \App\Models\Event::moduleColor('agenda'), 'Sessions'],
                        ['movements', \App\Models\Event::moduleColor('transportation'), 'Movements'],
                        ['tasks', \App\Models\Event::moduleColor('tasks'), 'Deadlines'],
                    ];
                @endphp
                <div class="grid grid-cols-7 divide-x divide-line">
                    @foreach ($week as $day)
                        <div @class(['relative flex flex-col px-2 py-3', 'bg-gold-50/50' => $day['today'], 'bg-gold-50/25' => $peakLoad > 0 && $day['load'] === $peakLoad && ! $day['today']])>
                            <p class="text-center text-eyebrow font-bold uppercase tracking-[0.1em] {{ $day['today'] ? 'text-gold-700' : 'text-navy-400' }}">{{ $day['date']->format('D') }}</p>
                            <p class="mt-0.5 flex items-center justify-center gap-1 text-center text-[15px] font-black {{ $day['today'] ? 'text-gold-700' : 'text-navy-900' }}">
                                {{ $day['date']->format('j') }}
                                @if ($peakLoad > 0 && $day['load'] === $peakLoad)
                                    <x-icon name="sparkles" class="h-2.5 w-2.5 text-gold-500" title="Busiest day this week" />
                                @endif
                            </p>

                            {{-- the day's load, drawn against the busiest day of the seven --}}
                            <div class="mx-auto mt-2.5 flex h-16 w-full items-end justify-center gap-[3px]">
                                @foreach ($series as [$key, $hex, $label])
                                    <span class="w-[7px] rounded-t-sm {{ ! $day[$key] ? 'bg-navy-50' : '' }}"
                                          style="height: {{ $day[$key] ? max(8, round($day[$key] / $peak * 64)) : 4 }}px; {{ $day[$key] ? 'background:'.$hex : '' }}"
                                          title="{{ $day[$key] }} {{ $label }}"></span>
                                @endforeach
                            </div>

                            <p class="mt-2 text-center text-[10px] font-bold tabular-nums {{ $day['load'] ? 'text-navy-700' : 'text-navy-200' }}">{{ $day['load'] ?: '—' }}</p>

                            @foreach ($day['starting'] as $e)
                                <a href="{{ route('events.hub', $e) }}" class="mt-1.5 flex items-center gap-1 truncate rounded bg-emerald-50 px-1 py-0.5 text-[8.5px] font-bold text-emerald-700" title="{{ $e->name }} starts">
                                    <span class="h-0 w-0 shrink-0 border-y-[3.5px] border-l-[5px] border-y-transparent border-l-emerald-500"></span>{{ str($e->name)->limit(8, '') }}
                                </a>
                            @endforeach
                            @foreach ($day['ending'] as $e)
                                <a href="{{ route('events.hub', $e) }}" class="mt-1.5 flex items-center gap-1 truncate rounded bg-navy-50 px-1 py-0.5 text-[8.5px] font-bold text-navy-600" title="{{ $e->name }} ends">
                                    <x-icon name="flag" class="h-2 w-2 shrink-0" />{{ str($e->name)->limit(8, '') }}
                                </a>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-4 py-2.5">
                    @foreach ($series as [$key, $hex, $label])
                        <span class="flex items-center gap-1.5 text-[10.5px] text-muted"><span class="h-2 w-2 rounded-sm" style="background: {{ $hex }}"></span>{{ $label }}</span>
                    @endforeach
                    <span class="ms-auto text-[10.5px] italic text-navy-300">bars are scaled to the busiest day of the seven</span>
                </div>
            </div>

            {{-- ══════════ THE BOOK ══════════ --}}
            <div class="card overflow-hidden">
                <div class="flex flex-wrap items-baseline gap-x-3 border-b border-line px-4 py-3">
                    <h3 class="pf text-[15px] font-bold text-navy-950">The book</h3>
                    <p class="text-[11.5px] text-muted">Every event, by the stage it is in.</p>
                    <a href="{{ route('events.index') }}" class="ms-auto text-[11.5px] font-semibold text-navy-500 transition hover:text-navy-900">Open the journey →</a>
                </div>

                <div class="space-y-2 px-4 py-3.5">
                    @php $stageTotal = collect($stages)->sum('count'); @endphp
                    @if ($stageTotal === 0)
                        <p class="px-1 py-2 text-[11.5px] text-muted">No events in the book yet — stages will fill in as events move through them.</p>
                    @else
                        @foreach ($stages as $stage)
                            @continue (! $stage['count'])
                            <a href="{{ route('events.index', ['stage' => $stage['key'] ?? null]) }}" class="flex items-center gap-2.5 rounded-lg px-1 py-0.5 transition hover:bg-page/60">
                                <span class="w-28 shrink-0 truncate text-[11.5px] font-semibold text-navy-700">{{ $stage['label'] }}</span>
                                <span class="h-2.5 flex-1 overflow-hidden rounded-full bg-navy-50">
                                    <span class="block h-full rounded-full" style="width: {{ round($stage['count'] / $stageTotal * 100) }}%; background: {{ $stage['hex'] ?? 'var(--color-navy-400)' }}"></span>
                                </span>
                                <span class="w-6 shrink-0 text-right text-[11.5px] font-bold tabular-nums text-navy-900">{{ $stage['count'] }}</span>
                            </a>
                        @endforeach
                    @endif
                </div>

                <div class="grid grid-cols-2 divide-x divide-line border-t border-line sm:grid-cols-4">
                    @foreach ([
                        ['Contracted', \App\Livewire\EventsIndex::shortMoney($money['income'], $money['currency'])],
                        ['Cost', \App\Livewire\EventsIndex::shortMoney($money['cost'], $money['currency'])],
                        ['Net', \App\Livewire\EventsIndex::shortMoney($money['net'], $money['currency'])],
                        ['Margin', $money['pricedMargin'] === null ? '—' : $money['pricedMargin'].'%'],
                    ] as [$label, $value])
                        <a href="{{ route('finance.index') }}" class="px-4 py-3 transition hover:bg-page/60">
                            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">{{ $label }}</p>
                            <p class="pf mt-1 text-[17px] font-black leading-none text-navy-950">{{ $value }}</p>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- ══════════ THE RAIL ══════════ --}}
        <aside class="space-y-4">
            <div class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-line px-4 py-3">
                    <h3 class="pf text-[14px] font-bold text-navy-950">Signals</h3>
                    <a href="{{ route('ai.index') }}" class="ms-auto text-[11px] font-semibold text-navy-400 transition hover:text-navy-900">All {{ $signalCount }} →</a>
                </div>
                <div class="divide-y divide-line">
                    {{-- Severity (critical/warning/info) already comes ranked worst-first
                         off the advisor; the row only used to spend it on a 2px dot. The
                         bar + ink pairing here reuses the platform's own danger/warning
                         status tokens (app.css) so "what's on fire" reads before you've
                         read a single word, and "why" — already computed, never shown —
                         gives the fact that makes it urgent instead of just the label. --}}
                    @forelse ($signals as $s)
                        @php
                            $sev = match ($s['tone']) {
                                'red' => ['bar' => 'bg-danger', 'dot' => 'bg-danger', 'ink' => 'text-danger-ink'],
                                'amber' => ['bar' => 'bg-warning', 'dot' => 'bg-warning', 'ink' => 'text-warning-ink'],
                                default => ['bar' => null, 'dot' => 'bg-navy-300', 'ink' => 'text-navy-900'],
                            };
                        @endphp
                        <a href="{{ $s['href'] }}" class="relative flex items-start gap-2.5 px-4 py-2.5 transition hover:bg-page/60">
                            @if ($sev['bar'])
                                <span class="absolute inset-y-0 left-0 w-[3px] {{ $sev['bar'] }}"></span>
                            @endif
                            <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full {{ $sev['dot'] }}"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] font-bold {{ $sev['ink'] }}">{{ $s['title'] }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $s['where'] }}</span>
                                @if ($s['why'] ?? null)
                                    <span class="mt-0.5 block truncate text-[10px] text-navy-400">{{ $s['why'] }}</span>
                                @endif
                            </span>
                        </a>
                    @empty
                        <div class="px-4 py-7 text-center">
                            <x-icon name="check" class="mx-auto h-6 w-6 text-success" />
                            <p class="mt-2 text-[11.5px] font-semibold text-navy-700">Nothing flagged anywhere.</p>
                            <p class="mt-0.5 text-[10.5px] text-muted">The advisor scans every open event — this is what a clean book looks like.</p>
                        </div>
                    @endforelse
                </div>
            </div>

            <div class="card overflow-hidden">
                <div class="border-b border-line px-4 py-3">
                    <h3 class="pf text-[14px] font-bold text-navy-950">The floor</h3>
                    <p class="text-[11px] text-muted">Every event, nearest first.</p>
                </div>
                <div class="divide-y divide-line">
                    @forelse ($events->sortBy('starts_at')->take(6) as $e)
                        <a href="{{ route('events.hub', $e) }}" class="flex items-center gap-2.5 px-3 py-2.5 transition hover:bg-page/60">
                            <span class="relative h-10 w-10 shrink-0 overflow-hidden rounded-xl ring-1 ring-navy-900/[0.06]">
                                @if ($e->coverUrl())
                                    <img src="{{ $e->coverUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <x-event-crest :event="$e" class="h-full w-full" />
                                @endif
                                <span class="absolute inset-0 rounded-xl shadow-[inset_0_0_0_1px_rgba(255,255,255,.15)]" aria-hidden="true"></span>
                            </span>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate text-[12px] font-bold text-navy-900">{{ $e->name }}</span>
                                <span class="block truncate text-[10.5px] text-muted">{{ $e->starts_at?->format('j M Y') ?? 'No dates' }}</span>
                            </span>
                            <x-health-ring :percent="$e->progress" :group="$e->healthGroup()" size="h-7 w-7" :label="false" class="shrink-0" />
                        </a>
                    @empty
                        <p class="px-4 py-6 text-center text-[11.5px] text-muted">No events yet — once you add one, it'll show up here first.</p>
                    @endforelse
                </div>
                @if ($events->isNotEmpty())
                    <a href="{{ route('events.index') }}" class="block border-t border-line px-4 py-2.5 text-center text-[11.5px] font-semibold text-navy-500 transition hover:text-gold-700">All {{ $events->count() }} events →</a>
                @endif
            </div>
        </aside>
    </div>
</div>
