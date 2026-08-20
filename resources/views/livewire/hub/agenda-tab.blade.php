<div>
    @php
        // The old Agenda Command Header (ring + sessions/confirmed/venues/
        // days figures, built from $allSessions/$confPct/$unconfirmed/etc.)
        // is retired — the Event Hub's new Universal Module Header
        // (eo/hubx-module-header.blade.php) shows the equivalent numbers
        // above this component now, so showing both was two headers for one
        // module. $daySessions is the only one of that set anything else in
        // this file still reads.
        $daySessions = $day?->sessions->count() ?? 0;
    @endphp

    {{-- Import panel --}}
    @if ($showImport)
        <form wire:submit="import" class="eo-domain-card mb-4 flex flex-wrap items-end gap-3 !p-4">
            <div class="flex-1">
                <label class="eo-eo-label !mb-1" for="import-file">CSV file — columns: title, type, start, end, room, speaker, moderator (separate several names with ; )</label>
                <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="eo-input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-eo-navy file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                @error('importFile') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-eo-muted hover:text-eo-text">Cancel</button>
            <x-eo.button type="submit" size="sm" class="!h-10 !px-5" wire:loading.attr="disabled" wire:target="import,importFile">Import</x-eo.button>
        </form>
    @endif

    @if ($days->isEmpty())
        <x-empty icon="calendar" title="No agenda days yet"
                 hint="Days come from the event's date range — set the dates in Settings and they appear here, or start one now and the programme builds around it.">
            <x-slot:actions>
                <button type="button" wire:click="addDay" class="eo-btn eo-btn-primary eo-btn-sm">＋ Add the first day</button>
                <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="eo-btn eo-btn-ghost eo-btn-sm">Set the event dates</a>
            </x-slot:actions>
        </x-empty>
    @else
        {{-- ══════════ THE BUILDER SHELL ══════════
             Left rail (days, rooms, tracks, filters, add session) · center
             canvas (the timeline or the programme, unchanged underneath) ·
             right inspector (a selected session, or day insights when
             nothing is selected). The command bar runs the full width below
             — everything that acts on the whole day or the whole agenda,
             rather than on one session. --}}
        <div class="grid items-start gap-3 xl:grid-cols-[240px_minmax(0,1fr)_296px]">

            {{-- ═══ LEFT RAIL ═══ --}}
            <aside class="eo-domain-card flex flex-col gap-4 self-start !p-3.5">

                {{-- Days --}}
                <div>
                    <div class="mb-2 flex items-center justify-between px-0.5">
                        <p class="eo-label">Days</p>
                        <button type="button" wire:click="addDay" class="text-[11px] font-bold text-eo-teal-ink hover:underline">＋ Add</button>
                    </div>
                    <div class="scrollbar-none max-h-[260px] space-y-1.5 overflow-y-auto">
                        @foreach ($dayCards as $card)
                            @php $d = $card['model']; $on = $day && $day->id === $d->id; $r = 2 * M_PI * 11; @endphp
                            <button type="button" wire:click="selectDay({{ $d->id }})"
                                    @class([
                                        'flex w-full items-center gap-2.5 rounded-xl border px-2.5 py-2 text-left transition',
                                        'border-eo-navy bg-eo-navy text-white' => $on,
                                        'border-eo-line bg-white text-eo-text hover:border-eo-teal/30' => ! $on,
                                    ])>
                                <span class="relative grid h-7 w-7 shrink-0 place-items-center">
                                    <svg class="h-7 w-7 -rotate-90" viewBox="0 0 26 26" aria-hidden="true">
                                        <circle cx="13" cy="13" r="11" fill="none" stroke="{{ $on ? 'rgba(255,255,255,.16)' : 'var(--color-eo-bg)' }}" stroke-width="2.5" />
                                        <circle cx="13" cy="13" r="11" fill="none" stroke="var(--color-eo-gold)" stroke-width="2.5" stroke-linecap="round"
                                                stroke-dasharray="{{ $r }}" stroke-dashoffset="{{ $r - ($r * $card['pct'] / 100) }}" />
                                    </svg>
                                    <span class="absolute text-[7.5px] font-black">{{ $card['pct'] }}</span>
                                </span>
                                <span class="min-w-0 flex-1">
                                    <span class="block truncate text-[12px] font-bold">{{ $d->date?->format('D, j M') ?? $d->label }}</span>
                                    <span class="block text-[10px] {{ $on ? 'text-white/55' : 'text-eo-muted' }}">{{ $card['sessions'] }} {{ str('session')->plural($card['sessions']) }}</span>
                                </span>
                            </button>
                        @endforeach
                    </div>
                </div>

                {{-- Rooms --}}
                <div class="border-t border-eo-line pt-3.5">
                    <p class="eo-label mb-1.5">Rooms</p>
                    <div class="relative mb-2">
                        <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3 w-3 -translate-y-1/2 text-eo-muted" />
                        <input type="search" wire:model.live.debounce.250ms="venueSearch" placeholder="Search rooms…"
                               aria-label="Search rooms"
                               class="eo-input h-8 w-full !rounded-lg !py-0 !ps-7 text-[11.5px]">
                    </div>
                    <div class="scrollbar-none max-h-[220px] space-y-1.5 overflow-y-auto">
                        @forelse ($venues as $v)
                            @php
                                [$dot, $chip, $note] = match ($v['state']) {
                                    'conflict' => ['bg-eo-risk', 'bg-eo-risk-soft text-eo-risk-ink', $v['conflicts'].' '.str('conflict')->plural($v['conflicts'])],
                                    'warning' => ['bg-eo-warn', 'bg-eo-warn-soft text-amber-800', $v['over'].' warning'],
                                    default => ['bg-eo-ok', 'bg-eo-ok-soft text-emerald-800', 'No issues'],
                                };
                            @endphp
                            <div class="rounded-xl px-1.5 py-1.5 hover:bg-eo-workspace">
                                <p class="truncate text-[11.5px] font-bold text-eo-text">{{ $v['room']->name }}</p>
                                <p class="mt-0.5 text-[10px] text-eo-muted">
                                    @if ($v['room']->capacity) Cap. {{ number_format($v['room']->capacity) }} · @endif{{ $v['sessions'] }} {{ str('session')->plural($v['sessions']) }}
                                </p>
                                <span class="mt-1 inline-flex items-center gap-1 rounded-full px-1.5 py-0.5 text-[9px] font-bold {{ $chip }}">
                                    <span class="h-1.5 w-1.5 rounded-full {{ $dot }}"></span>{{ $note }}
                                </span>
                            </div>
                        @empty
                            <p class="px-1 py-3 text-center text-[11px] text-eo-muted">
                                {{ trim($venueSearch) === '' ? 'No rooms yet.' : 'No room matches “'.$venueSearch.'”.' }}
                            </p>
                        @endforelse
                    </div>
                    <a href="{{ route('events.hub', [$event, 'tab' => 'venue']) }}"
                       class="mt-1.5 block text-center text-[11px] font-semibold text-eo-muted transition hover:text-eo-teal-ink">＋ Add Room</a>
                </div>

                {{-- Tracks — read-only for now; a full Tracks view is staged for later. --}}
                <div class="border-t border-eo-line pt-3.5">
                    <p class="eo-label mb-1.5">Tracks</p>
                    <div class="scrollbar-none max-h-[140px] space-y-1 overflow-y-auto">
                        @forelse ($trackSummary as $t)
                            <div class="flex items-center justify-between gap-2 rounded-lg px-1.5 py-1 text-[11.5px]">
                                <span class="min-w-0 truncate text-eo-text">{{ $t['name'] }}</span>
                                <span class="shrink-0 rounded-full bg-eo-workspace px-1.5 py-0.5 text-[9.5px] font-bold text-eo-muted">{{ $t['count'] }}</span>
                            </div>
                        @empty
                            <p class="px-1 py-2 text-[11px] text-eo-muted">No sessions on this day yet.</p>
                        @endforelse
                    </div>
                </div>

                {{-- Filters --}}
                <div class="border-t border-eo-line pt-3.5">
                    <p class="eo-label mb-1.5">Filters</p>
                    <div class="flex flex-wrap gap-1.5">
                        @foreach (\App\Models\EventAgendaSession::STATUS_META as $key => [$label, $settled2, $hex])
                            @php $on = in_array($key, $statusFilter, true); @endphp
                            <button type="button" wire:click="toggleStatusFilter('{{ $key }}')"
                                    @class([
                                        'rounded-full border px-2.5 py-1 text-[10.5px] font-bold transition',
                                        'border-transparent text-white' => $on,
                                        'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30' => ! $on,
                                    ])
                                    style="{{ $on ? 'background:'.$hex : '' }}">{{ $label }}</button>
                        @endforeach
                    </div>
                    @if ($statusFilter)
                        <p class="mt-1.5 text-[10px] text-eo-muted">Other statuses are dimmed on the board.</p>
                    @endif
                </div>

                {{-- The one action worth a full-width button. --}}
                <button type="button" wire:click="newSession"
                        class="mt-auto flex h-10 w-full items-center justify-center gap-1.5 rounded-xl bg-gradient-to-r from-eo-gold-soft to-eo-gold text-[12.5px] font-black text-eo-navy-deep shadow-[0_10px_22px_-14px_rgba(214,174,52,0.7)] transition hover:brightness-105">
                    ＋ Add Session
                </button>
            </aside>

            {{-- ═══ CENTER CANVAS ═══ --}}
            <div class="eo-domain-card min-w-0 overflow-hidden !p-0">
                @if ($view === 'program')
                    @php $programEmpty = collect($programDays)->every(fn ($d) => \App\Services\AgendaProgram::isEmpty($d['program'])); @endphp
                    @if ($programEmpty)
                        <x-empty icon="calendar" class="!border-0 !shadow-none"
                                 :title="$daySessions ? 'Nothing on the public programme for this day' : 'Nothing scheduled for this day'"
                                 :hint="$daySessions
                                     ? 'Everything on this day sits on a back-of-house track, so a delegate would see an empty page. Switch to Internal to work on it.'
                                     : 'Add a session and it appears here as a programme card, ready to print.'">
                            <x-slot:actions>
                                @if ($daySessions)
                                    <button type="button" wire:click="setAudience('internal')" class="eo-btn eo-btn-primary eo-btn-sm">Show the internal programme</button>
                                @else
                                    <button type="button" wire:click="newSession" class="eo-btn eo-btn-primary eo-btn-sm">＋ Add the first session</button>
                                @endif
                            </x-slot:actions>
                        </x-empty>
                    @else
                        <div class="p-5"><x-agenda-program :days="$programDays" :legend="false" /></div>
                    @endif
                @elseif ($view === 'timeline')
                    @if ($timeline)
                        @include('livewire.hub.partials.agenda-gantt', ['lanes' => $timeline['lanes']])
                    @else
                        <x-empty icon="chart" class="!border-0 !shadow-none" title="Nothing scheduled for this day"
                                 hint="Sessions plot themselves against the clock as you add them, one lane per room. Copy yesterday's running order if this day repeats it.">
                            <x-slot:actions>
                                <button type="button" wire:click="newSession" class="eo-btn eo-btn-primary eo-btn-sm">＋ Add the first session</button>
                                @if ($days->count() > 1)
                                    <button type="button" wire:click="$toggle('showImport')" class="eo-btn eo-btn-ghost eo-btn-sm">Import from CSV</button>
                                @endif
                            </x-slot:actions>
                        </x-empty>
                    @endif

                {{-- ═══ ROOMS VIEW ═══ the same board, seen one room at a
                     time (or every room, same as Timeline) — same lanes,
                     same blocks, same drag. Use existing EventRoom data;
                     no parallel room system. ═══ --}}
                @elseif ($view === 'rooms')
                    <div class="flex flex-wrap items-center gap-1.5 border-b border-eo-line px-4 py-2.5">
                        <span class="eo-label shrink-0">Room</span>
                        <button type="button" wire:click="selectRoomFilter(null)"
                                @class(['rounded-full border px-2.5 py-1 text-[10.5px] font-bold transition', 'border-eo-navy bg-eo-navy text-white' => ! $roomsViewFilter, 'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30' => $roomsViewFilter])>
                            All Rooms
                        </button>
                        @foreach ($rooms as $room)
                            <button type="button" wire:click="selectRoomFilter({{ $room->id }})"
                                    @class(['rounded-full border px-2.5 py-1 text-[10.5px] font-bold transition', 'border-eo-navy bg-eo-navy text-white' => $roomsViewFilter === $room->id, 'border-eo-line bg-white text-eo-muted hover:border-eo-teal/30' => $roomsViewFilter !== $room->id])>
                                {{ $room->name }}
                            </button>
                        @endforeach
                    </div>

                    @php
                        $roomWorkload = $roomsViewFilter ? $venues->firstWhere('room.id', $roomsViewFilter) : null;
                        $roomsLanes = $timeline
                            ? ($roomsViewFilter ? $timeline['lanes']->filter(fn ($l) => $l['room_id'] === $roomsViewFilter)->values() : $timeline['lanes'])
                            : collect();
                    @endphp

                    @if ($roomWorkload)
                        {{-- Workload — sessions, conflicts, capacity, read off
                             the same venue-rail figures the left rail already
                             shows; nothing recomputed for this header. --}}
                        <div class="grid grid-cols-3 divide-x divide-eo-line border-b border-eo-line text-center">
                            <div class="px-3 py-3"><p class="text-[18px] font-black text-eo-text">{{ $roomWorkload['sessions'] }}</p><p class="text-[10px] text-eo-muted">{{ str('Session')->plural($roomWorkload['sessions']) }} today</p></div>
                            <div class="px-3 py-3"><p class="text-[18px] font-black {{ $roomWorkload['conflicts'] ? 'text-eo-risk-ink' : 'text-eo-text' }}">{{ $roomWorkload['conflicts'] }}</p><p class="text-[10px] text-eo-muted">{{ str('Conflict')->plural($roomWorkload['conflicts']) }}</p></div>
                            <div class="px-3 py-3"><p class="text-[18px] font-black text-eo-text">{{ $roomWorkload['room']->capacity ? number_format($roomWorkload['room']->capacity) : '—' }}</p><p class="text-[10px] text-eo-muted">Capacity</p></div>
                        </div>
                    @endif

                    @if ($roomsViewFilter && $roomsLanes->isEmpty())
                        <x-empty icon="building" class="!border-0 !shadow-none" title="Empty room — nothing scheduled today"
                                 hint="This room has no sessions on this day. Add one, or drag a session here from another lane in All Rooms.">
                            <x-slot:actions>
                                <button type="button" wire:click="newSession" class="eo-btn eo-btn-primary eo-btn-sm">＋ Add a session</button>
                            </x-slot:actions>
                        </x-empty>
                    @else
                        @include('livewire.hub.partials.agenda-gantt', ['lanes' => $roomsLanes])
                    @endif

                {{-- ═══ TRACKS VIEW ═══ the day's sessions grouped by track,
                     in running order — continuity across the day rather
                     than across rooms. Uses the existing free-text track
                     column; no new schema. ═══ --}}
                @elseif ($view === 'tracks')
                    <div class="space-y-5 p-4">
                        @forelse ($trackGroups as $group)
                            <div>
                                <div class="mb-2 flex items-center gap-2 px-0.5">
                                    <p class="text-[12.5px] font-bold text-eo-text">{{ $group['name'] }}</p>
                                    <span class="rounded-full bg-eo-workspace px-2 py-0.5 text-[10px] font-bold text-eo-muted">{{ $group['sessions']->count() }}</span>
                                    @if ($group['name'] === 'Unassigned')
                                        <span class="text-[10px] italic text-eo-muted">no track chosen yet</span>
                                    @endif
                                </div>
                                <div class="space-y-1.5">
                                    @foreach ($group['sessions'] as $s)
                                        @php
                                            $hasConflict = isset($conflicts[$s->id]);
                                            $severity = $severityBySession[$s->id] ?? null;
                                            $selected = $selectedSession && $selectedSession->id === $s->id;
                                            $tTone = match (true) {
                                                $hasConflict => 'risk',
                                                in_array($s->status, ['confirmed', 'final'], true) => 'ok',
                                                in_array($s->status, ['needs_review', 'waiting_speaker'], true) => 'warn',
                                                default => 'pending',
                                            };
                                        @endphp
                                        <button type="button" wire:click="selectSession({{ $s->id }})"
                                                @class([
                                                    'flex w-full items-center gap-3 rounded-xl border px-3 py-2.5 text-left transition hover:border-eo-teal/30',
                                                    'border-eo-teal bg-eo-teal-soft/30' => $selected,
                                                    'border-eo-line bg-white' => ! $selected,
                                                ])>
                                            <span class="w-12 shrink-0 font-mono text-[11px] text-eo-muted">{{ substr($s->starts_at, 0, 5) }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-1.5">
                                                    @if ($severity === 'critical')
                                                        <span class="shrink-0 text-eo-risk" title="Critical — scheduling conflict">⚠</span>
                                                    @elseif ($severity === 'high')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn" title="High priority"></span>
                                                    @elseif ($severity === 'medium')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn/50" title="Needs attention"></span>
                                                    @endif
                                                    <span class="truncate text-[12.5px] font-bold text-eo-text">{{ $s->title }}</span>
                                                </span>
                                                <span class="block truncate text-[10.5px] text-eo-muted">{{ $s->room?->name ?? 'No room' }}@if ($s->speakers->isNotEmpty()) · {{ $s->speakers->first()->name }}@endif</span>
                                            </span>
                                            <x-eo.status-pill :tone="$tTone" class="shrink-0 !text-[9px]">{{ $s->statusLabel() }}</x-eo.status-pill>
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @empty
                            <x-empty icon="columns" class="!border-0 !shadow-none" title="Nothing scheduled for this day"
                                     hint="Sessions will group themselves by track as you add them — give one a track in the session form and it gets its own lane here.">
                                <x-slot:actions>
                                    <button type="button" wire:click="newSession" class="eo-btn eo-btn-primary eo-btn-sm">＋ Add the first session</button>
                                </x-slot:actions>
                            </x-empty>
                        @endforelse
                    </div>

                {{-- ═══ SESSION LIST VIEW ═══ every session, every day — the
                     audit view. Row click opens the Inspector; no drag here,
                     a table row is the wrong gesture for it. ═══ --}}
                @elseif ($view === 'sessions')
                    <div class="border-b border-eo-line p-3">
                        <div class="relative max-w-xs">
                            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                            <input type="search" wire:model.live.debounce.250ms="sessionSearch" placeholder="Search title or speaker…"
                                   class="eo-input h-9 w-full !ps-8 text-[12px]">
                        </div>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-[12px]">
                            <thead>
                                <tr class="border-b border-eo-line bg-eo-workspace text-left text-[10px] font-bold uppercase tracking-[0.06em] text-eo-muted">
                                    <th class="whitespace-nowrap px-3 py-2">Title</th>
                                    <th class="whitespace-nowrap px-3 py-2">Day</th>
                                    <th class="whitespace-nowrap px-3 py-2">Time</th>
                                    <th class="whitespace-nowrap px-3 py-2">Room</th>
                                    <th class="whitespace-nowrap px-3 py-2">Track</th>
                                    <th class="whitespace-nowrap px-3 py-2">Speaker</th>
                                    <th class="whitespace-nowrap px-3 py-2">Status</th>
                                    <th class="whitespace-nowrap px-3 py-2">Capacity</th>
                                    <th class="px-3 py-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse ($sessionList as $s)
                                    @php
                                        $rowConflict = isset($conflictsByDay[$s->agenda_day_id][$s->id]);
                                        $rowSeverity = $severityBySession[$s->id] ?? null;
                                        $selected = $selectedSession && $selectedSession->id === $s->id;
                                        $lTone = match (true) {
                                            $rowConflict => 'risk',
                                            in_array($s->status, ['confirmed', 'final'], true) => 'ok',
                                            in_array($s->status, ['needs_review', 'waiting_speaker'], true) => 'warn',
                                            default => 'pending',
                                        };
                                    @endphp
                                    <tr wire:click="selectSession({{ $s->id }})"
                                        @class(['cursor-pointer border-b border-eo-line transition last:border-b-0 hover:bg-eo-workspace', 'bg-eo-teal-soft/20' => $selected])>
                                        <td class="max-w-[220px] truncate px-3 py-2 font-bold text-eo-text">
                                            <span class="flex items-center gap-1.5">
                                                @if ($rowSeverity === 'critical')
                                                    <span class="shrink-0 text-eo-risk" title="Critical — scheduling conflict">⚠</span>
                                                @elseif ($rowSeverity === 'high')
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn" title="High priority"></span>
                                                @elseif ($rowSeverity === 'medium')
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn/50" title="Needs attention"></span>
                                                @endif
                                                <span class="truncate">{{ $s->title }}</span>
                                            </span>
                                        </td>
                                        <td class="whitespace-nowrap px-3 py-2 text-eo-muted">{{ $s->day?->date?->format('D j M') ?? '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2 font-mono text-eo-muted">{{ substr($s->starts_at, 0, 5) }}–{{ substr($s->ends_at, 0, 5) }}</td>
                                        <td class="max-w-[120px] truncate px-3 py-2 text-eo-muted">{{ $s->room?->name ?? '—' }}</td>
                                        <td class="max-w-[110px] truncate px-3 py-2 text-eo-muted">{{ $s->track ?: '—' }}</td>
                                        <td class="max-w-[140px] truncate px-3 py-2 text-eo-muted">{{ $s->speakers->pluck('name')->implode(', ') ?: '—' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2"><x-eo.status-pill :tone="$lTone" class="!text-[9px]">{{ $s->statusLabel() }}</x-eo.status-pill></td>
                                        <td class="whitespace-nowrap px-3 py-2 text-eo-muted">{{ $s->bookedCount() }}{{ $s->capacity ? '/'.number_format($s->capacity) : '' }}</td>
                                        <td class="whitespace-nowrap px-3 py-2">
                                            <div class="flex items-center justify-end gap-1">
                                                <button type="button" wire:click.stop="editSession({{ $s->id }})" class="rounded-md p-1 text-eo-muted transition hover:bg-eo-workspace hover:text-eo-text" title="Edit">✎</button>
                                                <button type="button" wire:click.stop="duplicateSession({{ $s->id }})" class="rounded-md p-1 text-eo-muted transition hover:bg-eo-workspace hover:text-eo-text" title="Duplicate">⧉</button>
                                                <x-confirm title="Delete “{{ $s->title }}”?" confirm="Delete" run="$wire.deleteSession({{ $s->id }})"
                                                           class="rounded-md p-1 text-eo-muted transition hover:bg-eo-risk-soft hover:text-eo-risk" title="Delete">✕</x-confirm>
                                            </div>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="9" class="px-3 py-10 text-center text-eo-muted">
                                            {{ trim($sessionSearch) !== '' ? 'No sessions match "'.$sessionSearch.'".' : 'No sessions yet.' }}
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                {{-- ═══ SPEAKER VIEW ═══ every roster speaker's schedule
                     across the whole event — who's assigned, who isn't,
                     what's missing, what's unconfirmed, and same-day
                     conflicts the board already knows about. Row click
                     opens the Inspector, same as everywhere else; no drag,
                     no new cross-day conflict engine. ═══ --}}
                @elseif ($view === 'speakers')
                    @php
                        $spkTotal = $speakerBoard->count();
                        $spkAssigned = $speakerBoard->where('sessionCount', '>', 0)->count();
                        $spkNeedsAction = $speakerBoard->where('needsAction', true)->count();
                    @endphp

                    <div class="grid grid-cols-4 divide-x divide-eo-line border-b border-eo-line text-center">
                        <div class="px-3 py-3"><p class="text-[18px] font-black text-eo-text">{{ $spkTotal }}</p><p class="text-[10px] text-eo-muted">{{ str('Speaker')->plural($spkTotal) }}</p></div>
                        <div class="px-3 py-3"><p class="text-[18px] font-black text-eo-text">{{ $spkAssigned }}</p><p class="text-[10px] text-eo-muted">Assigned</p></div>
                        <div class="px-3 py-3"><p class="text-[18px] font-black {{ $spkNeedsAction ? 'text-eo-warn-ink' : 'text-eo-text' }}">{{ $spkNeedsAction }}</p><p class="text-[10px] text-eo-muted">Need action</p></div>
                        <div class="px-3 py-3"><p class="text-[18px] font-black {{ $unassignedSessions->isNotEmpty() ? 'text-eo-risk-ink' : 'text-eo-text' }}">{{ $unassignedSessions->count() }}</p><p class="text-[10px] text-eo-muted">Unassigned {{ str('session')->plural($unassignedSessions->count()) }}</p></div>
                    </div>

                    <div class="flex items-center justify-between border-b border-eo-line px-4 py-2.5">
                        <p class="text-[11px] text-eo-muted">Speaker profiles and billing live on the Speakers tab — add someone there and they appear here the moment they're billed on a session.</p>
                        <a href="{{ route('events.hub', [$event, 'tab' => 'speakers']) }}" class="shrink-0 text-[11px] font-semibold text-eo-teal-ink hover:underline">＋ Add Speaker</a>
                    </div>

                    {{-- Highlight missing speaker assignment: sessions
                         nobody is billed on yet, surfaced up front rather
                         than only discoverable by scrolling every speaker. --}}
                    @if ($unassignedSessions->isNotEmpty())
                        <div class="border-b border-eo-line bg-eo-risk-soft/40 px-4 py-3">
                            <p class="mb-1.5 text-[11px] font-bold text-eo-risk-ink">⚠ {{ $unassignedSessions->count() }} {{ str('session')->plural($unassignedSessions->count()) }} with no speaker billed</p>
                            <div class="flex flex-wrap gap-1.5">
                                @foreach ($unassignedSessions as $u)
                                    <button type="button" wire:click="selectSession({{ $u->id }})"
                                            class="rounded-lg border border-eo-risk/25 bg-white px-2.5 py-1 text-[11px] font-semibold text-eo-text transition hover:border-eo-risk/50">
                                        {{ substr($u->starts_at, 0, 5) }} · {{ $u->title }}
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <div class="divide-y divide-eo-line">
                        @forelse ($speakerBoard as $row)
                            @php
                                $sp = $row['speaker'];
                                $spTone = match (true) {
                                    $sp->status === 'declined' || $sp->status === 'cancelled' => 'risk',
                                    $sp->status === 'confirmed' => 'ok',
                                    default => 'pending',
                                };
                            @endphp
                            <div class="p-4">
                                <details>
                                    <summary class="flex cursor-pointer list-none items-center gap-3">
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-eo-navy text-[11px] font-bold text-white">{{ $sp->initials() }}</span>
                                        <span class="min-w-0 flex-1">
                                            <span class="flex flex-wrap items-center gap-1.5">
                                                <span class="text-[13px] font-bold text-eo-text">{{ $sp->name }}</span>
                                                @if ($row['worstSeverity'] === 'medium')
                                                    <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn/50" title="Needs attention"></span>
                                                @endif
                                                @if ($sp->is_keynote)<span title="Keynote">⭐</span>@endif
                                                <x-eo.status-pill :tone="$spTone" class="!text-[9px]">{{ ucfirst($sp->status) }}</x-eo.status-pill>
                                                @if ($row['hasConflict'])
                                                    <x-eo.status-pill tone="risk" class="!text-[9px]">Conflict</x-eo.status-pill>
                                                @elseif ($row['worstSeverity'] === 'high')
                                                    <x-eo.status-pill tone="warn" class="!text-[9px]">High priority</x-eo.status-pill>
                                                @endif
                                                @if ($row['unconfirmedCount'])<x-eo.status-pill tone="warn" class="!text-[9px]">{{ $row['unconfirmedCount'] }} unconfirmed</x-eo.status-pill>@endif
                                                @if ($row['missingDetails']->isNotEmpty())<x-eo.status-pill tone="warn" class="!text-[9px]">Missing {{ $row['missingDetails']->implode(', ') }}</x-eo.status-pill>@endif
                                            </span>
                                            <span class="mt-0.5 block text-[10.5px] text-eo-muted">{{ $sp->title ?: 'No title on file' }}{{ $sp->organization ? ' · '.$sp->organization : '' }} — {{ $row['sessionCount'] }} {{ str('session')->plural($row['sessionCount']) }}</span>
                                        </span>
                                        <span class="shrink-0 text-[10px] font-semibold text-eo-teal-ink">Details ▾</span>
                                    </summary>
                                    <div class="mt-3 grid gap-x-6 gap-y-1 rounded-xl bg-eo-workspace px-3.5 py-3 text-[11.5px] sm:grid-cols-2">
                                        <p><span class="text-eo-muted">Topic</span> — {{ $sp->topic ?: 'Not set' }}</p>
                                        <p><span class="text-eo-muted">Email</span> — {{ $sp->email ?: 'Not set' }}</p>
                                        <p><span class="text-eo-muted">Phone</span> — {{ $sp->phone ?: 'Not set' }}</p>
                                        <p><span class="text-eo-muted">Fee</span> — {{ $sp->fee_cents ? number_format($sp->fee_cents / 100, 2) : 'Not set' }}</p>
                                    </div>
                                </details>

                                <div class="ms-12 mt-2.5 space-y-1.5">
                                    @forelse ($row['rows'] as $r)
                                        @php
                                            $s = $r['session'];
                                            $rTone = match (true) {
                                                $r['hasConflict'] => 'risk',
                                                in_array($s->status, ['confirmed', 'final'], true) => 'ok',
                                                in_array($s->status, ['needs_review', 'waiting_speaker'], true) => 'warn',
                                                default => 'pending',
                                            };
                                        @endphp
                                        <button type="button" wire:click="selectSession({{ $s->id }})"
                                                class="flex w-full items-center gap-3 rounded-xl border border-eo-line bg-white px-3 py-2 text-left transition hover:border-eo-teal/30">
                                            <span class="w-12 shrink-0 font-mono text-[11px] text-eo-muted">{{ $r['missingTime'] ? '—' : substr($s->starts_at, 0, 5) }}</span>
                                            <span class="min-w-0 flex-1">
                                                <span class="flex items-center gap-1.5">
                                                    @if ($r['severity'] === 'critical')
                                                        <span class="shrink-0 text-eo-risk" title="Critical — scheduling conflict">⚠</span>
                                                    @elseif ($r['severity'] === 'high')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn" title="High priority"></span>
                                                    @elseif ($r['severity'] === 'medium')
                                                        <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-warn/50" title="Needs attention"></span>
                                                    @endif
                                                    <span class="truncate text-[12px] font-bold text-eo-text">{{ $s->title }}</span>
                                                </span>
                                                <span class="block truncate text-[10.5px] text-eo-muted">
                                                    {{ $s->day?->date?->format('D j M') ?? '—' }} ·
                                                    {{ $r['missingRoom'] ? 'No room' : $s->room?->name }}
                                                    @if ($s->track) · {{ $s->track }} @endif
                                                </span>
                                            </span>
                                            <x-eo.status-pill :tone="$rTone" class="shrink-0 !text-[9px]">{{ $s->statusLabel() }}</x-eo.status-pill>
                                        </button>
                                    @empty
                                        <p class="text-[11px] italic text-eo-muted">Not yet assigned to a session.</p>
                                    @endforelse
                                </div>
                            </div>
                        @empty
                            <x-empty icon="users" class="!border-0 !shadow-none" title="No speakers on the roster yet"
                                     hint="Bill someone onto a session and they'll appear here, or add them directly on the Speakers tab.">
                                <x-slot:actions>
                                    <a href="{{ route('events.hub', [$event, 'tab' => 'speakers']) }}" class="eo-btn eo-btn-primary eo-btn-sm">＋ Add Speaker</a>
                                </x-slot:actions>
                            </x-empty>
                        @endforelse
                    </div>
                @endif
            </div>

            {{-- ═══ RIGHT — INSPECTOR / INSIGHTS ═══ --}}
            <aside class="eo-domain-card self-start !p-4">
                @if ($selectedSession)
                    @php
                        $ss = $selectedSession;
                        $ssTone = match (true) {
                            isset($conflicts[$ss->id]) => 'risk',
                            $ss->status === 'final' => 'ok',
                            $ss->status === 'confirmed' => 'ok',
                            $ss->status === 'needs_review' => 'warn',
                            $ss->status === 'waiting_speaker' => 'warn',
                            default => 'pending',
                        };
                    @endphp
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <p class="eo-label">Inspector</p>
                        <button type="button" wire:click="selectSession(null)" class="grid h-6 w-6 place-items-center rounded-full text-eo-muted transition hover:bg-eo-workspace" title="Close">✕</button>
                    </div>

                    <x-eo.status-pill :tone="$ssTone">{{ $ss->statusLabel() }}</x-eo.status-pill>
                    <h3 class="eo-font mt-2 text-[16px] font-bold leading-snug text-eo-text">{{ $ss->title }}</h3>

                    <div class="mt-3 divide-y divide-eo-line text-[12px]">
                        <div class="flex items-center justify-between py-2"><span class="font-semibold text-eo-muted">Speaker{{ $ss->speakers->count() === 1 ? '' : 's' }}</span><span class="text-right text-eo-text">{{ $ss->speakers->isNotEmpty() ? $ss->speakers->pluck('name')->implode(', ') : '—' }}</span></div>
                        <div class="flex items-center justify-between py-2"><span class="font-semibold text-eo-muted">Room</span><span class="text-eo-text">{{ $ss->room?->name ?? '—' }}</span></div>
                        <div class="flex items-center justify-between py-2"><span class="font-semibold text-eo-muted">Start</span><span class="font-mono text-eo-text">{{ substr($ss->starts_at, 0, 5) }}</span></div>
                        <div class="flex items-center justify-between py-2"><span class="font-semibold text-eo-muted">End</span><span class="font-mono text-eo-text">{{ substr($ss->ends_at, 0, 5) }}</span></div>
                        <div class="flex items-center justify-between py-2"><span class="font-semibold text-eo-muted">Capacity</span><span class="text-eo-text">{{ $ss->bookedCount() }}{{ $ss->capacity ? ' / '.number_format($ss->capacity) : '' }}</span></div>
                        <div class="flex items-center justify-between py-2"><span class="font-semibold text-eo-muted">Approval status</span><x-eo.status-pill :tone="$ssTone" class="!text-[9px]">{{ $ss->statusLabel() }}</x-eo.status-pill></div>
                    </div>

                    {{-- Dependencies — everything this session is waiting on,
                         read off data that already exists elsewhere (the day's
                         conflicts, the speaker roster, the room's equipment
                         list) — nothing here is invented for this panel. --}}
                    <div class="mt-3 border-t border-eo-line pt-3">
                        <p class="eo-label mb-1.5">Dependencies</p>
                        @if (empty($dependencies))
                            <p class="flex items-center gap-1.5 text-[11.5px] text-eo-ok-ink"><span>✓</span> Nothing outstanding</p>
                        @else
                            <ul class="space-y-1.5">
                                @foreach ($dependencies as $dep)
                                    <li class="flex items-start gap-1.5 text-[11px]">
                                        <span class="mt-0.5 shrink-0 {{ $dep['tone'] === 'risk' ? 'text-eo-risk' : 'text-eo-warn-ink' }}">⚠</span>
                                        <span class="text-eo-text">{{ $dep['label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Move room — a select, not a modal: the room half of
                         what dragging the block onto a lane already does. --}}
                    <div class="mt-3 border-t border-eo-line pt-3">
                        <label class="eo-eo-label !mb-1" for="insp-room">Move room</label>
                        <select id="insp-room" class="eo-select h-9 text-[12px]"
                                onchange="window.__agendaWire.assignRoom({{ $ss->id }}, this.value || null)">
                            <option value="">— Unassigned —</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected($room->id === $ss->room_id)>{{ $room->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-1.5">
                        <button type="button" wire:click="editSession({{ $ss->id }})" class="eo-btn eo-btn-primary eo-btn-sm">Edit</button>
                        <button type="button" wire:click="duplicateSession({{ $ss->id }})" class="eo-btn eo-btn-ghost eo-btn-sm">Duplicate</button>
                        <button type="button" wire:click="quickAddSpeaker({{ $ss->id }})" class="eo-btn eo-btn-ghost eo-btn-sm">＋ Speaker</button>
                        @if ($ss->status === 'final')
                            <button type="button" disabled class="eo-btn eo-btn-ghost eo-btn-sm opacity-50">Published</button>
                        @else
                            <button type="button" wire:click="publishSession({{ $ss->id }})" class="eo-btn eo-btn-secondary eo-btn-sm">Publish</button>
                        @endif
                    </div>
                @else
                    {{-- Nothing selected — the day's insights, exactly as before. --}}
                    <p class="eo-label">Day insights</p>

                    <div class="mt-2">
                        <p class="eo-font text-[13px] font-bold text-eo-text">Today overview</p>
                        <p class="mt-0.5 text-[11px] text-eo-muted">{{ $day?->date?->format('l, j M') ?? 'No day selected' }}</p>

                        @php $r = 2 * M_PI * 26; $done = 0; @endphp
                        <div class="mt-3 flex items-center gap-3.5">
                            <span class="relative grid h-[74px] w-[74px] shrink-0 place-items-center">
                                <svg class="h-[74px] w-[74px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                                    <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-eo-bg)" stroke-width="7" />
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
                                    <span class="block text-[15px] font-black leading-none text-eo-text">{{ $insights['total'] }}</span>
                                </span>
                            </span>

                            <ul class="min-w-0 flex-1 space-y-1">
                                @forelse ($insights['byStatus'] as $row)
                                    <li class="flex items-center gap-1.5 text-[11px]">
                                        <span class="h-2 w-2 shrink-0 rounded-full" style="background: {{ $row['hex'] }}"></span>
                                        <span class="w-4 shrink-0 font-bold tabular-nums text-eo-text">{{ $row['count'] }}</span>
                                        <span class="truncate text-eo-muted">{{ $row['label'] }}</span>
                                    </li>
                                @empty
                                    <li class="text-[11px] text-eo-muted">Nothing scheduled.</li>
                                @endforelse
                            </ul>
                        </div>
                    </div>

                    {{-- Three things that each need a person to do something. --}}
                    <div class="mt-3 space-y-1.5">
                        @foreach ([
                            ['count' => $insights['awaitingSpeakers'], 'label' => 'Awaiting speakers', 'tone' => 'navy'],
                            ['count' => $insights['capacityRisks'], 'label' => 'Capacity risks', 'tone' => 'warn'],
                            ['count' => $insights['doubleBookings'], 'label' => 'Double bookings', 'tone' => 'risk'],
                        ] as $alert)
                            @php
                                [$ring, $ink] = $alert['count'] === 0
                                    ? ['ring-eo-line', 'text-eo-muted']
                                    : match ($alert['tone']) {
                                        'risk' => ['ring-eo-risk/30 bg-eo-risk-soft/40', 'text-eo-risk-ink'],
                                        'warn' => ['ring-eo-warn/30 bg-eo-warn-soft/40', 'text-eo-warn-ink'],
                                        default => ['ring-eo-line', 'text-eo-text'],
                                    };
                            @endphp
                            <div class="flex items-center gap-3 rounded-xl px-2.5 py-2 ring-1 {{ $ring }}">
                                <span class="eo-font text-[18px] font-black leading-none {{ $ink }}">{{ $alert['count'] }}</span>
                                <span class="min-w-0 flex-1 truncate text-[11px] font-semibold text-eo-text">{{ $alert['label'] }}</span>
                            </div>
                        @endforeach
                    </div>

                    <div class="mt-3 rounded-xl bg-eo-workspace p-3">
                        <div class="flex items-baseline justify-between">
                            <span class="eo-font text-[18px] font-black leading-none text-eo-text">{{ $insights['pct'] }}%</span>
                            <span class="text-[11px] font-semibold text-eo-muted">Agenda complete</span>
                        </div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-eo-bg">
                            <div class="h-full rounded-full bg-eo-ok" style="width: {{ $insights['pct'] }}%"></div>
                        </div>
                        <p class="mt-1.5 text-[10.5px] text-eo-muted">{{ $insights['settled'] }} of {{ $insights['total'] }} confirmed on this day</p>
                    </div>

                    {{-- What is on next, but only on the day that is actually today. --}}
                    @if ($insights['next'])
                        <p class="eo-label mt-3 mb-1.5">Up next</p>
                        @php $n = $insights['next']; [$nLegend, $nHex] = \App\Livewire\Hub\AgendaTab::PALETTE[$n->type] ?? ['Session', '#3B82F6']; @endphp
                        <button type="button" wire:click="selectSession({{ $n->id }})" class="block w-full rounded-xl bg-eo-workspace p-3 text-left transition hover:-translate-y-px hover:shadow-sm">
                            <div class="flex items-center gap-2">
                                @if ($insights['nextIn'] !== null && $insights['nextIn'] > 0)
                                    <span class="text-[10px] font-bold uppercase tracking-wide text-eo-muted">In {{ $insights['nextIn'] }} min</span>
                                @endif
                                <span class="ms-auto inline-block rounded-full px-2 py-0.5 text-[9px] font-bold uppercase tracking-wide text-white" style="background: {{ $nHex }}">{{ $nLegend }}</span>
                            </div>
                            <p class="eo-font mt-1.5 text-[13px] font-bold leading-snug text-eo-text">{{ $n->title }}</p>
                            <p class="mt-1 text-[11px] text-eo-muted">{{ substr($n->starts_at, 0, 5) }}–{{ substr($n->ends_at, 0, 5) }}@if ($n->room) · {{ $n->room->name }}@endif</p>
                        </button>
                    @endif

                    <a href="{{ route('events.run-of-show', $event) }}"
                       class="mt-3 flex h-10 items-center justify-center gap-2 rounded-xl bg-eo-navy text-[12px] font-bold text-white transition hover:bg-eo-navy-deep">
                        View Run of Show →
                    </a>
                @endif
            </aside>
        </div>

        {{-- ═══ BOTTOM — COMMAND BAR ═══ everything that acts on the whole
             day or the whole agenda, rather than one session. ═══ --}}
        <div class="mt-3 flex flex-wrap items-center gap-2 rounded-2xl bg-eo-card p-2.5 shadow-[var(--shadow-eo)]">
            <span class="flex items-center gap-1.5 px-1.5 text-[11px] font-semibold text-eo-muted" wire:loading.remove wire:target="saveSession,moveSession,toggleFlag,assignRoom,publishSession,duplicateSession,confirmDay">
                <span class="h-1.5 w-1.5 rounded-full bg-eo-ok"></span> Autosaved
            </span>
            <span class="flex items-center gap-1.5 px-1.5 text-[11px] font-semibold text-eo-muted" wire:loading wire:target="saveSession,moveSession,toggleFlag,assignRoom,publishSession,duplicateSession,confirmDay">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-eo-gold"></span> Saving…
            </span>

            <div class="flex rounded-xl border border-eo-line bg-white p-0.5">
                @foreach ([
                    ['timeline', 'Timeline', 'chart'],
                    ['rooms', 'Rooms', 'building'],
                    ['tracks', 'Tracks', 'columns'],
                    ['sessions', 'Sessions', 'list'],
                    ['speakers', 'Speakers', 'users'],
                    ['program', 'Programme', 'calendar'],
                ] as [$key, $label, $icon])
                    <button type="button" wire:click="setView('{{ $key }}')"
                            @class(['flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition', 'bg-eo-navy text-white' => $view === $key, 'text-eo-muted hover:text-eo-text' => $view !== $key])>
                        <x-icon :name="$icon" class="h-3 w-3" /> {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($view === 'program')
                <div class="flex rounded-xl border border-eo-line bg-white p-0.5" title="Public hides setup, press and registration">
                    <button type="button" wire:click="setAudience('internal')" @class(['rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition', 'bg-eo-navy text-white' => $audience === 'internal', 'text-eo-muted hover:text-eo-text' => $audience !== 'internal'])>Internal</button>
                    <button type="button" wire:click="setAudience('public')" @class(['rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition', 'bg-eo-navy text-white' => $audience === 'public', 'text-eo-muted hover:text-eo-text' => $audience !== 'public'])>Public</button>
                </div>
            @endif

            <button type="button" wire:click="$toggle('showImport')" class="eo-btn eo-btn-ghost eo-btn-sm">⇪ Import CSV</button>
            <a href="{{ route('events.run-of-show', $event) }}" class="eo-btn eo-btn-ghost eo-btn-sm" title="Open the live show-day cue sheet"><x-icon name="calendar" class="h-3.5 w-3.5" /> Run of Show</a>

            <details class="relative">
                <summary class="eo-btn eo-btn-ghost eo-btn-sm cursor-pointer list-none">Export ▾</summary>
                <div class="absolute left-0 z-30 mt-1 w-56 overflow-hidden rounded-xl border border-eo-line bg-white py-1 shadow-lg">
                    <p class="px-3 pb-1 pt-2 text-[9.5px] font-bold uppercase tracking-[0.1em] text-eo-muted">This day</p>
                    @if ($view === 'program')
                        <a href="{{ route('events.agenda.program.pdf', [$event, 'day' => $day?->id, 'audience' => $audience]) }}" class="block px-3 py-2 text-[11.5px] text-eo-text transition hover:bg-eo-workspace">Programme PDF</a>
                    @else
                        <a href="{{ route('events.agenda.timeline.pdf', [$event, 'day' => $day?->id]) }}" class="block px-3 py-2 text-[11.5px] text-eo-text transition hover:bg-eo-workspace">Timeline PDF</a>
                    @endif
                    <a href="{{ route('events.run-of-show.pdf', [$event, 'day' => $day?->id]) }}" class="block px-3 py-2 text-[11.5px] text-eo-text transition hover:bg-eo-workspace">Run of Show PDF</a>
                    <p class="border-t border-eo-line px-3 pb-1 pt-2 text-[9.5px] font-bold uppercase tracking-[0.1em] text-eo-muted">All days</p>
                    <a href="{{ route('events.agenda.timeline.pdf', $event) }}" class="block px-3 py-2 text-[11.5px] text-eo-text transition hover:bg-eo-workspace">Timeline PDF</a>
                    <a href="{{ route('events.agenda.program.pdf', [$event, 'audience' => $audience]) }}" class="block px-3 py-2 text-[11.5px] text-eo-text transition hover:bg-eo-workspace">Programme PDF</a>
                    <a href="{{ route('events.agenda.master.pdf', $event) }}" class="block px-3 py-2 text-[11.5px] text-eo-text transition hover:bg-eo-workspace">Master Schedule — incl. crew</a>
                </div>
            </details>

            <button type="button" wire:click="$toggle('showClashSummary')"
                    @class(['eo-btn eo-btn-sm', 'eo-btn-navy' => $showClashSummary, 'eo-btn-ghost' => ! $showClashSummary])>
                ⚠ Clash Check
                @if ($clashSummary->isNotEmpty())
                    <span class="ms-0.5 rounded-full bg-eo-risk px-1.5 text-[9.5px] font-black text-white">{{ $clashSummary->count() }}</span>
                @endif
            </button>

            <span class="eo-btn eo-btn-ghost eo-btn-sm cursor-not-allowed opacity-50" title="A whole-programme publish state is staged for a later phase">Publish Programme · Soon</span>

            <span class="ms-auto flex items-center gap-2">
                @if ($day)
                    <details class="relative">
                        <summary class="eo-btn eo-btn-secondary eo-btn-sm cursor-pointer list-none">Day tools ▾</summary>
                        <div class="absolute right-0 z-30 mt-1 w-60 overflow-hidden rounded-xl border border-eo-line bg-white py-1 shadow-lg">
                            <button type="button" wire:click="confirmDay" class="block w-full px-3 py-2 text-left text-[11.5px] text-eo-text transition hover:bg-eo-workspace">
                                ✓ Confirm every session
                                <span class="block text-[10px] text-eo-muted">Signs off whatever is still draft</span>
                            </button>
                            <button type="button" wire:click="duplicateDay({{ $day->id }})" class="block w-full px-3 py-2 text-left text-[11.5px] text-eo-text transition hover:bg-eo-workspace">
                                ⧉ Duplicate this day
                                <span class="block text-[10px] text-eo-muted">Same running order, one day later, back to draft</span>
                            </button>
                            <x-confirm title="Delete this day?"
                                       body="Its {{ $daySessions }} {{ str('session')->plural($daySessions) }} go with it, speakers and all. The event's own dates are not touched."
                                       confirm="Delete the day" tone="danger"
                                       run="$wire.deleteDay({{ $day->id }})"
                                       class="block w-full px-3 py-2 text-left text-[11.5px] text-eo-risk-ink transition hover:bg-eo-risk-soft">
                                ✕ Delete this day
                            </x-confirm>
                        </div>
                    </details>
                @endif
            </span>
        </div>

        {{-- ═══ CLASH CHECK SUMMARY ═══ every finding across the whole
             event, not just today — the same findings the day banner, the
             pins, every Agenda view and the Inspector already read, just
             laid out as one list instead of scattered per-block. ═══ --}}
        @if ($showClashSummary)
            <div class="mt-3 eo-domain-card !p-0 overflow-hidden">
                <div class="flex items-center justify-between border-b border-eo-line px-4 py-3">
                    <div>
                        <p class="text-[13px] font-bold text-eo-text">
                            {{ $clashSummary->isEmpty() ? 'No conflicts found' : $clashSummary->count().' '.str('finding')->plural($clashSummary->count()).' across the event' }}
                        </p>
                        <p class="text-[10.5px] text-eo-muted">Every day, every room, every billed speaker — worst first.</p>
                    </div>
                    <button type="button" wire:click="$toggle('showClashSummary')" class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-eo-muted transition hover:bg-eo-workspace" title="Close">✕</button>
                </div>

                @if ($clashSummary->isEmpty())
                    <p class="flex items-center gap-2 px-4 py-6 text-[12px] text-eo-ok-ink">✓ Nothing needs attention right now.</p>
                @else
                    <div class="scrollbar-none max-h-[360px] divide-y divide-eo-line overflow-y-auto">
                        @foreach ($clashSummary as $row)
                            @php
                                $sevTone = match ($row['severity']) {
                                    'critical' => 'risk', 'high' => 'risk', 'medium' => 'warn', default => 'pending',
                                };
                            @endphp
                            <button type="button" wire:click="selectSession({{ $row['primarySessionId'] }})"
                                    class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-eo-workspace">
                                <x-eo.status-pill :tone="$sevTone" class="mt-0.5 shrink-0 !text-[9px] uppercase">{{ $row['severity'] }}</x-eo.status-pill>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.04em] text-eo-muted">{{ $row['typeLabel'] }}</span>
                                        <span class="text-[10.5px] text-eo-muted">{{ $row['dayLabels']->implode(' · ') }}</span>
                                        @if ($row['roomName'])<span class="text-[10.5px] text-eo-muted">· {{ $row['roomName'] }}</span>@endif
                                        @if ($row['speakerName'])<span class="text-[10.5px] text-eo-muted">· {{ $row['speakerName'] }}</span>@endif
                                    </span>
                                    <span class="mt-0.5 block text-[12.5px] text-eo-text">{{ $row['message'] }}</span>
                                </span>
                                <span class="shrink-0 self-center text-[10.5px] font-bold text-eo-teal-ink">Open →</span>
                            </button>
                        @endforeach
                    </div>
                @endif
            </div>
        @endif
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
                                <label class="eo-eo-label !mb-1.5" for="m-title">Title <span class="text-eo-risk-ink">*</span></label>
                                <input id="m-title" type="text" wire:model="title" class="eo-input !py-2.5" placeholder="Opening Keynote">
                                @error('title') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                            </div>
                            <div class="sm:w-44">
                                <label class="eo-eo-label !mb-1.5" for="m-type">Type</label>
                                <input id="m-type" type="text" list="session-types" wire:model="type" class="eo-input !py-2.5"
                                       autocomplete="off" placeholder="Keynote, Setup, Rehearsal…">
                                <datalist id="session-types">
                                    @foreach ($typeOptions as $t)<option value="{{ $t }}"></option>@endforeach
                                </datalist>
                                <p class="mt-1 text-[10.5px] text-eo-muted">Pick one or type your own.</p>
                                @error('type') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        {{-- The track is not a label: it decides where the session
                             sits on the programme and whether a delegate sees it
                             at all. Free text, because every event invents one. --}}
                        <div>
                            <label class="eo-eo-label !mb-1.5" for="m-track">Programme track <span class="font-normal normal-case tracking-normal text-eo-muted">optional</span></label>
                            <input id="m-track" type="text" list="session-tracks" wire:model="track" class="eo-input !py-2.5"
                                   autocomplete="off" placeholder="Main Stage, Track A, Registration…">
                            <datalist id="session-tracks">
                                @foreach ($trackOptions as $name => $does)<option value="{{ $name }}">{{ $does }}</option>@endforeach
                            </datalist>
                            <p class="mt-1 text-[10.5px] text-eo-muted">Main Stage heads its slot; Registration, Setup, Media and Partnerships stay off the public programme.</p>
                            @error('track') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="eo-eo-label !mb-1.5">Format</label>
                            <div class="grid grid-cols-3 gap-2.5">
                                @foreach (\App\Support\Taxonomy::options('session_format') as $val => $lbl)
                                    <button type="button" wire:click="$set('format', '{{ $val }}')"
                                            @class([
                                                'rounded-2xl border py-2.5 text-sm font-bold transition',
                                                'border-eo-navy bg-eo-navy text-white' => $format === $val,
                                                'border-eo-line text-eo-muted hover:text-eo-text' => $format !== $val,
                                            ])>{{ $lbl }}</button>
                                @endforeach
                            </div>
                        </div>

                        <div>
                            <label class="eo-eo-label !mb-1.5" for="m-status">Status</label>
                            <select id="m-status" wire:model="status" class="eo-select !py-2.5">
                                @foreach (\App\Models\EventAgendaSession::STATUS_META as $val => [$lbl, $settled])
                                    <option value="{{ $val }}">{{ $lbl }}{{ $settled ? '' : ' — not for distribution' }}</option>
                                @endforeach
                            </select>
                            @error('status') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
                        </div>

                        <div>
                            <label class="eo-eo-label !mb-1.5" for="m-cap">Seat capacity <span class="font-normal normal-case tracking-normal text-eo-muted">optional — limits attendee sign-ups</span></label>
                            <input id="m-cap" type="number" min="0" wire:model="capacity" class="eo-input !py-2.5" placeholder="Leave blank for unlimited">
                        </div>

                        <div class="grid gap-4 sm:grid-cols-3">
                            <div>
                                <label class="eo-eo-label !mb-1.5" for="m-day">Day <span class="text-eo-risk-ink">*</span></label>
                                <select id="m-day" wire:model="agenda_day_id" class="eo-select !py-2.5">
                                    @foreach ($days as $d)<option value="{{ $d->id }}">Day {{ $loop->iteration }} — {{ $d->date?->format('D j M') }}</option>@endforeach
                                </select>
                            </div>
                            <div>
                                <label class="eo-eo-label !mb-1.5" for="m-start">Start <span class="text-eo-risk-ink">*</span></label>
                                <input id="m-start" type="time" wire:model="starts_at" class="eo-input !py-2.5">
                            </div>
                            <div>
                                <label class="eo-eo-label !mb-1.5" for="m-end">End <span class="text-eo-risk-ink">*</span></label>
                                <input id="m-end" type="time" wire:model="ends_at" class="eo-input !py-2.5">
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div class="sm:col-span-2">
                                <div class="mb-1.5 flex items-center justify-between">
                                    <label class="eo-eo-label !mb-0">Speaker line-up</label>
                                    <span class="text-[10.5px] text-eo-muted">Pick from the roster or type a new name — new people are added to Speakers automatically</span>
                                </div>

                                <datalist id="speaker-roster">
                                    @foreach ($speakerOptions as $sp)<option value="{{ $sp }}"></option>@endforeach
                                </datalist>

                                <div class="space-y-2">
                                    @forelse ($speakerRows as $i => $row)
                                        <div class="flex items-center gap-2" wire:key="spk-{{ $i }}">
                                            <input type="text" list="speaker-roster" wire:model.blur="speakerRows.{{ $i }}.name"
                                                   class="eo-input !py-2.5 flex-1" placeholder="Speaker name">
                                            <select wire:model="speakerRows.{{ $i }}.role" class="eo-select !py-2.5 w-40 shrink-0">
                                                @foreach ($roles as $value => $label)
                                                    <option value="{{ $value }}">{{ $label }}</option>
                                                @endforeach
                                            </select>
                                            <button type="button" wire:click="removeSpeakerRow({{ $i }})"
                                                    class="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg text-eo-muted transition hover:bg-eo-risk-soft hover:text-eo-risk"
                                                    title="Remove">✕</button>
                                        </div>
                                    @empty
                                        <p class="rounded-xl border border-dashed border-eo-line px-3 py-3 text-center text-[11px] text-eo-muted">
                                            No speakers billed yet — a panel can carry a moderator plus several panellists.
                                        </p>
                                    @endforelse
                                </div>

                                <div class="mt-2 flex flex-wrap gap-2">
                                    <button type="button" wire:click="addSpeakerRow('keynote')" class="rounded-lg border border-eo-line bg-white px-2.5 py-1.5 text-[11px] font-semibold text-eo-text transition hover:border-eo-gold/40">＋ Keynote</button>
                                    <button type="button" wire:click="addSpeakerRow('moderator')" class="rounded-lg border border-eo-line bg-white px-2.5 py-1.5 text-[11px] font-semibold text-eo-text transition hover:border-eo-gold/40">＋ Moderator</button>
                                    <button type="button" wire:click="addSpeakerRow('panelist')" class="rounded-lg border border-eo-line bg-white px-2.5 py-1.5 text-[11px] font-semibold text-eo-text transition hover:border-eo-gold/40">＋ Panellist</button>
                                </div>
                            </div>
                            <div>
                                <label class="eo-eo-label !mb-1.5" for="m-room">Venue / Room</label>
                                <select id="m-room" wire:model="room_id" class="eo-select mb-2 !py-2.5">
                                    <option value="">— Select a venue —</option>
                                    @foreach ($rooms as $room)<option value="{{ $room->id }}">{{ $room->name }}</option>@endforeach
                                </select>
                                <input type="text" wire:model="newRoomName" class="eo-input !py-2.5" placeholder="…or type a room">
                            </div>
                        </div>

                        <div>
                            <label class="eo-eo-label !mb-1.5" for="m-desc">Description / Notes</label>
                            <textarea id="m-desc" wire:model="description" rows="2" class="eo-textarea !py-2.5" placeholder="Optional details…"></textarea>
                        </div>
                    </div>

                    {{-- footer --}}
                    <div class="flex items-center justify-end gap-3 rounded-b-[24px] border-t border-eo-line bg-eo-workspace/60 px-6 py-4">
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold text-eo-text">
                            <input type="checkbox" wire:model="flagged" class="h-4 w-4 rounded border-eo-line text-eo-gold focus:ring-eo-gold">
                            <span>🚩 Flag</span>
                        </label>
                        @if ($editingId)
                            <x-confirm title="Delete this session?"
                                       confirm="Delete"
                                       run="$wire.deleteSession({{ $editingId }})"
                                       class="rounded-2xl px-4 py-2.5 text-sm font-bold text-eo-risk-ink transition hover:bg-eo-risk-soft">Delete</x-confirm>
                        @endif
                        <span class="mr-auto"></span>
                        <button type="button" wire:click="closeForm" class="rounded-2xl bg-eo-bg px-6 py-2.5 text-sm font-bold text-eo-text transition hover:bg-eo-line">Cancel</button>
                        <button type="submit" class="rounded-2xl bg-eo-navy px-7 py-2.5 text-sm font-bold text-white shadow-[0_10px_25px_rgba(11,19,34,0.25)] transition hover:bg-eo-navy-deep">{{ $editingId ? 'Save Session' : 'Add Session' }}</button>
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
                // A click that never moved selects the block into the
                // Inspector — it no longer jumps straight to the edit modal;
                // the Inspector's own "Edit" button opens that.
                if (! d.moved) { wire.selectSession(d.id); return; }
                wire.moveSession(d.id, d.newStart, d.roomId === '' ? null : +d.roomId);
            });
        }
    </script>
    @endscript
</div>
