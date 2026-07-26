@props(['event'])
@php
    // The chip carries its colour in the DOT; the label stays dark navy so every
    // status reads with the same weight and the pod keeps one voice.
    $tones = [
        'planning'  => 'bg-cc-plan',
        'progress'  => 'bg-cc-info',
        'confirmed' => 'bg-cc-ok',
        'risk'      => 'bg-cc-risk',
    ];
    $tone = $tones[$event['status']] ?? $tones['planning'];

    // Green only when genuinely excellent, red below half, blue in between.
    $ring = match (true) {
        $event['health'] >= 95 => 'text-cc-ok',
        $event['health'] >= 50 => 'text-cc-info',
        default => 'text-cc-risk',
    };
    $r = 20; $c = 2 * M_PI * $r;
@endphp
{{--
    An event POD: a hexagon badge holding the health ring, locked onto a capsule
    body. The hexagon finally carries information rather than decorating a box,
    and the capsule holds text at a width text is readable at.
--}}
<a href="{{ $event['href'] ?? '#' }}" class="group relative block pl-[38px]">

    {{-- hexagon badge — the health instrument --}}
    <span class="absolute left-0 top-1/2 z-10 grid h-[74px] w-[68px] -translate-y-1/2 place-items-center
                 transition duration-300 group-hover:scale-105">
        <span class="cc-hex-flat absolute inset-0 bg-cc-navy transition group-hover:bg-cc-navy-2"></span>
        <span class="relative grid place-items-center">
            <svg width="48" height="48" viewBox="0 0 48 48" class="-rotate-90">
                <circle cx="24" cy="24" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="3.5" class="text-white/15" />
                <circle cx="24" cy="24" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="3.5" stroke-linecap="round"
                        class="{{ $ring }}" stroke-dasharray="{{ round($c, 1) }}"
                        stroke-dashoffset="{{ round($c - $c * $event['health'] / 100, 1) }}" />
            </svg>
            <span class="absolute text-[12px] font-extrabold text-white">{{ $event['health'] }}%</span>
        </span>
    </span>

    {{-- capsule body --}}
    <span class="relative block overflow-hidden rounded-[26px] border border-cc-line bg-white py-3 pl-10 pr-4
                 transition duration-300 cc-lift-2 group-hover:-translate-y-1 group-hover:border-cc-gold group-hover:cc-lift-3">

        {{-- status rail — the pod's spine --}}
        <span class="absolute inset-y-0 left-0 w-[5px] {{ $tone }}"></span>

        <span class="flex items-center justify-between gap-2">
            <span class="inline-flex items-center gap-1.5 rounded-full bg-cc-mist px-2 py-[3px] text-[9px] font-bold uppercase tracking-[0.12em] text-cc-ink">
                <span class="h-[6px] w-[6px] shrink-0 rounded-full {{ $tone }}"></span>{{ $event['statusLabel'] }}
            </span>
            <span class="shrink-0 text-[10px] font-bold {{ $event['footTone'] === 'ok' ? 'text-cc-ok' : 'text-cc-risk' }}">{{ $event['foot'] }}</span>
        </span>

        <span class="mt-1.5 block truncate text-[14.5px] font-extrabold leading-tight tracking-tight text-cc-navy">{{ $event['name'] }}</span>

        <span class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-0.5 text-[10.5px] text-cc-ink-2">
            <span class="flex items-center gap-1"><x-canvas.icon name="cal" :size="11" class="shrink-0 text-cc-ink-3" />{{ $event['dates'] }}</span>
            <span class="flex items-center gap-1"><x-canvas.icon name="pin" :size="11" class="shrink-0 text-cc-ink-3" />{{ $event['location'] }}</span>
        </span>

        <span class="mt-2 flex items-center gap-4 border-t border-cc-line pt-2 text-[10.5px] text-cc-ink-2">
            <span class="flex items-center gap-1"><x-canvas.icon name="people" :size="12" class="shrink-0 text-cc-ink-3" /><b class="font-bold text-cc-navy">{{ $event['participants'] }}</b> pax</span>
            <span class="flex items-center gap-1"><x-canvas.icon name="money" :size="12" class="shrink-0 text-cc-ink-3" /><b class="font-bold text-cc-navy">{{ $event['budget'] }}</b></span>
        </span>
    </span>
</a>
