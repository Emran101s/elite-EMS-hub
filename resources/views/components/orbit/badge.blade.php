@props([
    'tone' => null,     // vital | ion | plasma | flare | critical | solar
    'pulse' => false,
    'bare' => false,    // hide the leading dot
])
@php
    $classes = 'o-badge'.($bare ? ' o-badge--bare' : '').($pulse ? ' o-badge--pulse' : '');
@endphp
<span {{ $attributes->merge(['class' => $classes]) }}@if ($tone) data-tone="{{ $tone }}"@endif>{{ $slot }}</span>
