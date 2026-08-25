@props([
    'label',
    'value',
    'hint' => null,
    'tone' => null, // null | ok | warn | risk | live
])

@php
    $valueClass = match ($tone) {
        'ok' => 'text-success-ink',
        'warn' => 'text-warning-ink',
        'risk' => 'text-danger-ink',
        'live' => 'text-info-ink',
        default => 'text-ink',
    };
@endphp

<div {{ $attributes->class(['flex min-w-[140px] flex-col gap-1 rounded-lg border border-line bg-white px-4 py-3.5 shadow-raise']) }}>
    <p class="eyebrow">{{ $label }}</p>
    <p class="text-[22px] font-bold tabular-nums tracking-tight {{ $valueClass }}">{{ $value }}</p>
    @if ($hint)
        <p class="text-[12px] text-muted">{{ $hint }}</p>
    @endif
</div>
