@props(['percent' => 0, 'hex' => '#6366F1', 'size' => 64, 'label' => null, 'dark' => false])

{{-- The progress circle. Every view draws the same one at a different size. --}}

@php
    $pct = max(0, min(100, (int) $percent));
    $c = 2 * M_PI * 26;
    $stroke = $size >= 80 ? 6 : ($size >= 56 ? 5.5 : 5);
@endphp

<span {{ $attributes->merge(['class' => 'relative grid shrink-0 place-items-center']) }}
      style="height: {{ $size }}px; width: {{ $size }}px">
    <svg class="-rotate-90" viewBox="0 0 60 60" style="height: {{ $size }}px; width: {{ $size }}px" aria-hidden="true">
        <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $dark ? 'rgba(255,255,255,.16)' : 'var(--color-navy-50)' }}" stroke-width="{{ $stroke }}" />
        <circle cx="30" cy="30" r="26" fill="none" stroke="{{ $hex }}" stroke-width="{{ $stroke }}" stroke-linecap="round"
                stroke-dasharray="{{ $c }}" stroke-dashoffset="{{ $c - ($c * $pct / 100) }}"
                style="transition: stroke-dashoffset .6s cubic-bezier(.4,0,.2,1)" />
    </svg>
    <span class="absolute text-center leading-none">
        <span class="pf block font-black {{ $dark ? 'text-white' : 'text-navy-950' }}"
              style="font-size: {{ max(11, round($size * 0.26)) }}px">{{ $pct }}%</span>
        @if ($label)
            <span class="mt-0.5 block font-bold uppercase tracking-[0.14em] {{ $dark ? 'text-white/50' : 'text-navy-400' }}"
                  style="font-size: {{ max(6.5, round($size * 0.105)) }}px">{{ $label }}</span>
        @endif
    </span>
</span>
