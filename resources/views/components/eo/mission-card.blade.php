@props([
    'title',
    'venue' => null,
    'dates' => null,
    'stage' => null,
    'type' => null, // Summit | Conference | Exhibition | Forum | VIP…
    'readiness' => null,
    'health' => null, // ok | warn | risk | live
    'href' => null,
])

@php
    $tag = $href ? 'a' : 'div';
    $ready = is_null($readiness) ? null : max(0, min(100, (int) $readiness));
    $ringTone = match ($health) {
        'risk' => 'var(--color-eo-risk)',
        'warn' => 'var(--color-eo-warn)',
        'ok' => 'var(--color-eo-ok)',
        default => 'var(--color-eo-teal)',
    };
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'eo-domain-card eo-mission-card block transition hover:-translate-y-0.5 hover:shadow-eo-float',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-eo-teal/40' => (bool) $href,
    ]) }}
>
    <div class="flex items-start gap-3">
        <div class="min-w-0 flex-1">
            <div class="mb-3 flex flex-wrap items-center gap-2">
                @if ($type)
                    <span class="eo-journey-chip">{{ $type }}</span>
                @endif
                @if ($stage)
                    <x-eo.status-pill tone="live">{{ $stage }}</x-eo.status-pill>
                @endif
                @isset($badge)
                    {{ $badge }}
                @endisset
            </div>

            <h3 class="text-[16px] font-semibold text-eo-text">{{ $title }}</h3>

            @if ($venue || $dates)
                <p class="mt-1.5 text-[13px] text-eo-muted">
                    {{ collect([$venue, $dates])->filter()->implode(' · ') }}
                </p>
            @endif
        </div>

        @if (! is_null($ready))
            <div
                class="eo-mission-card-ring shrink-0"
                style="--eo-ring: {{ $ringTone }}; --eo-ring-pct: {{ $ready }}%"
                title="{{ $ready }}% readiness"
            >
                <span class="eo-mission-card-ring-value">{{ $ready }}</span>
            </div>
        @endif
    </div>

    @if (! is_null($ready))
        <div class="mt-4">
            <div class="mb-1.5 flex items-center justify-between">
                <span class="eo-label">Mission readiness</span>
                <span class="text-[12px] font-bold tabular-nums text-eo-teal-ink">{{ $ready }}%</span>
            </div>
            <div class="h-1.5 overflow-hidden rounded-full bg-eo-bg">
                <div
                    class="h-full rounded-full bg-gradient-to-r from-eo-teal-deep to-eo-teal-lit"
                    style="width: {{ $ready }}%"
                ></div>
            </div>
        </div>
    @endif

    {{ $slot }}
</{{ $tag }}>