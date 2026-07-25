@props([
    'variant' => 'solid',   // solar | solid | ghost | quiet | danger
    'size' => null,         // sm | lg
    'icon' => false,        // icon-only (square)
    'href' => null,
])
@php
    $classes = 'o-btn o-btn--'.$variant.($size ? ' o-btn--'.$size : '').($icon ? ' o-btn--icon' : '');
@endphp
@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $classes]) }}>{{ $slot }}</a>
@else
    <button {{ $attributes->merge(['class' => $classes, 'type' => 'button']) }}>{{ $slot }}</button>
@endif
