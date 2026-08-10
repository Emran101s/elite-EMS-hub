@props([
    'title' => 'Event health',
    'score' => 0,
    'status' => 'pending', // ok | warn | risk | live | pending
    'hint' => null,
])

@php
    $score = max(0, min(100, (int) $score));
    $toneLabel = match ($status) {
        'ok' => 'On track',
        'warn' => 'Watch',
        'risk' => 'At risk',
        'live' => 'Live',
        default => 'Pending',
    };
    $bar = match ($status) {
        'ok' => 'from-eo-ok to-emerald-400',
        'warn' => 'from-eo-warn to-amber-400',
        'risk' => 'from-eo-risk to-red-400',
        'live' => 'from-eo-teal-deep to-eo-teal-lit',
        default => 'from-eo-muted to-eo-muted',
    };
@endphp

<div {{ $attributes->class(['eo-domain-card eo-health-card']) }}>
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <p class="eo-label">Event health</p>
            <h3 class="mt-1 text-[16px] font-semibold text-eo-text">{{ $title }}</h3>
            @if ($hint)
                <p class="mt-1 text-[12px] text-eo-muted">{{ $hint }}</p>
            @endif
        </div>
        <x-eo.status-pill :tone="$status">{{ $toneLabel }}</x-eo.status-pill>
    </div>

    <div class="flex items-end justify-between gap-3">
        <p class="text-[32px] font-bold tabular-nums tracking-tight text-eo-text">{{ $score }}</p>
        <p class="eo-label mb-1">Health index</p>
    </div>

    <div class="mt-3 h-2 overflow-hidden rounded-full bg-eo-bg">
        <div class="h-full rounded-full bg-gradient-to-r {{ $bar }}" style="width: {{ $score }}%"></div>
    </div>

    {{ $slot }}
</div>
