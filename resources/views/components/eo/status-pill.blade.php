@props([
    'tone' => 'pending', // ok | warn | risk | pending | live | premium
])

@php
    $class = match ($tone) {
        'ok', 'success' => 'eo-pill-ok',
        'warn', 'warning' => 'eo-pill-warn',
        'risk', 'danger' => 'eo-pill-risk',
        'live', 'info' => 'eo-pill-live',
        'premium', 'gold' => 'eo-pill-premium',
        default => 'eo-pill-pending',
    };
@endphp

<span {{ $attributes->class([$class]) }}>
    {{ $slot }}
</span>
