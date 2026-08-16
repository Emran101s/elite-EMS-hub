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
        'Critical', 'High' => '#b91c1c',
        'Medium' => '#92400e',
        default => '#15803d',
    };

    $healthColor = match ($health['group'] ?? 'neutral') {
        'track' => '#15803d', 'warn' => '#92400e', 'risk' => '#b91c1c', default => 'var(--color-eo-muted)',
    };

    $cards = [
        ['label' => 'Health Score', 'value' => $health['score'] !== null ? $health['score'] : '—',
            'sub' => $health['score'] !== null ? ucfirst(str_replace('_', ' ', $health['status'])) : 'Not scored', 'color' => $healthColor],
        ['label' => 'Readiness', 'value' => $header['readiness']['pct'].'%',
            'sub' => $header['readiness']['met'].' / '.$header['readiness']['total'].' gates met', 'color' => 'var(--color-eo-teal-ink)'],
        ['label' => 'Days Out', 'value' => $countdown['value'] ?? '—',
            'sub' => $countdown['label'] ?? 'Unscheduled', 'color' => 'var(--color-eo-text)'],
        ['label' => 'Budget Used', 'value' => $budgetPct !== null ? $budgetPct.'%' : '—',
            'sub' => $event->money($cost['forecast']).' / '.$event->money($cost['cap']), 'color' => $budgetPct !== null && $budgetPct > 100 ? '#b91c1c' : 'var(--color-eo-text)'],
        ['label' => 'Risk Level', 'value' => $riskLevel,
            'sub' => $event->risks->filter->isOpen()->count().' active', 'color' => $riskColor],
    ];
@endphp

<div class="hubx-kpi-strip">
    @foreach ($cards as $c)
        <div class="hubx-kpi-card">
            <p class="eo-label !text-[9.5px]">{{ $c['label'] }}</p>
            <p class="hubx-kpi-value" style="color: {{ $c['color'] }}">{{ $c['value'] }}</p>
            <p class="hubx-kpi-sub" style="color: {{ $c['color'] }}">{{ $c['sub'] }}</p>
        </div>
    @endforeach
</div>
