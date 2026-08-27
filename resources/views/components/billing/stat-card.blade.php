@props(['eyebrow', 'title', 'subtitle' => null, 'value', 'meta' => null, 'tone' => 'premium'])

@php
    $valueColor = match ($tone) {
        'ok' => 'text-success-ink',
        'warn' => 'text-warning-ink',
        default => 'text-ink',
    };
@endphp

<div class="rounded-md bg-page px-3.5 py-3">
    <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">{{ $eyebrow }}</p>
    <p class="mt-1.5 text-[13.5px] font-semibold text-ink">{{ $title }}</p>
    @if ($subtitle)
        <p class="mt-0.5 text-[11px] text-muted">{{ $subtitle }}</p>
    @endif
    <p class="mt-2 text-[24px] font-extrabold tracking-tight tabular-nums {{ $valueColor }}">{{ $value }}</p>
    @if ($meta)
        <p class="mt-0.5 text-[11px] text-muted">{{ $meta }}</p>
    @endif
</div>
