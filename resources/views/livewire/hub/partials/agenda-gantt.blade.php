{{-- ══════════ SHARED GANTT BODY ══════════
     Legend, the conflict banner, the time-axis + room lanes, and the zoom
     footer — used by both Timeline View (every lane) and Rooms View (one
     lane, or every lane again). $lanes is the only thing that differs
     between callers; everything else ($timeline, $conflicts, $day,
     $legend, $selectedSession, $statusFilter) comes from the parent
     component's own render() data, which @include shares automatically.
     Block markup, drag data-attributes and the pointer-drag script this
     feeds are untouched from Phase G.1 — a session dragged from here lands
     exactly where it would from the Timeline view, because it is the same
     markup. Included (not a component) so it shares the parent's data
     automatically — $lanes is the only value each caller passes in. ══════════ --}}
@if ($lanes->isEmpty())
    <x-empty icon="chart" class="!border-0 !shadow-none" title="Nothing scheduled for this day"
             hint="Sessions plot themselves against the clock as you add them, one lane per room. Copy yesterday's running order if this day repeats it.">
        <x-slot:actions>
            <button type="button" wire:click="newSession" class="eo-btn eo-btn-primary eo-btn-sm">＋ Add the first session</button>
        </x-slot:actions>
    </x-empty>
@else
    {{-- filters legend --}}
    <div class="flex flex-wrap items-center gap-x-3.5 gap-y-2 border-b border-eo-line px-4 py-2.5">
        <span class="eo-label">Legend</span>
        @foreach ($legend as [$label, $hex])
            <span class="flex items-center gap-1.5 text-[11px] font-medium text-eo-text">
                <span class="h-2 w-2 rounded-full" style="background: {{ $hex }}"></span>{{ $label }}
            </span>
        @endforeach
        <span class="ms-auto text-[10px] italic text-eo-muted">dashed = not confirmed</span>
    </div>

    {{-- The board marks conflicts with a pin and a red ring, but a pin
         cannot say WHAT is wrong. The reasons stay on screen rather than
         hiding in a tooltip nobody hovers. --}}
    @if ($conflicts)
        <div data-conflict-banner class="border-b border-eo-risk/25 bg-eo-risk-soft px-4 py-2 transition">
            <p class="text-[11px] font-bold text-eo-risk">
                ⚠ {{ count($conflicts) }} scheduling {{ str('conflict')->plural(count($conflicts)) }} on this day
            </p>
            <ul class="mt-0.5 space-y-0.5 ps-4 text-[10.5px] text-eo-risk">
                @foreach (collect($conflicts)->flatten()->unique()->take(6) as $reason)
                    <li>· {{ $reason }}</li>
                @endforeach
            </ul>
        </div>
    @endif

    @php
        // The board is drawn wider than its column and scrolls, which is
        // the only way a 30-minute break is still a readable block: at
        // column width a 13-hour day gives a coffee break 26px, which can
        // hold nothing.
        $tlWidth = max(900, (int) ceil($timeline['span'] / 60) * 112);

        // The now-marker only means something on the day that is actually
        // today; on any other day it would be a lie.
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
            <div class="relative ms-[132px] h-8 border-b border-eo-line">
                <span class="absolute left-0 top-2 text-[9px] font-bold uppercase tracking-[0.16em] text-eo-muted" style="margin-left: -132px">Time</span>
                @foreach ($timeline['hours'] as $hour)
                    <span class="absolute top-2 -translate-x-1/2 text-[10px] font-semibold text-eo-muted" style="left: {{ $hour['left'] }}%">{{ $hour['label'] }}</span>
                @endforeach

                {{-- the conflict pins sit on the axis, above the block they belong to --}}
                @foreach ($lanes->flatMap->blocks as $b)
                    @if (isset($conflicts[$b['session']->id]))
                        <span class="absolute top-0 z-20 -translate-x-1/2 text-[11px] leading-none text-eo-risk"
                              style="left: {{ $b['left'] }}%"
                              title="{{ implode(' · ', $conflicts[$b['session']->id]) }}">⚠</span>
                    @endif
                @endforeach

                @if ($nowLeft !== null)
                    <span class="absolute -top-0.5 z-30 -translate-x-1/2 rounded-md bg-eo-gold px-1.5 py-0.5 text-[9px] font-black text-eo-navy-deep shadow"
                          style="left: {{ $nowLeft }}%">{{ now()->format('H:i') }}</span>
                @endif
            </div>

            {{-- lanes --}}
            <div class="relative">
                @if ($nowLeft !== null)
                    {{-- One line down the whole board, so "now" reads
                         against every room at once. --}}
                    <span class="pointer-events-none absolute inset-y-0 z-20 w-px border-l border-dashed border-eo-gold/70"
                          style="left: calc(132px + (100% - 132px) * {{ $nowLeft / 100 }})"></span>
                @endif

                @foreach ($lanes as $lane)
                    <div class="flex items-stretch border-b border-eo-line last:border-b-0">
                        <div class="flex w-[132px] shrink-0 flex-col justify-center gap-0.5 px-2.5 py-3">
                            <p class="flex items-center gap-1.5 text-[11.5px] font-bold text-eo-text">
                                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-eo-muted"></span>
                                <span class="truncate">{{ $lane['room'] }}</span>
                            </p>
                            <p class="ps-3 text-[10px] text-eo-muted">{{ $lane['blocks']->count() }} {{ str('session')->plural($lane['blocks']->count()) }}</p>
                        </div>

                        <div class="relative flex-1" data-room-track data-room-id="{{ $lane['room_id'] }}">
                            @foreach ($timeline['hours'] as $hour)
                                <span class="absolute inset-y-0 w-px bg-eo-line/70" style="left: {{ $hour['left'] }}%"></span>
                            @endforeach

                            <div class="relative py-2.5" style="min-height: 96px">
                                @if ($lane['blocks']->isEmpty())
                                    <p class="px-3 text-[11px] italic text-eo-muted">Nothing booked in this room today.</p>
                                @endif
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
                                        $selected = $selectedSession && $selectedSession->id === $sess->id;
                                        $dimmed = $statusFilter && ! in_array($sess->status, $statusFilter, true);
                                        // Critical keeps the ring + axis pin it has always had — the
                                        // loudest treatment, reserved for a real double-booking. High
                                        // and medium get a quiet corner dot instead: visible without
                                        // making every block with a gap in it look broken.
                                        $severity = $severityBySession[$sess->id] ?? null;
                                    @endphp
                                    <div wire:key="blk-{{ $sess->id }}"
                                         class="agenda-block group/blk absolute top-2.5 flex cursor-grab touch-none select-none flex-col justify-between rounded-xl p-2 text-left text-white shadow-sm transition hover:brightness-95 hover:ring-2 hover:ring-white/40 {{ $hasConflict ? 'ring-2 ring-eo-risk ring-offset-1' : '' }} {{ $selected ? 'ring-2 ring-eo-teal ring-offset-1' : '' }} {{ $dimmed ? 'opacity-30' : '' }}"
                                         data-session-id="{{ $sess->id }}" data-start-min="{{ $b['startMin'] }}" data-dur-min="{{ $b['durMin'] }}" data-room-id="{{ $lane['room_id'] }}"
                                         style="left: {{ $b['left'] }}%; width: {{ $b['width'] }}%; height: 80px; background: {{ $b['hex'] }}{{ $sess->isSettled() ? '' : '; outline: 2px dashed rgba(255,255,255,.55); outline-offset: -3px' }}"
                                         title="{{ $sess->title }} · {{ substr($sess->starts_at, 0, 5) }}–{{ substr($sess->ends_at, 0, 5) }} · {{ $sess->statusLabel() }} — drag to reschedule, click to select">

                                        @if ($severity === 'high')
                                            <span class="pointer-events-none absolute -top-1 -right-1 z-10 h-2.5 w-2.5 rounded-full bg-eo-warn ring-2 ring-white" title="High priority — see Inspector"></span>
                                        @elseif ($severity === 'medium')
                                            <span class="pointer-events-none absolute -top-1 -right-1 z-10 h-2 w-2 rounded-full bg-eo-warn/50 ring-2 ring-white" title="Needs attention — see Inspector"></span>
                                        @endif

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
                                            {{-- Seats booked at registration, against the seats
                                                 there are. A capacity nobody is measured against is
                                                 a number somebody typed once. --}}
                                            @php $booked = $sess->bookedCount(); @endphp
                                            @if ($sess->capacity)
                                                <span class="ms-auto shrink-0 whitespace-nowrap"
                                                      title="{{ $booked }} booked of {{ number_format($sess->capacity) }}">
                                                    @if ($booked > 0)<span class="font-bold">{{ number_format($booked) }}</span>/@endif{{ number_format($sess->capacity) }}@if ($fill !== null && $booked === 0) · {{ $fill }}%@endif
                                                </span>
                                            @elseif ($booked > 0)
                                                <span class="ms-auto shrink-0 whitespace-nowrap font-bold" title="{{ $booked }} booked at registration">{{ number_format($booked) }}</span>
                                            @endif
                                        </span>

                                        {{-- hover quick actions --}}
                                        <span class="absolute -top-2.5 right-1 z-10 hidden items-center gap-1 group-hover/blk:flex">
                                            <button type="button" data-block-action wire:click.stop="toggleFlag({{ $sess->id }})"
                                                    class="flex h-5 w-5 items-center justify-center rounded-md bg-white/95 text-eyebrow shadow ring-1 ring-black/5 transition hover:bg-white {{ $sess->flagged ? '' : 'grayscale' }}"
                                                    title="{{ $sess->flagged ? 'Remove flag' : 'Flag' }}">🚩</button>
                                            <x-confirm title="Delete “{{ $sess->title }}”?"
                                                    confirm="Delete"
                                                    run="$wire.deleteSession({{ $sess->id }})"
                                                    data-block-action
                                                    class="flex h-5 w-5 items-center justify-center rounded-md bg-white/95 text-micro font-bold text-red-600 shadow ring-1 ring-black/5 transition hover:bg-white">✕</x-confirm>
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
    <div class="flex flex-wrap items-center gap-x-4 gap-y-2 border-t border-eo-line px-4 py-2.5">
        @foreach ([
            ['text-eo-risk', 'Speaker conflict'],
            ['text-eo-warn', 'Room capacity risk'],
            ['text-eo-muted', 'Overlapping session'],
            ['text-eo-muted', 'Missing presentation'],
        ] as [$tone, $label])
            <span class="flex items-center gap-1.5 text-[10.5px] text-eo-muted"><span class="{{ $tone }}">⚠</span>{{ $label }}</span>
        @endforeach

        <div class="ms-auto flex items-center gap-1.5">
            <button type="button" data-tl-fit class="eo-btn eo-btn-ghost eo-btn-sm !h-7 !px-2.5 !text-[10.5px]">⤢ Fit</button>
            <button type="button" data-tl-zoom="-1" class="grid h-6 w-6 place-items-center rounded-lg border border-eo-line text-eo-muted transition hover:bg-eo-workspace">−</button>
            <span data-tl-level class="w-10 text-center text-[10.5px] font-bold tabular-nums text-eo-text">100%</span>
            <button type="button" data-tl-zoom="1" class="grid h-6 w-6 place-items-center rounded-lg border border-eo-line text-eo-muted transition hover:bg-eo-workspace">+</button>
        </div>
    </div>
@endif
