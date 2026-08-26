@props([
    'tone' => 'pending', // ok | warn | risk | pending | live | premium
])

@php
    // Brand tone colours on the shared .pill shape (app.css), replacing the
    // old eo-pill-* classes so every status pill reads on the navy/gold
    // system without eo-soft-command.css.
    $class = match ($tone) {
        'ok', 'success' => 'bg-success-soft text-success-ink',
        'warn', 'warning' => 'bg-warning-soft text-warning-ink',
        'risk', 'danger' => 'bg-danger-soft text-danger-ink',
        'live', 'info' => 'bg-info-soft text-info-ink',
        'premium', 'gold' => 'bg-gold-50 text-gold-700 ring-1 ring-gold-200',
        default => 'bg-page text-muted',
    };
@endphp

<span {{ $attributes->class(['pill', $class]) }}>
    {{ $slot }}
</span>
