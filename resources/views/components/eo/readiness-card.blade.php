@props([
    'title',
    'percent' => 0,
    'status' => 'pending', // ok | warn | risk | pending | live
    'hint' => null,
    'domain' => 'Readiness', // label eyebrow — Delegate / Venue / Ops…
])

@php
    $percent = max(0, min(100, (int) $percent));
    $bar = match ($status) {
        'ok' => 'from-eo-ok to-emerald-400',
        'warn' => 'from-eo-warn to-amber-400',
        'risk' => 'from-eo-risk to-red-400',
        'live' => 'from-eo-teal-deep to-eo-teal-lit',
        default => 'from-eo-muted to-eo-muted',
    };
@endphp

<div {{ $attributes->class(['eo-domain-card eo-readiness-surface']) }}>
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <p class="eo-label">{{ $domain }}</p>
            <h3 class="mt-1 text-[15px] font-semibold text-eo-text">{{ $title }}</h3>
            @if ($hint)
                <p class="mt-1 text-[12px] text-eo-muted">{{ $hint }}</p>
            @endif
        </div>
        <x-eo.status-pill :tone="$status">{{ $percent }}%</x-eo.status-pill>
    </div>

    <div class="h-2 overflow-hidden rounded-full bg-eo-bg">
        <div class="h-full rounded-full bg-gradient-to-r {{ $bar }} transition-all" style="width: {{ $percent }}%"></div>
    </div>

    @isset($meta)
        <div class="mt-3 text-[12px] text-eo-muted">
            {{ $meta }}
        </div>
    @endisset
</div>
