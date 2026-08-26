@props(['venue', 'header'])

<div class="hubx-kpi-strip">
    @foreach ($header['scale'] as $c)
        <div class="hubx-kpi-card is-secondary">
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !text-[9.5px]">{{ $c['label'] }}</p>
            <p class="hubx-kpi-value">{{ $c['value'] }}</p>
        </div>
    @endforeach

    <div class="hubx-kpi-card is-secondary">
        <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !text-[9.5px]">Readiness</p>
        <p class="hubx-kpi-value">{{ $header['readiness']['pct'] }}%</p>
        <p class="hubx-kpi-sub">{{ $header['readiness']['met'] }} / {{ $header['readiness']['total'] }} gates met</p>
    </div>
</div>
