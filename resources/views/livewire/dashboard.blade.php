@php
    $user = auth()->user();
    $hour = (int) $now->copy()->setTimeFrom(now())->format('H');
    $greeting = match (true) { $hour < 12 => 'Good morning', $hour < 18 => 'Good afternoon', default => 'Good evening' };
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

                {{-- the two numbers that matter about it --}}
                <div class="flex shrink-0 items-center gap-3">
                    <div class="flex h-[74px] min-w-[92px] flex-col justify-center rounded-2xl bg-black/25 px-3 text-center ring-1 ring-white/10">
                        <span class="pf text-[24px] font-black leading-none text-gold-400">
                            {{ $spotlightLive ? 'Day '.((int) $spotlight->starts_at->diffInDays($now) + 1) : ($days === null ? '—' : $days.'d') }}
                        </span>
                        <span class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-white/50">{{ $spotlightLive ? 'Of the run' : 'To go' }}</span>
                    </div>

                    <div class="flex h-[74px] min-w-[92px] flex-col justify-center rounded-2xl bg-black/25 px-3 text-center ring-1 ring-white/10"
                         title="{{ collect($ready['gates'])->map(fn ($g) => ($g['met'] ? '✓ ' : '✗ ').$g['label'])->implode(chr(10)) }}">
                        <span class="pf text-[24px] font-black leading-none {{ $ready['pct'] >= 70 ? 'text-emerald-400' : ($ready['pct'] >= 40 ? 'text-amber-400' : 'text-red-400') }}">{{ $ready['pct'] }}%</span>
                        <span class="mt-1 text-eyebrow font-bold uppercase tracking-wider text-white/50">Ready</span>
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

    <x-figure-strip :figures="$figures" />

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
                        ['Sessions', $today['sessions'], 'On stage today', 'calendar', route('events.index')],
                        ['Movements', $today['movements'], 'Cars and coaches', 'truck', route('events.index')],
                        ['Arrivals', $today['arrivals'], 'People landing', 'users', route('events.index')],
                        ['Due today', $today['tasks'], 'Tasks dated now', 'clipboard', route('tasks.index')],
                    ] as [$label, $value, $note, $icon, $href])
                        <a href="{{ $href }}" class="px-4 py-3.5 transition hover:bg-page/60">
                            <p class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-navy-400">
                                <x-icon :name="$icon" class="h-3.5 w-3.5 text-navy-300" />{{ $label }}
                            </p>
                            <p class="pf mt-1.5 text-[24px] font-black leading-none {{ $value ? 'text-navy-950' : 'text-navy-200' }}">{{ $value }}</p>
                            <p class="mt-1 truncate text-[10.5px] text-muted">{{ $note }}</p>
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

                @php $peak = max(1, $week->max('load')); @endphp
                <div class="grid grid-cols-7 divide-x divide-line">
                    @foreach ($week as $day)
                        <div @class(['flex flex-col px-2 py-3', 'bg-gold-50/50' => $day['today']])>
                            <p class="text-center text-eyebrow font-bold uppercase tracking-[0.1em] {{ $day['today'] ? 'text-gold-700' : 'text-navy-400' }}">{{ $day['date']->format('D') }}</p>
                            <p class="mt-0.5 text-center text-[15px] font-black {{ $day['today'] ? 'text-gold-700' : 'text-navy-900' }}">{{ $day['date']->format('j') }}</p>

                            {{-- the day's load, drawn against the busiest day of the seven --}}
                            <div class="mx-auto mt-2.5 flex h-16 w-full items-end justify-center gap-[3px]">
                                @foreach ([['sessions', 'bg-blue-500'], ['movements', 'bg-violet-500'], ['tasks', 'bg-amber-500']] as [$key, $tone])
                                    <span class="w-[7px] rounded-t-sm {{ $day[$key] ? $tone : 'bg-navy-50' }}"
                                          style="height: {{ $day[$key] ? max(8, round($day[$key] / $peak * 64)) : 4 }}px"
                                          title="{{ $day[$key] }} {{ $key }}"></span>
                                @endforeach
                            </div>

                            <p class="mt-2 text-center text-[10px] font-bold tabular-nums {{ $day['load'] ? 'text-navy-700' : 'text-navy-200' }}">{{ $day['load'] ?: '—' }}</p>

                            @foreach ($day['starting'] as $e)
                                <a href="{{ route('events.hub', $e) }}" class="mt-1.5 block truncate rounded bg-emerald-100 px-1 py-0.5 text-center text-[8.5px] font-bold text-emerald-700" title="{{ $e->name }} starts">▶ {{ str($e->name)->limit(9, '') }}</a>
                            @endforeach
                            @foreach ($day['ending'] as $e)
                                <a href="{{ route('events.hub', $e) }}" class="mt-1.5 block truncate rounded bg-navy-50 px-1 py-0.5 text-center text-[8.5px] font-bold text-navy-600" title="{{ $e->name }} ends">■ {{ str($e->name)->limit(9, '') }}</a>
                            @endforeach
                        </div>
                    @endforeach
                </div>

                <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-4 py-2.5">
                    @foreach ([['bg-blue-500', 'Sessions'], ['bg-violet-500', 'Movements'], ['bg-amber-500', 'Deadlines']] as [$tone, $label])
                        <span class="flex items-center gap-1.5 text-[10.5px] text-muted"><span class="h-2 w-2 rounded-sm {{ $tone }}"></span>{{ $label }}</span>
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
                            <span class="h-9 w-12 shrink-0 overflow-hidden rounded-lg bg-navy-50">
                                @if ($e->coverUrl())
                                    <img src="{{ $e->coverUrl() }}" alt="" class="h-full w-full object-cover">
                                @else
                                    <x-event-crest :event="$e" class="h-full w-full" />
                                @endif
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
