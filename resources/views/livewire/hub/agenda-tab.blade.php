<div class="cx-canvas">
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
        <form wire:submit="import" class="cx-lcard mb-4 flex flex-wrap items-end gap-3 !p-4">
            <div class="flex-1">
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="import-file">CSV file — columns: title, type, start, end, room, speaker, moderator (separate several names with ; )</label>
                <input id="import-file" type="file" wire:model="importFile" accept=".csv,text/csv" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white focus:border-navy-300 focus:outline-none">
                @error('importFile') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-muted hover:text-ink">Cancel</button>
            <button type="submit" class="cx-btn cx-btn-accent" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
        </form>
    @endif

    @if ($days->isEmpty())
        <div class="cx-empty">
            <h3>No agenda days yet</h3>
            <p>Days come from the event's date range — set the dates in Settings and they appear here, or start one now and the programme builds around it.</p>
            <div class="flex items-center justify-center gap-2">
                <button type="button" wire:click="addDay" class="cx-btn cx-btn-accent">＋ Add the first day</button>
                <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" class="cx-btn cx-btn-ghost">Set the event dates</a>
            </div>
        </div>
    @else
        {{-- ══════════ THE BUILDER SHELL ══════════
             Left rail (days, rooms, tracks, filters, add session) · center
             canvas (the timeline or the programme, unchanged underneath) ·
             right inspector (a selected session, or day insights when
             nothing is selected). The command bar runs the full width below
             — everything that acts on the whole day or the whole agenda,
             rather than on one session. --}}
        @include('livewire.hub.agenda.day-strip')

        {{-- ═══ THE TOOLBAR ═══
             The status filters and the Add Session button used to live at the
             bottom of a 240px rail, below Days, Rooms and Tracks — three
             scrolling lists you had to get past to reach the one button you
             press most. They belong on the same line as the board they act on. --}}
        <div class="mb-2 flex flex-wrap items-center gap-2">
            <span class="cx-eyebrow">Show</span>
            @foreach (\App\Models\EventAgendaSession::STATUS_META as $key => [$label, $settledMeta, $hex])
                @php $on = in_array($key, $statusFilter, true); @endphp
                <button type="button" wire:click="toggleStatusFilter('{{ $key }}')"
                        class="cx-chip {{ $on ? 'is-on' : '' }}"
                        style="{{ $on ? 'background:'.$hex.'; border-color:'.$hex.'; color:#fff' : '' }}">{{ $label }}</button>
            @endforeach
            @if ($statusFilter)
                <span class="text-[10.5px] text-muted">Others dimmed on the board</span>
            @endif

            <span class="ms-auto flex items-center gap-2">
                {{-- Filters the board's own lanes, not a separate room list. --}}
                <span class="cx-search" style="height:34px">
                    <x-icon name="search" class="h-3.5 w-3.5 text-muted" />
                    <input type="search" wire:model.live.debounce.250ms="venueSearch"
                           placeholder="Filter rooms…" aria-label="Filter rooms" style="width:130px">
                </span>
                <button type="button" wire:click="$toggle('showImport')" class="cx-btn cx-btn-ghost" style="height:34px">⇪ Import CSV</button>
                <button type="button" wire:click="newSession" class="cx-btn cx-btn-accent" style="height:34px">＋ Add Session</button>
            </span>
        </div>

        {{-- The canvas runs full width while you are building. It gives up
             room for the session inspector only once a session is actually
             selected — and takes it straight back when you close it. The
             schedule is what this screen is for; nothing else holds a
             column open just in case. --}}
        <div class="grid items-start gap-3 cx-reveal cx-d1 {{ $selectedSession ? 'xl:grid-cols-[minmax(0,1fr)_300px]' : 'xl:grid-cols-1' }}">

            @include('livewire.hub.agenda.canvas')
            {{-- ═══ RIGHT — INSPECTOR / INSIGHTS ═══ --}}
            {{-- ═══ RIGHT — THE SELECTED SESSION ═══
                 Only rendered when a session is actually selected. Its old
                 "day insights" fallback (a readiness ring and three cards that
                 usually read 0, 0, 0) now lives as one line in the day strip,
                 so nothing holds this column open when there is nothing in it. --}}
            @if ($selectedSession)
            <aside class="cx-panel self-start !p-4">
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
                        $ssTagCls = match ($ssTone) { 'risk' => 'tone-risk', 'ok' => 'tone-ok', 'warn' => 'tone-warn', default => 'tone-info' };
                    @endphp
                    <div class="mb-3 flex items-start justify-between gap-2">
                        <p class="cx-eyebrow">Inspector</p>
                        <button type="button" wire:click="selectSession(null)" class="grid h-6 w-6 place-items-center rounded-full text-muted transition hover:bg-page" title="Close">✕</button>
                    </div>

                    <span class="cx-tag {{ $ssTagCls }}">{{ $ss->statusLabel() }}</span>
                    <h3 class="mt-2 text-[16px] font-bold leading-snug text-ink">{{ $ss->title }}</h3>

                    <div class="mt-3">
                        <div class="cx-field"><span class="cx-fk">Speaker{{ $ss->speakers->count() === 1 ? '' : 's' }}</span><span class="cx-fv">{{ $ss->speakers->isNotEmpty() ? $ss->speakers->pluck('name')->implode(', ') : '—' }}</span></div>
                        <div class="cx-field"><span class="cx-fk">Room</span><span class="cx-fv">{{ $ss->room?->name ?? '—' }}</span></div>
                        <div class="cx-field"><span class="cx-fk">Start</span><span class="cx-fv" style="font-family:var(--cx-mono)">{{ substr($ss->starts_at, 0, 5) }}</span></div>
                        <div class="cx-field"><span class="cx-fk">End</span><span class="cx-fv" style="font-family:var(--cx-mono)">{{ substr($ss->ends_at, 0, 5) }}</span></div>
                        <div class="cx-field"><span class="cx-fk">Capacity</span><span class="cx-fv">{{ $ss->bookedCount() }}{{ $ss->capacity ? ' / '.number_format($ss->capacity) : '' }}</span></div>
                    </div>

                    {{-- Dependencies — everything this session is waiting on,
                         read off data that already exists elsewhere (the day's
                         conflicts, the speaker roster, the room's equipment
                         list) — nothing here is invented for this panel. --}}
                    <div class="mt-3 border-t border-line pt-3">
                        <p class="mb-1.5 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Dependencies</p>
                        @if (empty($dependencies))
                            <p class="flex items-center gap-1.5 text-[11.5px] text-success-ink"><span>✓</span> Nothing outstanding</p>
                        @else
                            <ul class="space-y-1.5">
                                @foreach ($dependencies as $dep)
                                    <li class="flex items-start gap-1.5 text-[11px]">
                                        <span class="mt-0.5 shrink-0 {{ $dep['tone'] === 'risk' ? 'text-danger' : 'text-warning-ink' }}">⚠</span>
                                        <span class="text-ink">{{ $dep['label'] }}</span>
                                    </li>
                                @endforeach
                            </ul>
                        @endif
                    </div>

                    {{-- Move room — a select, not a modal: the room half of
                         what dragging the block onto a lane already does. --}}
                    <div class="mt-3 border-t border-line pt-3">
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="insp-room">Move room</label>
                        <select id="insp-room" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-[12px] text-ink focus:border-navy-300 focus:outline-none"
                                onchange="window.__agendaWire.assignRoom({{ $ss->id }}, this.value || null)">
                            <option value="">— Unassigned —</option>
                            @foreach ($rooms as $room)
                                <option value="{{ $room->id }}" @selected($room->id === $ss->room_id)>{{ $room->name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="mt-4 grid grid-cols-2 gap-1.5">
                        <button type="button" wire:click="editSession({{ $ss->id }})" class="btn-gold btn-sm">Edit</button>
                        <button type="button" wire:click="duplicateSession({{ $ss->id }})" class="btn-ghost btn-sm">Duplicate</button>
                        <button type="button" wire:click="quickAddSpeaker({{ $ss->id }})" class="btn-ghost btn-sm">＋ Speaker</button>
                        @if ($ss->status === 'final')
                            <button type="button" disabled class="btn-ghost btn-sm opacity-50">Published</button>
                        @else
                            <button type="button" wire:click="publishSession({{ $ss->id }})" class="rounded-xl border border-gold-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-gold-700 transition hover:bg-gold-50">Publish</button>
                        @endif
                    </div>
            </aside>
            @endif
        </div>

        {{-- ═══ BOTTOM — COMMAND BAR ═══ everything that acts on the whole
             day or the whole agenda, rather than one session. ═══ --}}
        <div class="cx-lcard mt-3 flex flex-wrap items-center gap-2 !p-2.5">
            <span class="flex items-center gap-1.5 px-1.5 text-[11px] font-semibold text-muted" wire:loading.remove wire:target="saveSession,moveSession,toggleFlag,assignRoom,publishSession,duplicateSession,confirmDay">
                <span class="h-1.5 w-1.5 rounded-full bg-success"></span> Autosaved
            </span>
            <span class="flex items-center gap-1.5 px-1.5 text-[11px] font-semibold text-muted" wire:loading wire:target="saveSession,moveSession,toggleFlag,assignRoom,publishSession,duplicateSession,confirmDay">
                <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-gold-500"></span> Saving…
            </span>

            <div class="cx-seg">
                @foreach ([
                    ['timeline', 'Timeline', 'chart'],
                    ['rooms', 'Rooms', 'building'],
                    ['tracks', 'Tracks', 'columns'],
                    ['sessions', 'Sessions', 'list'],
                    ['speakers', 'Speakers', 'users'],
                    ['program', 'Programme', 'calendar'],
                ] as [$key, $label, $icon])
                    <button type="button" wire:click="setView('{{ $key }}')" aria-pressed="{{ $view === $key ? 'true' : 'false' }}" style="display:inline-flex;align-items:center;gap:5px">
                        <x-icon :name="$icon" class="h-3 w-3" aria-hidden="true" /> {{ $label }}
                    </button>
                @endforeach
            </div>

            @if ($view === 'program')
                <div role="group" aria-label="Programme audience" class="cx-seg" title="Public hides setup, press and registration">
                    <button type="button" wire:click="setAudience('internal')" aria-pressed="{{ $audience === 'internal' ? 'true' : 'false' }}">Internal</button>
                    <button type="button" wire:click="setAudience('public')" aria-pressed="{{ $audience === 'public' ? 'true' : 'false' }}">Public</button>
                </div>
            @endif

            <button type="button" wire:click="$toggle('showImport')" class="btn-ghost btn-sm">⇪ Import CSV</button>
            <a href="{{ route('events.run-of-show', $event) }}" class="btn-ghost btn-sm" title="Open the live show-day cue sheet"><x-icon name="calendar" class="h-3.5 w-3.5" /> Run of Show</a>

            <details class="relative">
                <summary class="btn-ghost btn-sm cursor-pointer list-none">Export ▾</summary>
                <div class="absolute left-0 z-30 mt-1 w-56 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
                    <p class="px-3 pb-1 pt-2 text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">This day</p>
                    @if ($view === 'program')
                        <a href="{{ route('events.agenda.program.pdf', [$event, 'day' => $day?->id, 'audience' => $audience]) }}" class="block px-3 py-2 text-[11.5px] text-ink transition hover:bg-page">Programme PDF</a>
                    @else
                        <a href="{{ route('events.agenda.timeline.pdf', [$event, 'day' => $day?->id]) }}" class="block px-3 py-2 text-[11.5px] text-ink transition hover:bg-page">Timeline PDF</a>
                    @endif
                    <a href="{{ route('events.run-of-show.pdf', [$event, 'day' => $day?->id]) }}" class="block px-3 py-2 text-[11.5px] text-ink transition hover:bg-page">Run of Show PDF</a>
                    <p class="border-t border-line px-3 pb-1 pt-2 text-[9.5px] font-bold uppercase tracking-[0.1em] text-muted">All days</p>
                    <a href="{{ route('events.agenda.timeline.pdf', $event) }}" class="block px-3 py-2 text-[11.5px] text-ink transition hover:bg-page">Timeline PDF</a>
                    <a href="{{ route('events.agenda.program.pdf', [$event, 'audience' => $audience]) }}" class="block px-3 py-2 text-[11.5px] text-ink transition hover:bg-page">Programme PDF</a>
                    <a href="{{ route('events.agenda.master.pdf', $event) }}" class="block px-3 py-2 text-[11.5px] text-ink transition hover:bg-page">Master Schedule — incl. crew</a>
                </div>
            </details>

            <button type="button" wire:click="$toggle('showClashSummary')"
                    @class(['btn-sm', 'btn-navy' => $showClashSummary, 'btn-ghost' => ! $showClashSummary])>
                ⚠ Clash Check
                @if ($clashSummary->isNotEmpty())
                    <span class="ms-0.5 rounded-full bg-danger px-1.5 text-[9.5px] font-black text-white">{{ $clashSummary->count() }}</span>
                @endif
            </button>

            <span class="btn-ghost btn-sm cursor-not-allowed opacity-50" title="A whole-programme publish state is staged for a later phase">Publish Programme · Soon</span>

            <span class="ms-auto flex items-center gap-2">
                @if ($day)
                    <details class="relative">
                        <summary class="cursor-pointer list-none rounded-xl border border-gold-300 bg-white px-3.5 py-1.5 text-xs font-semibold text-gold-700 transition hover:bg-gold-50">Day tools ▾</summary>
                        <div class="absolute right-0 z-30 mt-1 w-60 overflow-hidden rounded-xl border border-line bg-white py-1 shadow-lg">
                            <button type="button" wire:click="confirmDay" class="block w-full px-3 py-2 text-left text-[11.5px] text-ink transition hover:bg-page">
                                ✓ Confirm every session
                                <span class="block text-[10px] text-muted">Signs off whatever is still draft</span>
                            </button>
                            <button type="button" wire:click="duplicateDay({{ $day->id }})" class="block w-full px-3 py-2 text-left text-[11.5px] text-ink transition hover:bg-page">
                                ⧉ Duplicate this day
                                <span class="block text-[10px] text-muted">Same running order, one day later, back to draft</span>
                            </button>
                            <x-confirm title="Delete this day?"
                                       body="Its {{ $daySessions }} {{ str('session')->plural($daySessions) }} go with it, speakers and all. The event's own dates are not touched."
                                       confirm="Delete the day" tone="danger"
                                       run="$wire.deleteDay({{ $day->id }})"
                                       class="block w-full px-3 py-2 text-left text-[11.5px] text-danger-ink transition hover:bg-danger-soft">
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
            <div class="cx-lcard mt-3">
                <div class="flex items-center justify-between border-b border-line px-4 py-3">
                    <div>
                        <p class="text-[13px] font-bold text-ink">
                            {{ $clashSummary->isEmpty() ? 'No conflicts found' : $clashSummary->count().' '.str('finding')->plural($clashSummary->count()).' across the event' }}
                        </p>
                        <p class="text-[10.5px] text-muted">Every day, every room, every billed speaker — worst first.</p>
                    </div>
                    <button type="button" wire:click="$toggle('showClashSummary')" class="grid h-7 w-7 shrink-0 place-items-center rounded-full text-muted transition hover:bg-page" title="Close">✕</button>
                </div>

                @if ($clashSummary->isEmpty())
                    <p class="flex items-center gap-2 px-4 py-6 text-[12px] text-success-ink">✓ Nothing needs attention right now.</p>
                @else
                    <div class="scrollbar-none max-h-[360px] divide-y divide-line overflow-y-auto">
                        @foreach ($clashSummary as $row)
                            @php
                                $sevTone = match ($row['severity']) {
                                    'critical' => 'risk', 'high' => 'risk', 'medium' => 'warn', default => 'pending',
                                };
                                $sevCls = match ($sevTone) { 'risk' => 'tone-risk', 'warn' => 'tone-warn', default => 'tone-info' };
                            @endphp
                            <button type="button" wire:click="selectSession({{ $row['primarySessionId'] }})"
                                    class="flex w-full items-start gap-3 px-4 py-3 text-left transition hover:bg-page">
                                <span class="cx-tag {{ $sevCls }} mt-0.5 shrink-0" style="text-transform:uppercase">{{ $row['severity'] }}</span>
                                <span class="min-w-0 flex-1">
                                    <span class="flex flex-wrap items-center gap-x-2 gap-y-0.5">
                                        <span class="text-[11px] font-bold uppercase tracking-[0.04em] text-muted">{{ $row['typeLabel'] }}</span>
                                        <span class="text-[10.5px] text-muted">{{ $row['dayLabels']->implode(' · ') }}</span>
                                        @if ($row['roomName'])<span class="text-[10.5px] text-muted">· {{ $row['roomName'] }}</span>@endif
                                        @if ($row['speakerName'])<span class="text-[10.5px] text-muted">· {{ $row['speakerName'] }}</span>@endif
                                    </span>
                                    <span class="mt-0.5 block text-[12.5px] text-ink">{{ $row['message'] }}</span>
                                </span>
                                <span class="shrink-0 self-center text-[10.5px] font-bold text-gold-700">Open →</span>
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

    @include('livewire.hub.agenda.session-modal')
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
