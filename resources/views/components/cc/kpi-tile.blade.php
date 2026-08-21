@props(['label', 'value', 'hint' => null, 'tone' => null])

@php
    $accent = match ($tone) {
        'ok' => 'border-l-success',
        'warn' => 'border-l-warning',
        'risk' => 'border-l-danger',
        'live' => 'border-l-info',
        default => 'border-l-line',
    };
@endphp

<div {{ $attributes->class(["rounded-lg border border-line border-l-2 bg-white p-3.5 transition hover:-translate-y-0.5 hover:shadow-float", $accent]) }}>
    <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $label }}</p>
    <p class="mt-1 text-[22px] font-extrabold tracking-tight tabular-nums text-ink">{{ $value }}</p>
    @if ($hint)
        <p class="mt-0.5 text-[11px] text-muted">{{ $hint }}</p>
    @endif
</div>
