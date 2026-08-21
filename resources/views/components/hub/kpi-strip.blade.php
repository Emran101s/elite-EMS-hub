@props(['event', 'header', 'health'])

@php
    $countdown = collect($header['scale'])->firstWhere('icon', 'clock');
    $budgetPct = $event->budgetUsedPct();
    $cost = $event->costForecast();

    $severity = $event->risks->filter->isOpen()->max(fn ($r) => $r->severity()) ?? 0;
    $riskLevel = match (true) {
        $severity >= 20 => 'Critical',
        $severity >= 12 => 'High',
        $severity > 0 => 'Medium',
        default => 'Low',
    };
    $riskColor = match ($riskLevel) {
        'Critical', 'High' => 'var(--color-danger-ink)',
        'Medium' => 'var(--color-warning-ink)',
        default => 'var(--color-success-ink)',
    };

    $healthColor = match ($health['group'] ?? 'neutral') {
        'track' => 'var(--color-success-ink)', 'warn' => 'var(--color-warning-ink)', 'risk' => 'var(--color-danger-ink)', default => 'var(--color-muted)',
    };

    $pulse = [
        ['label' => 'Health Score', 'value' => $health['score'] !== null ? $health['score'] : '—',
            'sub' => $health['score'] !== null ? ucfirst(str_replace('_', ' ', $health['status'])) : 'Not scored', 'color' => $healthColor],
        ['label' => 'Readiness', 'value' => $header['readiness']['pct'].'%',
            'sub' => $header['readiness']['met'].' / '.$header['readiness']['total'].' gates met', 'color' => 'var(--color-gold-700)'],
    ];
    $secondary = [
        ['label' => 'Days Out', 'value' => $countdown['value'] ?? '—',
            'sub' => $countdown['label'] ?? 'Unscheduled', 'color' => 'var(--color-ink)'],
        ['label' => 'Budget Used', 'value' => $budgetPct !== null ? $budgetPct.'%' : '—',
            'sub' => $event->money($cost['forecast']).' / '.$event->money($cost['cap']), 'color' => $budgetPct !== null && $budgetPct > 100 ? 'var(--color-danger-ink)' : 'var(--color-ink)'],
        ['label' => 'Risk Level', 'value' => $riskLevel,
            'sub' => $event->risks->filter->isOpen()->count().' active', 'color' => $riskColor],
    ];
@endphp

<div class="ehx-pulse-wrap">
    <div class="ehx-pulse">
        <p class="ehx-pulse-label">Event Pulse</p>
        <div class="ehx-pulse-row">
            @foreach ($pulse as $i => $c)
                @if ($i > 0)<span class="ehx-pulse-divider"></span>@endif
                <div class="ehx-pulse-stat">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9.5px]">{{ $c['label'] }}</p>
                    <p class="ehx-pulse-value" style="color: {{ $c['color'] }}">{{ $c['value'] }}</p>
                    <p class="ehx-kpi-sub" style="color: {{ $c['color'] }}">{{ $c['sub'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="ehx-kpi-strip">
        @foreach ($secondary as $c)
            <div class="ehx-kpi-card">
                <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[8.5px] opacity-65">{{ $c['label'] }}</p>
                <p class="ehx-kpi-value" style="color: {{ $c['color'] }}">{{ $c['value'] }}</p>
                <p class="ehx-kpi-sub" style="color: {{ $c['color'] }}">{{ $c['sub'] }}</p>
            </div>
        @endforeach
    </div>
</div>
