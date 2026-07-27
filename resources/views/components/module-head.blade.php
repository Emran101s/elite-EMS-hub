@props([
    // The instrument on the left: a ring (0–100), or a lead number, or neither.
    'ring' => null,
    'ringLabel' => null,      // what goes inside the ring — defaults to the %
    'eyebrow' => null,        // small gold label above the lead
    'lead' => null,           // the one figure that matters most
    'leadNote' => null,       // what it means, under the lead
    'leadTone' => 'text-gold-400',

    // The row of supporting figures: [label, value, tone]
    'figures' => [],
])

{{--
    One header for every module.

    Budget led with a flat row of four numbers; the Planner led with a
    dark island that stopped halfway across with its buttons loose on the
    page beside it; Agenda led with a ring and read like an instrument.
    This is Agenda's shape, made shared, so a module's header says what
    module you are in rather than which week it was built.

    Everything is optional. A module brings a ring or a lead number or
    neither, as many figures as it has, and its actions — which live
    INSIDE the strip, because a header with its buttons outside it is the
    thing that looked unfinished.
--}}

@php
    $circumference = 2 * M_PI * 26;
@endphp

<div {{ $attributes->merge(['class' => 'strip-dark relative mb-4 flex flex-wrap items-center gap-x-8 gap-y-4 overflow-hidden px-5 py-4']) }}>
    <div class="pointer-events-none absolute -right-8 -top-14 h-44 w-44 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.26),transparent_70%)]"></div>

    {{-- ── the instrument ── --}}
    @if ($ring !== null)
        <div class="relative shrink-0">
            <svg class="h-[70px] w-[70px] -rotate-90" viewBox="0 0 60 60" aria-hidden="true">
                <circle cx="30" cy="30" r="26" fill="none" stroke="rgba(255,255,255,.12)" stroke-width="6" />
                <circle cx="30" cy="30" r="26" fill="none" stroke="var(--color-gold-500)" stroke-width="6" stroke-linecap="round"
                        stroke-dasharray="{{ $circumference }}"
                        stroke-dashoffset="{{ $circumference - ($circumference * max(0, min(100, $ring)) / 100) }}" />
            </svg>
            <span class="absolute inset-0 flex items-center justify-center text-sm font-black text-white">{{ $ringLabel ?? $ring.'%' }}</span>
        </div>
    @endif

    @if ($lead !== null || $eyebrow)
        <div class="relative min-w-0 shrink-0">
            @if ($eyebrow)
                <p class="text-eyebrow font-bold uppercase tracking-[0.2em] text-gold-300/90">{{ $eyebrow }}</p>
            @endif
            @if ($lead !== null)
                <p class="pf mt-1 text-[34px] font-black leading-none {{ $leadTone }}">{{ $lead }}</p>
            @endif
            @if ($leadNote)
                <p class="mt-1 text-eyebrow font-semibold text-white/55">{{ $leadNote }}</p>
            @endif
        </div>
    @endif

    {{-- ── the supporting figures ── --}}
    @foreach ($figures as $i => [$label, $value, $tone])
        @if ($i > 0 || $ring !== null || $lead !== null)
            <span class="hidden h-11 w-px bg-white/10 sm:block" aria-hidden="true"></span>
        @endif
        <div class="relative min-w-[104px]">
            <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-gold-300/80">{{ $label }}</p>
            <p class="pf mt-1 text-[22px] font-bold leading-none {{ $tone ?: 'text-white' }}">{{ $value }}</p>
        </div>
    @endforeach

    {{-- ── the right-hand side ── --}}
    @isset($meter)
        <div class="relative ml-auto min-w-[190px] flex-1">{{ $meter }}</div>
    @endisset

    @isset($actions)
        <div class="relative flex shrink-0 flex-wrap items-center gap-2 {{ isset($meter) ? '' : 'ml-auto' }}">{{ $actions }}</div>
    @endisset

    {{ $slot }}
</div>
