@props([
    // A circular gauge, not a plain progress ring — ticks around the face are
    // what read as "instrument" instead of "loading spinner". Built once so
    // the hero and the instrument panel below it share one real component
    // instead of three copies of the same SVG math drifting apart over time.
    'pct' => 100,
    'hex' => 'var(--color-gold-500)',
    'size' => 72,
    'stroke' => 5,
    'ticks' => 10,
    'track' => 'rgba(255,255,255,.08)',
    'tickColor' => 'rgba(255,255,255,.16)',
])

@php
    $r = ($size - $stroke) / 2 - 2;
    $ring = 2 * M_PI * $r;
    $c = $size / 2;
@endphp

<div {{ $attributes->merge(['class' => 'relative shrink-0']) }} style="height:{{ $size }}px;width:{{ $size }}px">
    <svg class="h-full w-full -rotate-90" viewBox="0 0 {{ $size }} {{ $size }}" aria-hidden="true">
        @for ($i = 0; $i < $ticks; $i++)
            @php $a = deg2rad(360 / $ticks * $i); @endphp
            <line x1="{{ $c + ($r + $stroke / 2 + 1.5) * cos($a) }}" y1="{{ $c + ($r + $stroke / 2 + 1.5) * sin($a) }}"
                  x2="{{ $c + ($r + $stroke / 2 + 4) * cos($a) }}" y2="{{ $c + ($r + $stroke / 2 + 4) * sin($a) }}"
                  stroke="{{ $tickColor }}" stroke-width="1" stroke-linecap="round" />
        @endfor
        <circle cx="{{ $c }}" cy="{{ $c }}" r="{{ $r }}" fill="none" stroke="{{ $track }}" stroke-width="{{ $stroke }}" />
        <circle cx="{{ $c }}" cy="{{ $c }}" r="{{ $r }}" fill="none" stroke="{{ $hex }}" stroke-width="{{ $stroke }}" stroke-linecap="round"
                stroke-dasharray="{{ $ring }}" stroke-dashoffset="{{ $ring - ($ring * min(max($pct, 0), 100) / 100) }}" />
    </svg>
    <div class="absolute inset-0 grid place-items-center">
        {{ $slot }}
    </div>
</div>
