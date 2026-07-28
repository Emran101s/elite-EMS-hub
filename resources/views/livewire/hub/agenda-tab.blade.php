<div>
    @php
        $allSessions = $days->flatMap->sessions;
        $toMin = fn ($t) => (int) substr((string) $t, 0, 2) * 60 + (int) substr((string) $t, 3, 2);
        $totalSessions = $allSessions->count();
        $settled = $allSessions->filter(fn ($s) => $s->isSettled())->count();
        $flaggedCount = $allSessions->where('flagged', true)->count();
        $agendaHours = round($allSessions->sum(fn ($s) => max($toMin($s->ends_at) - $toMin($s->starts_at), 0)) / 60, 1);
        $speakerTotal = $allSessions->flatMap(fn ($s) => $s->speakers->pluck('id'))->unique()->count();
        $roomTotal = $allSessions->pluck('room.name')->filter()->unique()->count();
        $confPct = $totalSessions ? (int) round($settled / $totalSessions * 100) : 0;
        $unconfirmed = $totalSessions - $settled;
        $daySessions = $day?->sessions->count() ?? 0;
    @endphp

    {{-- ══════════ COMMAND HEADER ══════════
         The ring is how much of the whole agenda is confirmed; the four
         figures are the ones you would ask for out loud — how many sessions,
         how many of them are settled, how many rooms are in play, how many
         days. Each carries the second line that says which way it is going. --}}
    <x-module-head eyebrow="Agenda Command Center"
                   :ring="$confPct"
                   lead-note="Agenda complete"
                   :figures="[
                       ['Total sessions', $totalSessions, 'text-white', $daySessions.' on this day'],
                       ['Confirmed', $settled, 'text-white', $unconfirmed ? $unconfirmed.' still open' : 'all confirmed'],
                       ['Active venues', $roomTotal, 'text-white', $agendaHours.' hrs programmed'],
                       ['Event days', $days->count(), 'text-white', $speakerTotal.' '.str('speaker')->plural($speakerTotal).' billed'],
                   ]">
        <x-slot:actions>
            {{-- The two numbers that mean someone has work to do. --}}
            <div class="flex h-[52px] min-w-[5.4rem] flex-col justify-center rounded-xl bg-black/20 px-2.5 text-center ring-1 ring-white/10">
                <span class="text-lg font-black leading-none" style="color: {{ $unconfirmed > 0 ? 'var(--color-warning-on-dark)' : 'rgba(255,255,255,.4)' }}">{{ $unconfirmed }}</span>
                <span class="mt-0.5 text-eyebrow font-bold uppercase leading-tight tracking-wider text-white/50">Unconfirmed</span>
            </div>
            <div class="flex h-[52px] min-w-[5.4rem] flex-col justify-center rounded-xl bg-black/20 px-2.5 text-center ring-1 ring-white/10">
                <span class="text-lg font-black leading-none" style="color: {{ $flaggedCount > 0 ? 'var(--color-danger-on-dark)' : 'rgba(255,255,255,.4)' }}">{{ $flaggedCount }}</span>
                <span class="mt-0.5 text-eyebrow font-bold uppercase leading-tight tracking-wider text-white/50">Flagged</span>
            </div>
        </x-slot:actions>
    </x-module-head>

    {{-- Import panel --}}
    @if ($showImport)
        <form wire:submit="import" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="flex-1">
                <label class="field-label !mb-1 !text-eyebrow" for="import-file">CSV file — columns: title, type, start, end, room, speaker, moderator (separate several names with ; )</label>
                <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                @error('importFile') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
            <button type="submit" class="btn-navy h-10 px-5 text-xs" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
        </form>
    @endif

    @if ($days->isEmpty())
        <div class="card flex flex-col items-center px-8 py-16 text-center">
            <h3 class="pf text-base font-bold text-navy-900">No agenda days yet</h3>
            <p class="mt-1 max-w-md text-xs text-muted">Days are created from the event's date range — set the dates in Settings, or add one here.</p>
            <button type="button" wire:click="addDay" class="btn-gold mt-4 h-9 px-4 text-xs">＋ Add Day</button>
        </div>
    @else
        {{-- ══════════ THE BOARD ══════════
             The venue rail runs the full height on the left, because which
             room you are looking at outlives which day you are looking at.
             Everything that changes with the day — the day strip, the tools,
             the board itself — lives in the column beside it. --}}
        <div class="grid gap-3 xl:grid-cols-[216px_minmax(0,1fr)]">

            {{-- ── LEFT: view switch, then the rooms ── --}}
            <aside class="card flex flex-col self-start overflow-hidden">
                {{-- Room timeline ⇄ programme board. It sits over the venue list
                     because the list is what it swaps out. --}}
                <div class="flex border-b border-line">
                    @foreach ([['timeline', 'Timeline', 'chart'], ['program', 'Programme', 'calendar']] as [$key, $label, $icon])
                        <button type="button" wire:click="setView('{{ $key }}')"
                                @class([
                                    'relative flex flex-1 items-center justify-center gap-1.5 px-2 py-2.5 text-[11.5px] transition',
                                    'font-bold text-navy-900 after:absolute after:inset-x-3 after:-bottom-px after:h-[2.5px] after:rounded-full after:bg-gold-500' => $view === $key,
                                    'font-medium text-navy-400 hover:text-navy-700' => $view !== $key,
                                ])>
                            <x-icon :name="$icon" class="h-3.5 w-3.5" /> {{ $label }}
                        </button>
                    @endforeach
                </div>

                <div class="px-3.5 pb-2.5 pt-3">
                    <p class="eyebrow">Venues</p>
                    <p class="mt-0.5 text-[10.5px] text-muted">{{ $venues->count() }} {{ str('room')->plural($venues->count()) }} · {{ $venues->sum('sessions') }} booked today</p>

                    <div class="relative mt-2.5">
                        <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-navy-300" />
                        <input type="search" wire:model.live.debounce.250ms="venueSearch" placeholder="Search venues…"
                               aria-label="Search venues"
                               class="input h-8 w-full !rounded-lg !py-0 !ps-7 text-[11.5px]">
                    </div>
                </div>

                <div class="scrollbar-none max-h-[560px] min-h-0 flex-1 divide-y divide-line overflow-y-auto border-t border-line">
                    @forelse ($venues as $v)
                        @php
                            [$dot, $chip, $note] = match ($v['state']) {
                                'conflict' => ['bg-risk', 'bg-risk/10 text-red-700', $v['conflicts'].' '.str('conflict')->plural($v['conflicts'])],
                                'warning' => ['bg-warn', 'bg-warn/15 text-amber-700', $v['over'].' warning'],
                                default => ['bg-track', 'bg-track/10 text-emerald-700', 'No issues'],
                            };
                        @endphp
                        <div class="flex items-start gap-2.5 px-3 py-2.5">
                            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-xl bg-navy-50 text-navy-500">
                                <x-icon name="building" class="h-3.5 w-3.5" />
                            </span>
                            <div class="min-w-0 flex-1">
                                {{-- The name gets the whole line: a 216px rail that
                                     truncates every room to "Exhibition…" is a rail
                                     you cannot read. The verdict goes underneath. --}}
                                <p class="truncate text-[12px] font-bold text-navy-900">{{ $v['room']->name }}</p>
                                <p class="mt-0.5 text-[10px] text-muted">
                                    @if ($v['room']->capacity) Capacity {{ number_format($v['room']->capacity) }} · @endif
                                    {{ $v['sessions'] }} {{ str('session')->plural($v['sessions']) }}
                                </p>
                                <span class="mt-1 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $chip }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $note }}
                                </span>
                            </div>
                        </div>
                    @empty
                        <p class="px-3.5 py-6 text-center text-[11.5px] text-muted">
                            {{ trim($venueSearch) === '' ? 'No rooms yet. Add them on the Venue tab.' : 'No room matches “'.$venueSearch.'”.' }}
                        </p>
                    @endforelse
                </div>

                <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}"
                   class="border-t border-line px-3.5 py-2.5 text-center text-[11.5px] font-semibold text-navy-500 transition hover:text-gold-700">＋ Add Venue</a>
            </aside>

            {{-- ── RIGHT: the day, the tools, the board ── --}}
            <div class="min-w-0">

                {{-- ══ DAY STRIP ══
                     Each day carries how settled it is, so the strip says which
                     day still needs work rather than only which one you are
                     looking at. --}}
                <div class="scrollbar-none mb-3 flex items-stretch gap-2.5 overflow-x-auto pb-1">
                    @foreach ($dayCards as $card)
                        @php $d = $card['model']; $on = $day && $day->id === $d->id; $r = 2 * M_PI * 13; @endphp
                        <button type="button" wire:click="selectDay({{ $d->id }})"
                                @class([
                                    'flex w-[152px] shrink-0 items-center gap-2.5 rounded-2xl border px-3 py-2.5 text-left transition',
                                    'border-navy-900 bg-navy-900 text-white shadow-[0_10px_24px_-16px_rgba(11,31,58,0.9)]' => $on,
                                    'border-line bg-white text-navy-700 hover:border-navy-200' => ! $on,
                                ])>
                            <span class="min-w-0 flex-1">
                                <span class="block text-[9px] font-bold uppercase tracking-[0.18em] {{ $on ? 'text-gold-400' : 'text-navy-300' }}">Day {{ $loop->iteration }}</span>
                                <span class="mt-0.5 block truncate text-[12.5px] font-bold">{{ $d->date?->format('D, j M') ?? $d->label }}</span>
                                <span class="block text-[10px] {{ $on ? 'text-white/55' : 'text-muted' }}">{{ $card['sessions'] }} {{ str('session')->plural($card['sessions']) }}</span>
                            </span>

                            <span class="relative grid h-9 w-9 shrink-0 place-items-center">
                                <svg class="h-9 w-9 -rotate-90" viewBox="0 0 30 30" aria-hidden="true">
                                    <circle cx="15" cy="15" r="13" fill="none" stroke="{{ $on ? 'rgba(255,255,255,.14)' : 'var(--color-navy-100)' }}" stroke-width="3" />
                                    <circle cx="15" cy="15" r="13" fill="none" stroke="var(--color-gold-500)" stroke-width="3" stroke-linecap="round"
                                            stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $r - ($r * $card['pct'] / 100) }}" />
                                </svg>
                                <span class="absolute text-[9px] font-black {{ $on ? 'text-white' : 'text-navy-700' }}">{{ $card['pct'] }}%</span>
                            </span>
                        </button>
                    @endforeach

                    <button type="button" wire:click="addDay"
                            class="flex w-[92px] shrink-0 items-center justify-center gap-1 rounded-2xl border border-dashed border-line text-[11.5px] font-semibold text-navy-400 transition hover:border-gold-300 hover:text-gold-600">
                        ＋ Add Day
                    </button>
                </div>

                {{-- ══ TOOLS ══ what you do TO the day, beside the day you picked --}}
                <div class="mb-3 flex flex-wrap items-center justify-end gap-2">
                    @if ($view === 'program')
                        {{-- Who the programme is for: ops sees build and press time, delegates don't. --}}
                        <div class="mr-auto flex rounded-xl border border-line bg-white p-0.5 shadow-sm" title="Public hides setup, press and registration">
                            <button type="button" wire:click="setAudience('internal')"
                                    @class(['rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $audience === 'internal', 'text-navy-400 hover:text-navy-700' => $audience !== 'internal'])>Internal</button>
                            <button type="button" wire:click="setAudience('public')"
                                    @class(['rounded-lg px-3 py-1.5 text-micro font-bold transition', 'bg-navy-900 text-white' => $audience === 'public', 'text-navy-400 hover:text-navy-700' => $audience !== 'public'])>Public</button>
                        </div>
                    @endif

                    <button type="button" wire:click="$toggle('showImport')" class="btn-ghost btn-sm gap-1.5">⇪ Import CSV</button>
                    <a href="{{ route('events.run-of-show', $event) }}" class="btn-ghost btn-sm gap-1.5" title="Open the live show-day cue sheet"><x-icon name="calendar" class="h-3.5 w-3.5" /> Open Run of Show</a>

                    @if ($view === 'program')
                        <a href="{{ route('events.agenda.program.pdf', [$event, 'day' => $day?->id, 'audience' => $audience]) }}"
                           class="flex h-9 items-center gap-1.5 rounded-xl border border-gold-300 bg-gold-50 px-3 text-xs font-bold text-gold-800 transition hover:bg-gold-100">
                            <x-icon name="chart" class="h-3.5 w-3.5" /> Programme PDF
                        </a>
                    @else
                        <a href="{{ route('events.agenda.timeline.pdf', [$event, 'day' => $day?->id]) }}"
                           class="flex h-9 items-center gap-1.5 rounded-xl border border-gold-300 bg-gold-50 px-3 text-xs font-bold text-gold-800 transition hover:bg-gold-100"
                           title="This day's timeline, drawn to scale">
                            <x-icon name="chart" class="h-3.5 w-3.5" /> Timeline PDF
                        </a>
                    @endif

                    {{-- Everything that covers the whole event rather than this day. --}}
                    <details class="relative" data-all-days>
                        <summary class="flex h-9 cursor-pointer list-none items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-xs font-semibold text-navy-700 shadow-sm transition hover:border-navy-200">
                            All days <span class="text-[9px] text-navy-300">▾</span>
                        </summary>
                        <div class="absolute right-0 z-30 mt-1 w-56 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
                            <a href="{{ route('events.agenda.timeline.pdf', $event) }}" class="block px-3 py-2 text-[11.5px] text-navy-700 transition hover:bg-page">Timeline PDF — every day</a>
                            <a href="{{ route('events.agenda.program.pdf', [$event, 'audience' => $audience]) }}" class="block px-3 py-2 text-[11.5px] text-navy-700 transition hover:bg-page">Programme PDF — every day</a>
                            <a href="{{ route('events.agenda.master.pdf', $event) }}" class="block px-3 py-2 text-[11.5px] text-navy-700 transition hover:bg-page">Master Schedule — incl. crew</a>
                            <a href="{{ route('events.run-of-show.pdf', [$event, 'day' => $day?->id]) }}" class="block px-3 py-2 text-[11.5px] text-navy-700 transition hover:bg-page">Run of Show PDF — this day</a>
                        </div>
                    </details>

                    <button type="button" wire:click="newSession" @disabled($days->isEmpty())
                            class="flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-500 px-4 text-xs font-bold text-navy-950 shadow-sm transition hover:brightness-105 disabled:opacity-40">＋ Add Session</button>
                </div>

                {{-- ══ BOARD + INSIGHTS ══ --}}
                <div class="grid gap-3 2xl:grid-cols-[minmax(0,1fr)_262px]">

            {{-- ── THE TIMELINE ── --}}
            <div class="card min-w-0 overflow-hidden">
                @if ($view === 'program')
                    <div class="p-5"><x-agenda-program :days="$programDays" :legend="false" /></div>
                @elseif ($timeline)
                    {{-- filters --}}
                    <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2 border-b border-line px-4 py-2.5">
                        <span class="eyebrow">Filters</span>
                        @foreach ($legend as [$label, $hex])
                            <span class="flex items-center gap-1.5 text-[11px] font-medium text-navy-700">
                                <span class="h-2 w-2 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
                            </span>
                        @endforeach
                        <span class="ms-auto text-[10px] italic text-navy-300">dashed = not confirmed</span>
                    </div>

                    {{-- The board marks conflicts with a pin and a red ring, but a
                         pin cannot say WHAT is wrong. The reasons stay on screen
                         rather than hiding in a tooltip nobody hovers. --}}
                    @if ($conflicts)
                        <div class="border-b border-risk/25 bg-risk/5 px-4 py-2">
                            <p class="text-[11px] font-bold text-red-700">
                                ⚠ {{ count($conflicts) }} scheduling {{ str('conflict')->plural(count($conflicts)) }} on this day
                            </p>
                            <ul class="mt-0.5 space-y-0.5 ps-4 text-[10.5px] text-red-600">
                                @foreach (collect($conflicts)->flatten()->unique()->take(6) as $reason)
                                    <li>· {{ $reason }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @php
                        // The board is drawn wider than its column and scrolls,
                        // which is the only way a 30-minute break is still a
                        // readable block: at column width a 13-hour day gives a
                        // coffee break 26px, which can hold nothing.
                        $tlWidth = max(900, (int) ceil($timeline['span'] / 60) * 112);

                        // The now-marker only means something on the day that is
                        // actually today; on any other day it would be a lie.
                        $isToday = $day?->date?->isToday();
                        $nowMin = (int) now()->format('H') * 60 + (int) now()->format('i');
                        $nowLeft = $isToday && $nowMin >= $timeline['startMin'] && $nowMin <= $timeline['startMin'] + $timeline['span']
                            ? round(($nowMin - $timeline['startMin']) / $timeline['span'] * 100, 3)
                            : null;
                    @endphp

                    <div class="scrollbar-none overflow-x-auto p-3">
                        <div style="min-width: {{ $tlWidth }}px" data-agenda-timeline data-tl-body
                             data-tl-base="{{ $tlWidth }}"
                             data-start-min="{{ $timeline['startMin'] }}" data-span-min="{{ $timeline['span'] }}">

                            {{-- time axis --}}
                            <div class="relative ms-[132px] h-8 border-b border-line">
                                <span class="absolute left-0 top-2 text-[9px] font-bold uppercase tracking-[0.16em] text-navy-300" style="margin-left: -132px">Time</span>
                                @foreach ($timeline['hours'] as $hour)
                                    <span class="absolute top-2 -translate-x-1/2 text-[10px] font-semibold text-navy-300" style="left: {{ $hour['left'] }}%">{{ $hour['label'] }}</span>
                                @endforeach

                                {{-- the conflict pins sit on the axis, above the block they belong to --}}
                                @foreach ($timeline['lanes']->flatMap->blocks as $b)
                                    @if (isset($conflicts[$b['session']->id]))
                                        <span class="absolute top-0 z-20 -translate-x-1/2 text-[11px] leading-none text-risk"
                                              style="left: {{ $b['left'] }}%"
                                              title="{{ implode(' · ', $conflicts[$b['session']->id]) }}">⚠</span>
                                    @endif
                                @endforeach

                                @if ($nowLeft !== null)
                                    <span class="absolute -top-0.5 z-30 -translate-x-1/2 rounded-md bg-gold-500 px-1.5 py-0.5 text-[9px] font-black text-navy-950 shadow"
                                          style="left: {{ $nowLeft }}%">{{ now()->format('H:i') }}</span>
                                @endif
                            </div>

                            {{-- lanes --}}
                            <div class="relative">
                                @if ($nowLeft !== null)
                                    {{-- One line down the whole board, so "now" reads
                                         against every room at once. --}}
                                    <span class="pointer-events-none absolute inset-y-0 z-20 w-px border-l border-dashed border-gold-500/70"
                                          style="left: calc(132px + (100% - 132px) * {{ $nowLeft / 100 }})"></span>
                                @endif

                                @foreach ($timeline['lanes'] as $lane)
                                    <div class="flex items-stretch border-b border-line last:border-b-0">
                                        <div class="flex w-[132px] shrink-0 flex-col justify-center gap-0.5 px-2.5 py-3">
                                            <p class="flex items-center gap-1.5 text-[11.5px] font-bold text-navy-900">
                                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-navy-300"></span>
                                                <span class="truncate">{{ $lane['room'] }}</span>
                                            </p>
                                            <p class="ps-3 text-[10px] text-muted">{{ $lane['blocks']->count() }} {{ str('session')->plural($lane['blocks']->count()) }}</p>
                                        </div>

                                        <div class="relative flex-1" data-room-track data-room-id="{{ $lane['room_id'] }}">
                                            @foreach ($timeline['hours'] as $hour)
                                                <span class="absolute inset-y-0 w-px bg-line/70" style="left: {{ $hour['left'] }}%"></span>
                                            @endforeach

                                            <div class="relative py-2.5" style="min-height: 96px">
                                                @foreach ($lane['blocks'] as $b)
                                                    @php
                                                        $sess = $b['session'];
                                                        $hasConflict = isset($conflicts[$sess->id]);
                                                        $lead = $sess->speakers->first();
                                                        $roomCap = $sess->room?->capacity ?: 0;
                                                        $fill = $roomCap > 0 && $sess->capacity ? (int) round($sess->capacity / $roomCap * 100) : null;
                                                        $typeLabel = \App\Livewire\Hub\AgendaTab::PALETTE[$sess->type][0] ?? str($sess->type)->replace('_', ' ')->title();
                                                        // A chip clipped to three letters says less than no chip.
                                                        // 132px is the lane label the track sits beside.
                                                        $roomForChip = ($tlWidth - 132) * $b['width'] / 100 >= 128;
                                                    @endphp
                                                    <div wire:key="blk-{{ $sess->id }}"
                                                         class="agenda-block group/blk absolute top-2.5 flex cursor-grab touch-none select-none flex-col justify-between rounded-xl p-2 text-left text-white shadow-sm transition hover:brightness-95 hover:ring-2 hover:ring-white/40 {{ $hasConflict ? 'ring-2 ring-risk ring-offset-1' : '' }}"
                                                         data-session-id="{{ $sess->id }}" data-start-min="{{ $b['startMin'] }}" data-dur-min="{{ $b['durMin'] }}" data-room-id="{{ $lane['room_id'] }}"
                                                         style="left: {{ $b['left'] }}%; width: {{ $b['width'] }}%; height: 80px; background: {{ $b['hex'] }}{{ $sess->isSettled() ? '' : '; outline: 2px dashed rgba(255,255,255,.55); outline-offset: -3px' }}"
                                                         title="{{ $sess->title }} · {{ substr($sess->starts_at, 0, 5) }}–{{ substr($sess->ends_at, 0, 5) }} · {{ $sess->statusLabel() }} — drag to reschedule, click to edit">

                                                        <span class="pointer-events-none min-w-0">
                                                            <span class="flex items-center gap-1 text-[10.5px] font-bold leading-tight">
                                                                @if ($sess->flagged)<span class="shrink-0">🚩</span>@endif
                                                                <span class="line-clamp-2">{{ $sess->title }}</span>
                                                            </span>
                                                            {{-- Time and kind on one line: the kind is what the
                                                                 colour already says, spelled out for anyone who
                                                                 cannot read the colour. --}}
                                                            <span class="mt-1 flex items-center gap-1.5 overflow-hidden">
                                                                <span class="shrink-0 text-[9.5px] text-white/75">{{ substr($sess->starts_at, 0, 5) }}–{{ substr($sess->ends_at, 0, 5) }}</span>
                                                                @if ($roomForChip)
                                                                    <span class="truncate rounded bg-black/25 px-1.5 py-px text-[8px] font-black uppercase tracking-[0.12em] text-white/90">{{ $typeLabel }}</span>
                                                                @endif
                                                            </span>
                                                        </span>

                                                        <span class="pointer-events-none flex items-center gap-1.5 overflow-hidden text-[9px] text-white/85">
                                                            @if ($lead)
                                                                <span class="grid h-3.5 w-3.5 shrink-0 place-items-center rounded-full bg-white/25 text-[7px] font-bold">{{ mb_substr($lead->name, 0, 1) }}</span>
                                                                <span class="truncate">{{ str($lead->name)->limit(14) }}</span>
                                                            @endif
                                                            @if ($sess->capacity)
                                                                <span class="ms-auto shrink-0 whitespace-nowrap">{{ number_format($sess->capacity) }}@if ($fill !== null) · {{ $fill }}%@endif</span>
                                                            @endif
                                                        </span>

                                                        {{-- hover quick actions --}}
                                                        <span class="absolute -top-2.5 right-1 z-10 hidden items-center gap-1 group-hover/blk:flex">
                                                            <button type="button" data-block-action wire:click.stop="toggleFlag({{ $sess->id }})"
                                                                    class="flex h-5 w-5 items-center justify-center rounded-md bg-white/95 text-eyebrow shadow ring-1 ring-black/5 transition hover:bg-white {{ $sess->flagged ? '' : 'grayscale' }}"
                                                                    title="{{ $sess->flagged ? 'Remove flag' : 'Flag' }}">🚩</button>
                                                            <button type="button" data-block-action wire:click.stop="deleteSession({{ $sess->id }})" wire:confirm="Delete “{{ $sess->title }}”?"
                                                                    class="flex h-5 w-5 items-center justify-center rounded-md bg-white/95 text-micro font-bold text-red-600 shadow ring-1 ring-black/5 transition hover:bg-white" title="Delete session">✕</button>
                                                        </span>
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- what the warnings on the board mean, and how big to draw it --}}
                    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-line px-4 py-2.5">
                        @foreach ([
                            ['text-risk', 'Speaker conflict'],
                            ['text-warn', 'Room capacity risk'],
                            ['text-navy-400', 'Overlapping session'],
                            ['text-navy-300', 'Missing presentation'],
                        ] as [$tone, $label])
                            <span class="flex items-center gap-1.5 text-[10.5px] text-muted"><span class="{{ $tone }}">⚠</span>{{ $label }}</span>
                        @endforeach

                        <div class="ms-auto flex items-center gap-1.5">
                            <button type="button" data-tl-fit class="btn-ghost btn-xs">⤢ Fit to screen</button>
                            <button type="button" data-tl-zoom="-1" class="grid h-6 w-6 place-items-center rounded-lg border border-line text-navy-500 transition hover:bg-page">−</button>
                            <span data-tl-level class="w-10 text-center text-[10.5px] font-bold tabular-nums text-navy-600">100%</span>
                            <button type="button" data-tl-zoom="1" class="grid h-6 w-6 place-items-center rounded-lg border border-line text-navy-500 transition hover:bg-page">+</button>
                        </div>
                    </div>
                @else
                    <div class="flex flex-col items-center px-8 py-16 text-center">
                        <h3 class="pf text-base font-bold text-navy-900">Nothing scheduled for this day</h3>
                        <p class="mt-1 max-w-md text-xs text-muted">Add a session and it'll plot on the timeline here.</p>
                        <button type="button" wire:click="newSession" class="btn-gold mt-4 h-9 px-4 text-xs">＋ Add Session</button>
                    </div>
                @endif
            </div>

            {{-- ── LIVE INSIGHTS ── --}}
            <aside class="space-y-3">
                <p class="eyebrow px-0.5">Live insights</p>

                <div class="card p-4">
                    <p class="pf text-[13px] font-bold text-navy-900">Today overview</p>
                    <p class="mt-0.5 text-[11px] text-muted">{{ $day?->date?->format('l, j M') ?? 'No day selected' }}</p>

                    @php $r = 2 * M_PI * 26; $done = 0; @endphp
                    <div class="mt-3 flex items-center gap-3.5">
                        <span class="relative grid h-[74px] w-[74px] shrink-0 place-items-center">
                            <svg class="h-[74px] w-[74px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                                <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-navy-50)" stroke-width="7" />
                                @foreach ($insights['byStatus'] as $row)
                                    @php
                                        $slice = $insights['total'] ? $row['count'] / $insights['total'] : 0;
                                        $offset = $r - ($r * $slice);
                                    @endphp
                                    <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $row['hex'] }}" stroke-width="7"
                                            stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $offset }}"
                                            transform="rotate({{ $done * 360 }} 30 30)" />
                                    @php $done += $slice; @endphp
                                @endforeach
                            </svg>
                            <span class="absolute text-center">
                                <span class="block text-[15px] font-black leading-none text-navy-900">{{ $insights['total'] }}</span>
                            </span>
                        </span>

                        <ul class="min-w-0 flex-1 space-y-1">
                            @forelse ($insights['byStatus'] as $row)
                                <li class="flex items-center gap-1.5 text-[11px]">
                                    <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $row['hex'] }}"></span>
                                    <span class="w-4 shrink-0 font-bold tabular-nums text-navy-900">{{ $row['count'] }}</span>
                                    <span class="truncate text-muted">{{ $row['label'] }}</span>
                                </li>
                            @empty
                                <li class="text-[11px] text-muted">Nothing scheduled.</li>
                            @endforelse
                        </ul>
                    </div>
                </div>

                {{-- Three things that each need a person to do something. --}}
                @foreach ([
                    ['count' => $insights['awaitingSpeakers'], 'label' => 'Awaiting speakers', 'tone' => 'navy'],
                    ['count' => $insights['capacityRisks'], 'label' => 'Capacity risks', 'tone' => 'warn'],
                    ['count' => $insights['doubleBookings'], 'label' => 'Double bookings', 'tone' => 'risk'],
                ] as $alert)
                    @php
                        [$ring, $ink] = $alert['count'] === 0
                            ? ['ring-line', 'text-navy-300']
                            : match ($alert['tone']) {
                                'risk' => ['ring-risk/30 bg-risk/5', 'text-risk'],
                                'warn' => ['ring-warn/30 bg-warn/5', 'text-warn'],
                                default => ['ring-line', 'text-navy-700'],
                            };
                    @endphp
                    <div class="card flex items-center gap-3 px-3.5 py-2.5 ring-1 {{ $ring }}">
                        <span class="pf text-[20px] font-black leading-none {{ $ink }}">{{ $alert['count'] }}</span>
                        <span class="min-w-0 flex-1 truncate text-[11.5px] font-semibold text-navy-700">{{ $alert['label'] }}</span>
                    </div>
                @endforeach

                <div class="card p-3.5">
                    <div class="flex items-baseline justify-between">
                        <span class="pf text-[20px] font-black leading-none text-navy-900">{{ $insights['pct'] }}%</span>
                        <span class="text-[11px] font-semibold text-muted">Agenda complete</span>
                    </div>
                    <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-navy-50">
                        <div class="h-full rounded-full bg-track" style="width: {{ $insights['pct'] }}%"></div>
                    </div>
                    <p class="mt-1.5 text-[10.5px] text-muted">{{ $insights['settled'] }} of {{ $insights['total'] }} confirmed on this day</p>
                </div>

                {{-- What is on next, but only on the day that is actually today. --}}
                @if ($insights['next'])
                    <p class="eyebrow px-0.5 pt-1">Up next</p>
                    @php $n = $insights['next']; [$nLegend, $nHex] = \App\Livewire\Hub\AgendaTab::PALETTE[$n->type] ?? ['Session', '#3B82F6']; @endphp
                    <div class="card p-3.5">
                        <div class="flex items-center gap-2">
                            @if ($insights['nextIn'] !== null && $insights['nextIn'] > 0)
                                <span class="text-[10px] font-bold uppercase tracking-wide text-muted">In {{ $insights['nextIn'] }} min</span>
                            @endif
                        <span class="ms-auto inline-block rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white" style="background: {{ $nHex }}">{{ $nLegend }}</span>
                        <p class="pf mt-1.5 text-[13.5px] font-bold leading-snug text-navy-900">{{ $n->title }}</p>
                        <p class="mt-1 text-[11px] text-muted">{{ substr($n->starts_at, 0, 5) }}–{{ substr($n->ends_at, 0, 5) }}@if ($n->room) · {{ $n->room->name }}@endif</p>
                        @if ($n->speakers->isNotEmpty())
                            <p class="mt-1.5 flex items-center gap-1.5 text-[11px] font-semibold text-navy-700">
                                <span class="grid h-5 w-5 place-items-center rounded-full bg-navy-900 text-[9px] font-bold text-gold-400">{{ mb_substr($n->speakers->first()->name, 0, 1) }}</span>
                                {{ $n->speakers->first()->name }}
                            </p>
                        @endif
                    </div>
                @endif

                <a href="{{ route('events.run-of-show', $event) }}"
                   class="flex h-11 items-center justify-center gap-2 rounded-2xl bg-navy-900 text-[12.5px] font-bold text-white transition hover:bg-navy-800">
                    View Run of Show →
                </a>
            </aside>
                </div>
            </div>
        </div>
    @endif

    @script
    <script>
        /* The board is drawn in percentages inside a min-width container, so
           zoom is that width — no re-layout, and the drag maths keeps working
           because every block is still positioned as a share of the track. */
        const tl = () => document.querySelector('[data-tl-body]');
        let level = 100;

        const apply = () => {
            const el = tl();
            if (! el) return;
            // The base is the day's own width — an hour is always the same
            // number of pixels, whatever the day is long.
            const base = Number(el.dataset.tlBase) || 900;
            el.style.minWidth = Math.round(base * level / 100) + 'px';
            const out = document.querySelector('[data-tl-level]');
            if (out) out.textContent = level + '%';
        };

        document.querySelectorAll('[data-tl-zoom]').forEach((b) => b.addEventListener('click', () => {
            level = Math.min(300, Math.max(60, level + Number(b.dataset.tlZoom) * 20));
            apply();
        }));

        document.querySelector('[data-tl-fit]')?.addEventListener('click', () => {
            level = 100;
            apply();
        });
    </script>
    @endscript

    {{-- ══════════ Add / Edit Session modal ══════════ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit Session' : 'Add Session'" max="2xl" close="closeForm" flush wire:key="session-modal">
                <form wire:submit="saveSession">

                    <div class="space-y-4 px-6 py-5">
                        <div class="grid gap-4 sm:grid-cols-[1fr_auto]">
                            <div>
                                <label class="field-label !mb-1.5" for="m-title">Title <span class="text-risk">*</span></label>
                                <input id="m-title" type="text" wire:model="title" class="field !py-2.5" placeholder="Opening Keynote">
                                @error('title') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:w-44">
                                <label class="field-label !mb-1.5" for="m-type">Type</label>
                                <input id="m-type" type="text" list="session-types" wire:model="type" class="field !py-2.5"
                                       autocomplete="off" placeholder="Keynote, Setup, Rehearsal…">
                                <datalist id="session-types">
                                    @foreach ($typeOptions as $t)<option value="{{ $t }}"></option>@endforeach
                                </datalist>
                                <p class="mt-1 text-eyebrow text-muted">Pick one or type your own.</p>
                                @error('type') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div>
                            <label class="field-label !mb-1.5">Format</label>
                            <div class="grid grid-cols-3 gap-2.5">
                                @foreach (\App\Support\Taxonomy::options('session_format') as $val => $lbl)
                                    <button type="button" wire:click="$set('format', '{{ $val }}')"
                                            @class([
                                                'rounded-2xl border py-2.5 text-sm font-bold transition',
                                                'border-navy-900 bg-navy-900 text-white' => $format === $val,
                                                'border-line text-navy-500 hover:text-navy-800' => $format !== $val,
                                            ])>{{ $lbl }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="field-label !mb-1.5" for="m-status">Status</label>
                            <select id="m-status" wire:model="status" class="field !py-2.5">
                                @foreach (\App\Models\EventAgendaSession::STATUS_META as $val => [$lbl, $settled])
                                    <option value="{{ $val }}">{{ $lbl }}{{ $settled ? '' : ' — not for distribution' }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="field-label !mb-1.5" for="m-cap">Seat capacity <span class="font-normal normal-case tracking-normal text-navy-300">optional — limits attendee sign-ups</span></label>
                            <input id="m-cap" type="number" min="0" wire:model="capacity" class="field !py-2.5" placeholder="Leave blank for unlimited">
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="field-label !mb-1.5" for="m-day">Day <span class="text-risk">*</span></label>
                                <select id="m-day" wire:model="agenda_day_id" class="field !py-2.5">
                                    @foreach ($days as $d)<option value="{{ $d->id }}">Day {{ $loop->iteration }} — {{ $d->date?->format('D j M') }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="field-label !mb-1.5" for="m-start">Start <span class="text-risk">*</span></label>
                                <input id="m-start" type="time" wire:model="starts_at" class="field !py-2.5">
                            </div>
                            <div>
                                <label class="field-label !mb-1.5" for="m-end">End <span class="text-risk">*</span></label>
                                <input id="m-end" type="time" wire:model="ends_at" class="field !py-2.5">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="field-label !mb-0">Speaker line-up</label>
                                    <span class="text-eyebrow text-muted">Pick from the roster or type a new name — new people are added to Speakers automatically</span>
                                </div>

                                <datalist id="speaker-roster">
                                    @foreach ($speakerOptions as $sp)<option value="{{ $sp }}"></option>@endforeach
                                </datalist>

                                <div class="space-y-2">
                                    @forelse ($speakerRows as $i => $row)
                                        <div class="flex items-center gap-2" wire:key="spk-{{ $i }}">
                                            <input type="text" list="speaker-roster" wire:model.blur="speakerRows.{{ $i }}.name"
                                                   class="field !py-2.5 flex-1" placeholder="Speaker name">
                                            <select wire:model="speakerRows.{{ $i }}.role" class="field !py-2.5 w-40 shrink-0">
                                                @foreach ($roles as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="removeSpeakerRow({{ $i }})"
                                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-navy-300 transition hover:bg-red-50 hover:text-red-600"
                                                    title="Remove">✕</button>
                                        </div>
                                    @empty
                                        <p class="rounded-xl border border-dashed border-line px-3 py-3 text-center text-micro text-muted">
                                            No speakers billed yet — a panel can carry a moderator plus several panellists.
                                        </p>
                                    @endforelse
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" wire:click="addSpeakerRow('keynote')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-semibold text-navy-700 transition hover:border-gold-300">＋ Keynote</button>
                                    <button type="button" wire:click="addSpeakerRow('moderator')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-semibold text-navy-700 transition hover:border-gold-300">＋ Moderator</button>
                                    <button type="button" wire:click="addSpeakerRow('panelist')" class="rounded-lg border border-line bg-white px-2.5 py-1.5 text-micro font-semibold text-navy-700 transition hover:border-gold-300">＋ Panellist</button>
                                </div>
                            </div>
                            <div>
                                <label class="field-label !mb-1.5" for="m-room">Venue / Room</label>
                                <select id="m-room" wire:model="room_id" class="field mb-2 !py-2.5">
                                    <option value="">— Select a venue —</option>
                                    @foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach
                                </select>
                                <input type="text" wire:model="newRoomName" class="field !py-2.5" placeholder="…or type a room">
                            </div>
                        </div>

                        <div>
                            <label class="field-label !mb-1.5" for="m-desc">Description / Notes</label>
                            <textarea id="m-desc" wire:model="description" rows="2" class="field !py-2.5" placeholder="Optional details…"></textarea>
                        </div>
                    </div>

                    {{-- footer --}}
                    <div class="flex items-center justify-end gap-3 rounded-b-[24px] border-t border-line bg-page/60 px-6 py-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-navy-700">
                            <input type="checkbox" wire:model="flagged" class="h-4 w-4 rounded border-line text-gold-700 focus:ring-gold-400">
                            <span>🚩 Flag</span>
                        </label>
                        @if ($editingId)
                            <button type="button" wire:click="deleteSession({{ $editingId }})" wire:confirm="Delete this session?" class="rounded-2xl px-4 py-2.5 text-sm font-bold text-risk transition hover:bg-risk/5">Delete</button>
                        @endif
                        <span class="mr-auto"></span>
                        <button type="button" wire:click="closeForm" class="rounded-2xl bg-fill px-6 py-2.5 text-sm font-bold text-navy-700 transition hover:bg-line">Cancel</button>
                        <button type="submit" class="rounded-2xl bg-navy-900 px-7 py-2.5 text-sm font-bold text-white shadow-[0_10px_25px_rgba(11,31,58,0.25)] transition hover:bg-navy-800">{{ $editingId ? 'Save Session' : 'Add Session' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif

    @script
    <script>
        // Keep the newest component reference; bind global drag handlers only once.
        window.__agendaWire = $wire;
        if (! window.__agendaDragBound) {
            window.__agendaDragBound = true;
            let drag = null;
            const pad = n => String(n).padStart(2, '0');
            const fmt = m => pad(Math.floor(m / 60)) + ':' + pad(((m % 60) + 60) % 60);

            document.addEventListener('pointerdown', e => {
                const block = e.target.closest('.agenda-block');
                if (! block || e.button !== 0 || e.target.closest('[data-block-action]')) return;
                const tl = block.closest('[data-agenda-timeline]');
                const track = block.closest('[data-room-track]');
                if (! tl || ! track) return;
                drag = {
                    block, id: +block.dataset.sessionId,
                    startMin: +block.dataset.startMin, durMin: +block.dataset.durMin,
                    span: +tl.dataset.spanMin, base: +tl.dataset.startMin,
                    trackW: track.getBoundingClientRect().width,
                    x: e.clientX, y: e.clientY, moved: false,
                    newStart: +block.dataset.startMin, roomId: block.dataset.roomId || '',
                };
                e.preventDefault();
            });

            document.addEventListener('pointermove', e => {
                if (! drag) return;
                const dx = e.clientX - drag.x, dy = e.clientY - drag.y;
                if (! drag.moved && Math.abs(dx) < 4 && Math.abs(dy) < 4) return;
                drag.moved = true;
                drag.block.style.opacity = '0.8';
                drag.block.style.cursor = 'grabbing';
                drag.block.style.pointerEvents = 'none'; // let elementFromPoint see the lane beneath

                const pxPerMin = drag.trackW / drag.span;
                let ns = drag.startMin + Math.round((dx / pxPerMin) / 5) * 5;
                ns = Math.max(0, Math.min(ns, 1440 - drag.durMin));
                drag.newStart = ns;
                drag.block.style.left = ((ns - drag.base) / drag.span * 100) + '%';

                const lane = document.elementFromPoint(e.clientX, e.clientY)?.closest('[data-room-track]');
                if (lane) drag.roomId = lane.dataset.roomId || '';
                drag.block.title = fmt(ns) + '–' + fmt(ns + drag.durMin);
            });

            window.addEventListener('pointerup', () => {
                if (! drag) return;
                const d = drag; drag = null;
                d.block.style.opacity = ''; d.block.style.cursor = ''; d.block.style.pointerEvents = '';
                const wire = window.__agendaWire;
                if (! d.moved) { wire.editSession(d.id); return; }
                wire.moveSession(d.id, d.newStart, d.roomId === '' ? null : +d.roomId);
            });
        }
    </script>
    @endscript
</div>
