@props(['percent', 'group' => 'track', 'size' => 'h-14 w-14'])

@php
    // r = 15.9155 → circumference ≈ 100, so dasharray maps 1:1 to percent.
    $stroke = match ($group) {
        'risk' => 'stroke-risk',
        'warn' => 'stroke-warn',
        default => 'stroke-track',
    };
    $text = match ($group) {
        'risk' => 'text-risk',
        'warn' => 'text-amber-600',
        default => 'text-emerald-600',
    };
@endphp

<span {{ $attributes->merge(['class' => "relative inline-flex items-center justify-center {$size}"]) }}>
    <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90">
        <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" class="stroke-navy-50" />
        <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" stroke-linecap="round"
                stroke-dasharray="{{ $percent }} {{ 100 - $percent }}" class="{{ $stroke }}" />
    </svg>
    <span class="absolute text-[0.65rem] font-bold {{ $text }}">{{ $percent }}%</span>
</span>
