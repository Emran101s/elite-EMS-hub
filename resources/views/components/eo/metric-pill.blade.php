@props([
    'label',
    'value',
    'hint' => null,
    'tone' => null, // null | ok | warn | risk | live
])

@php
    $valueClass = match ($tone) {
        'ok' => 'text-eo-ok',
        'warn' => 'text-eo-warn',
        'risk' => 'text-eo-risk',
        'live' => 'text-eo-teal',
        default => 'text-eo-text',
    };
@endphp

<div {{ $attributes->class(['eo-soft-card flex min-w-[140px] flex-col gap-1 px-4 py-3.5']) }}>
    <p class="eo-label">{{ $label }}</p>
    <p class="text-[22px] font-bold tabular-nums tracking-tight {{ $valueClass }}">{{ $value }}</p>
    @if ($hint)
        <p class="text-[12px] text-eo-muted">{{ $hint }}</p>
    @endif
</div>
