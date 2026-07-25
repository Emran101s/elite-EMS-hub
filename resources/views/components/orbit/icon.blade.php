@props([
    'name',              // sprite id without the "i-" prefix, e.g. "home"
    'size' => 16,
    'stroke' => 1.8,
])
<svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 24 24" fill="none"
     stroke="currentColor" stroke-width="{{ $stroke }}" stroke-linecap="round" stroke-linejoin="round"
     aria-hidden="true" {{ $attributes }}><use href="#i-{{ $name }}"/></svg>
