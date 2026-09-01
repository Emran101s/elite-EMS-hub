{{-- ═══ THE DAY STRIP ═══
     Days used to be a 240px vertical rail down the left of the builder. On a
     five-day event that is five rows of content holding a column open all the
     way down the page, taking width from the one thing that actually needs
     it: the schedule. Horizontal, they cost ~64px of height and nothing at
     all of width.

     The day you are on carries the context line — session count, what is
     still draft, whether anything clashes — so the three "Day Insights"
     cards that used to sit in their own 296px column (usually reading 0, 0
     and 0) become one sentence you can read without moving your eyes. --}}
<div class="cx-lcard !mb-2">
    {{-- min-w-0: overflow-x-auto only scrolls if the box is allowed to be
         narrower than its contents. Without it the strip widened to fit every
         day tab and took the whole page sideways with it. --}}
    <div class="flex min-w-0 items-stretch gap-px overflow-x-auto scrollbar-none" style="background: var(--cx-line-soft)">
        @foreach ($dayCards as $card)
            @php $d = $card['model']; $on = $day && $day->id === $d->id; @endphp
            <button type="button" wire:click="selectDay({{ $d->id }})"
                    class="cx-daytab {{ $on ? 'is-on' : '' }}">
                <span class="cx-dhex">{{ $card['pct'] }}</span>
                <span class="min-w-0 text-left">
                    <span class="block truncate cx-dname">{{ $d->date?->format('D, j M') ?? $d->label }}</span>
                    <span class="block cx-dsub">{{ $card['sessions'] }} {{ str('session')->plural($card['sessions']) }}</span>
                </span>
            </button>
        @endforeach

        <button type="button" wire:click="addDay" class="cx-daytab cx-daytab-add" title="Add a day">＋</button>
    </div>

    {{-- The context line: what this day is, in one read. --}}
    @if ($day)
        @php
            $clashCount = collect($conflicts ?? [])->flatten()->unique()->count();
            $draftCount = $day->sessions->where('status', 'draft')->count();
        @endphp
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-line px-3.5 py-2 text-[11.5px]">
            <span class="font-bold text-ink">{{ $day->date?->format('l, j F') ?? $day->label }}</span>
            <span class="text-muted">{{ $daySessions }} {{ str('session')->plural($daySessions) }}</span>

            @if ($draftCount)
                <span class="flex items-center gap-1.5 text-muted">
                    <span class="cx-hexdot" style="background: var(--cx-warn)"></span>{{ $draftCount }} still draft
                </span>
            @endif

            @if ($clashCount)
                <button type="button" wire:click="$toggle('showClashSummary')"
                        class="flex items-center gap-1.5 font-semibold" style="color: var(--cx-risk-ink)">
                    <span class="cx-hexdot" style="background: var(--cx-risk)"></span>
                    {{ $clashCount }} {{ str('clash')->plural($clashCount) }} — review
                </button>
            @else
                <span class="flex items-center gap-1.5 text-muted">
                    <span class="cx-hexdot" style="background: var(--cx-ok)"></span>No clashes
                </span>
            @endif

            <span class="ms-auto flex items-center gap-2">
                @if ($daySessions)
                    <button type="button" wire:click="confirmDay" class="text-[11px] font-semibold text-muted transition hover:text-ink">Confirm the day</button>
                @endif
                <a href="{{ route('events.agenda.program.pdf', $event) }}" target="_blank"
                   class="text-[11px] font-semibold" style="color: var(--cx-accent-ink)">Run of show ↗</a>
            </span>
        </div>
    @endif
</div>
