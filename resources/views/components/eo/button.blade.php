@props([
    'variant' => 'primary', // primary | secondary | ghost | danger | navy
    'size' => null, // sm | null
    'pill' => false,
    'type' => 'button',
    'href' => null,
])

@php
    $variantClass = match ($variant) {
        'secondary' => 'eo-btn-secondary',
        'ghost' => 'eo-btn-ghost',
        'danger' => 'eo-btn-danger',
        'navy' => 'eo-btn-navy',
        default => 'eo-btn-primary',
    };
    $classes = collect([
        $variantClass,
        $size === 'sm' ? 'eo-btn-sm' : null,
        $pill ? 'eo-btn-pill' : null,
    ])->filter()->implode(' ');
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->class([$classes]) }}>
        {{ $slot }}
    </button>
@endif
