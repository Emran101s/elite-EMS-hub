<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Navigation concept — Elite Business Hub</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=playfair-display:600,700,800&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

{{--
    ── Navigation concept ──────────────────────────────────────────────────
    Two tiers, from the reference: a narrow rail that says which AREA you are
    in, and a light panel that says what is inside it. Everything floats on the
    page background as its own rounded plane, which is what gives the reference
    its depth — nothing is flush to an edge.

    In our palette rather than the reference's grey: navy for the rail, gold for
    what is selected, and the panel light so the dark stays a 56px accent
    instead of a wall. Self-contained; no real screen imports this.
--}}

<body class="min-h-screen bg-page font-sans text-ink antialiased">
<div class="flex h-screen gap-3 overflow-hidden p-3 sm:gap-4 sm:p-4">

    {{-- ══════════════════════════════════════════════════════════════════
         THE RAIL — which area
         Two capsules with a gap, as in the reference: the areas float
         together, and the one thing that is not an area sits apart.
    ══════════════════════════════════════════════════════════════════ --}}
    <div class="flex shrink-0 flex-col gap-3">
        <nav class="flex min-h-0 flex-1 flex-col items-center gap-1 rounded-[22px] bg-navy-900 p-2.5 shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)]"
             aria-label="Areas">

            {{-- brand --}}
            <a href="{{ route('home') }}" class="mb-1 grid h-11 w-11 place-items-center rounded-2xl" title="Elite Business Hub">
                <span class="block h-3.5 w-3.5 rotate-45 rounded-[2px] border-2 border-gold-400"></span>
            </a>
            <span class="mb-2 h-px w-6 bg-white/10"></span>

            @foreach ([
                ['home', 'Command Center', true],
                ['calendar', 'Events', false],
                ['clipboard', 'Tasks', false],
                ['share', 'CRM', false],
                ['currency', 'Finance', false],
                ['archive', 'Library', false],
            ] as [$icon, $label, $active])
                <button type="button" title="{{ $label }}"
                        @class([
                            'group relative grid h-11 w-11 place-items-center rounded-2xl transition',
                            'bg-white/10 text-white' => $active,
                            'text-white/45 hover:bg-white/[0.07] hover:text-white/85' => ! $active,
                        ])>
                    {{-- The gold tick is the only colour on the rail, so where
                         you are reads before you have finished looking. --}}
                    @if ($active)
                        <span class="absolute -left-2.5 h-5 w-1 rounded-full bg-gold-400"></span>
                    @endif
                    <x-icon :name="$icon" class="h-[18px] w-[18px]" />

                    {{-- flyout label, since the rail is icons only --}}
                    <span class="pointer-events-none absolute left-full z-50 ml-3 hidden whitespace-nowrap rounded-lg bg-navy-900 px-2.5 py-1 text-[11px] font-semibold text-white shadow-lg group-hover:block">
                        {{ $label }}
                    </span>
                </button>
            @endforeach

            {{-- The rail's own name, set sideways — the reference's quiet
                 signature down the edge of the chrome. --}}
            <span class="mt-auto pb-1 text-[8.5px] font-bold uppercase tracking-[0.3em] text-white/20 [writing-mode:vertical-rl]">
                Elite · Nav
            </span>
        </nav>

        <button type="button" title="Settings"
                class="grid h-12 w-[60px] place-items-center rounded-[22px] bg-navy-900 text-white/60 shadow-[0_18px_40px_-24px_rgba(11,31,58,0.75)] transition hover:text-white">
            <x-icon name="cog" class="h-[18px] w-[18px]" />
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════
         THE PANEL — what is inside the area
    ══════════════════════════════════════════════════════════════════ --}}
    <aside class="flex w-[302px] shrink-0 flex-col overflow-hidden rounded-[22px] border border-line bg-white shadow-[0_20px_50px_-32px_rgba(11,31,58,0.45)]">

        {{-- ── who you are ── --}}
        <button type="button" class="flex items-center gap-3 px-4 py-4 text-left transition hover:bg-page/60">
            <span class="grid h-10 w-10 shrink-0 place-items-center overflow-hidden rounded-full bg-navy-900 text-[13px] font-bold text-gold-400">
                {{ str($user?->name ?? 'EB')->explode(' ')->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode('') }}
            </span>
            <span class="min-w-0 flex-1">
                <span class="flex items-center gap-1">
                    <span class="truncate text-[13.5px] font-bold text-navy-900">{{ $user?->name ?? 'Elite Business Hub' }}</span>
                    <x-icon name="chevron" class="h-3 w-3 shrink-0 text-navy-300" />
                </span>
                <span class="block truncate text-[11px] text-muted">{{ $user?->email }}</span>
            </span>
        </button>

        <div class="scrollbar-none min-h-0 flex-1 overflow-y-auto px-2.5 pb-3">

            {{-- ── Workspace ── --}}
            <p class="px-2 pb-1.5 pt-1 text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">Workspace</p>

            @foreach ([
                ['grid', 'Dashboard', null, true],
                ['calendar', 'Events', $eventCount, false],
                ['clipboard', 'Tasks', $openTasks, false],
                ['share', 'Shared with me', null, false],
            ] as [$icon, $label, $count, $active])
                <button type="button"
                        @class([
                            'flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left transition',
                            // The selected row lifts off the panel rather than
                            // filling with colour — the reference's trick, and
                            // it keeps the panel calm.
                            'bg-page shadow-[0_1px_2px_rgba(11,31,58,0.06),inset_0_0_0_1px_rgba(255,255,255,0.9)] ring-1 ring-line' => $active,
                            'hover:bg-page/60' => ! $active,
                        ])>
                    <x-icon :name="$icon" class="h-4 w-4 shrink-0 {{ $active ? 'text-gold-600' : 'text-navy-300' }}" />
                    <span class="min-w-0 flex-1 truncate text-[12.5px] {{ $active ? 'font-bold text-navy-900' : 'font-medium text-navy-700' }}">{{ $label }}</span>
                    @isset($count)
                        <span class="shrink-0 text-[11px] font-semibold tabular-nums text-navy-300">{{ $count }}</span>
                    @endisset
                </button>
            @endforeach

            <div class="my-2.5 h-px bg-line"></div>

            {{-- ── Pipeline ── --}}
            <p class="px-2 pb-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">Pipeline</p>

            @foreach ($pipeline as $stage)
                <button type="button" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left transition hover:bg-page/60">
                    <span class="grid h-4 w-4 shrink-0 place-items-center">
                        <span class="h-2 w-2 rounded-full ring-2 ring-inset" style="color: {{ $stage['color'] }}; background: {{ $stage['color'] }}22; --tw-ring-color: {{ $stage['color'] }}"></span>
                    </span>
                    <span class="min-w-0 flex-1 truncate text-[12.5px] font-medium text-navy-700">{{ $stage['label'] }}</span>
                    <span class="shrink-0 text-[11px] font-semibold tabular-nums text-navy-300">{{ $stage['count'] }}</span>
                </button>
            @endforeach

            <div class="my-2.5 h-px bg-line"></div>

            {{-- ── History ── --}}
            <p class="px-2 pb-1.5 text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">History</p>

            <button type="button" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left transition hover:bg-page/60">
                <x-icon name="bell" class="h-4 w-4 shrink-0 text-navy-300" />
                <span class="min-w-0 flex-1 truncate text-[12.5px] font-medium text-navy-700">Recently edited</span>
                <span class="shrink-0 text-[11px] font-semibold tabular-nums text-navy-300">{{ $recent->count() }}</span>
            </button>
            <button type="button" class="flex w-full items-center gap-2.5 rounded-xl px-2.5 py-2 text-left transition hover:bg-page/60">
                <x-icon name="archive" class="h-4 w-4 shrink-0 text-navy-300" />
                <span class="min-w-0 flex-1 truncate text-[12.5px] font-medium text-navy-700">Archive</span>
                <span class="shrink-0 text-[11px] font-semibold tabular-nums text-navy-300">{{ $archived }}</span>
            </button>

            <div class="my-2.5 h-px bg-line"></div>

            {{-- ── The portfolio, as a tree ── --}}
            <div class="flex items-center justify-between px-2 pb-2">
                <p class="text-[10px] font-bold uppercase tracking-[0.16em] text-navy-300">Portfolio</p>
                <button type="button" title="New event"
                        class="grid h-5 w-5 place-items-center rounded-full text-navy-300 transition hover:bg-gold-50 hover:text-gold-700">＋</button>
            </div>

            <label class="relative mb-2 block">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" placeholder="Search events…"
                       class="h-9 w-full rounded-xl border border-line bg-page/60 ps-9 text-[12px] text-navy-800 placeholder:text-navy-300 focus:border-gold-300 focus:bg-white focus:outline-none">
            </label>

            <div class="rounded-2xl border border-line bg-page/40 p-1.5">
                @forelse ($tree as $group)
                    <details class="group/branch" @if ($loop->first) open @endif>
                        <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-white [&::-webkit-details-marker]:hidden">
                            <span class="text-[9px] text-navy-300 transition group-open/branch:rotate-90">▶</span>
                            <span class="h-2 w-2 shrink-0 rounded-[3px]" style="background: {{ $group['color'] }}"></span>
                            <span class="min-w-0 flex-1 truncate text-[12px] font-bold text-navy-800">{{ $group['label'] }}</span>
                            <span class="shrink-0 text-[10.5px] font-semibold tabular-nums text-navy-300">{{ $group['events']->count() }}</span>
                        </summary>

                        {{-- The indent guide: one hairline the children hang off,
                             which is what stops a tree reading as a flat list. --}}
                        <div class="ms-[13px] border-s border-line ps-2">
                            @foreach ($group['events'] as $event)
                                <a href="{{ route('events.hub', $event) }}"
                                   class="flex items-center gap-2 rounded-lg px-2 py-1.5 transition hover:bg-white">
                                    <x-icon name="folder" class="h-3.5 w-3.5 shrink-0 text-navy-300" />
                                    <span class="min-w-0 flex-1">
                                        <span class="block truncate text-[12px] font-medium text-navy-700">{{ $event->name }}</span>
                                        <span class="block truncate text-[10px] text-navy-300">{{ $event->client?->name ?? 'No client' }}</span>
                                    </span>
                                    @if ($event->open_tasks)
                                        <span class="shrink-0 text-[10.5px] font-semibold tabular-nums text-navy-300">{{ $event->open_tasks }}</span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </details>
                @empty
                    <p class="px-2 py-6 text-center text-[11.5px] text-muted">No events yet.</p>
                @endforelse
            </div>
        </div>
    </aside>

    {{-- ══════════════════════════════════════════════════════════════════
         What the nav is for. Enough of a page to judge the chrome against.
    ══════════════════════════════════════════════════════════════════ --}}
    <main class="scrollbar-none min-w-0 flex-1 overflow-y-auto rounded-[22px] bg-navy-900/[0.045] p-5 sm:p-7">
        <p class="text-[10px] font-bold uppercase tracking-[0.22em] text-gold-600">Concept</p>
        <h1 class="pf mt-1.5 text-[32px] font-bold leading-tight text-navy-900">Two-tier navigation</h1>
        <p class="mt-1.5 max-w-[62ch] text-[13px] leading-relaxed text-muted">
            The rail says which area you are in; the panel says what is inside it. Only the rail is dark, so the
            screen still gets one dark accent rather than a wall — and the panel is free to carry counts, a
            search and the portfolio without competing with the work.
        </p>

        <div class="mt-6 grid gap-3 sm:grid-cols-3">
            @foreach ([
                ['56px', 'of dark', 'against 292px for the panel that came out'],
                [$eventCount, 'live events', 'in the tree, grouped by stage'],
                [$openTasks, 'open tasks', 'counted where you would look for them'],
            ] as [$figure, $label, $note])
                <div class="rounded-2xl border border-line bg-white p-4">
                    <p class="pf text-[26px] font-bold leading-none text-navy-900">{{ $figure }}</p>
                    <p class="mt-1.5 text-[11px] font-bold uppercase tracking-[0.14em] text-navy-400">{{ $label }}</p>
                    <p class="mt-1 text-[11.5px] leading-snug text-muted">{{ $note }}</p>
                </div>
            @endforeach
        </div>

        <div class="mt-6 rounded-2xl border border-dashed border-line p-6">
            <p class="text-[12.5px] font-semibold text-navy-700">What changes if this is adopted</p>
            <ul class="mt-2 space-y-1.5 text-[12px] leading-relaxed text-muted">
                <li>· The top pill row goes; areas move to the rail and their contents to the panel.</li>
                <li>· Each area brings its own panel — Events shows the portfolio, Finance shows the book.</li>
                <li>· Everything currently under <b>More</b> becomes a rail icon, so nothing hides behind a menu.</li>
            </ul>
        </div>
    </main>
</div>
</body>
</html>
