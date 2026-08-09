@props([
    'title',
    'subtitle' => null,
    'value' => null,
    'meta' => null,
    'tone' => 'premium', // premium | ok | warn
])

<div {{ $attributes->class(['eo-domain-card eo-commercial-card']) }}>
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <p class="eo-label">Commercial</p>
            <h3 class="mt-1 text-[16px] font-semibold text-eo-text">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-1 text-[12px] text-eo-muted">{{ $subtitle }}</p>
            @endif
        </div>
        <x-eo.status-pill :tone="$tone">Deal</x-eo.status-pill>
    </div>

    @if ($value)
        <p class="text-[28px] font-bold tracking-tight text-eo-text">{{ $value }}</p>
    @endif
    @if ($meta)
        <p class="mt-1 text-[12px] text-eo-muted">{{ $meta }}</p>
    @endif

    {{ $slot }}
</div>
