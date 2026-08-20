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

    // Event Pulse: Health + Readiness are what someone actually opens the
    // event to check first — pulled into one unified band rather than
    // living as two cards in the same grid as Days Out / Budget / Risk,
    // which stay real and visible, just clearly secondary.
    $pulse = [
        ['label' => 'Health Score', 'value' => $health['score'] !== null ? $health['score'] : '—',
            'sub' => $health['score'] !== null ? ucfirst(str_replace('_', ' ', $health['status'])) : 'Not scored', 'color' => $healthColor],
        ['label' => 'Readiness', 'value' => $header['readiness']['pct'].'%',
            'sub' => $header['readiness']['met'].' / '.$header['readiness']['total'].' gates met', 'color' => 'var(--color-eo-teal-ink)'],
    ];
    $secondary = [
        ['label' => 'Days Out', 'value' => $countdown['value'] ?? '—',
            'sub' => $countdown['label'] ?? 'Unscheduled', 'color' => 'var(--color-eo-text)'],
        ['label' => 'Budget Used', 'value' => $budgetPct !== null ? $budgetPct.'%' : '—',
            'sub' => $event->money($cost['forecast']).' / '.$event->money($cost['cap']), 'color' => $budgetPct !== null && $budgetPct > 100 ? '#b91c1c' : 'var(--color-eo-text)'],
        ['label' => 'Risk Level', 'value' => $riskLevel,
            'sub' => $event->risks->filter->isOpen()->count().' active', 'color' => $riskColor],
    ];
@endphp

{{-- Side by side, not stacked — "one Event Pulse zone" is about visual
     unity, not spending an extra row of page height to get it. --}}
<div class="hubx-pulse-wrap">
    <div class="hubx-pulse">
        <p class="hubx-pulse-label">Event Pulse</p>
        <div class="hubx-pulse-row">
            @foreach ($pulse as $i => $c)
                @if ($i > 0)<span class="hubx-pulse-divider"></span>@endif
                <div class="hubx-pulse-stat">
                    <p class="eo-label !text-[9.5px]">{{ $c['label'] }}</p>
                    <p class="hubx-pulse-value" style="color: {{ $c['color'] }}">{{ $c['value'] }}</p>
                    <p class="hubx-kpi-sub" style="color: {{ $c['color'] }}">{{ $c['sub'] }}</p>
                </div>
            @endforeach
        </div>
    </div>

    <div class="hubx-kpi-strip">
        @foreach ($secondary as $c)
            <div class="hubx-kpi-card is-secondary">
                <p class="eo-label !text-[9.5px]">{{ $c['label'] }}</p>
                <p class="hubx-kpi-value" style="color: {{ $c['color'] }}">{{ $c['value'] }}</p>
                <p class="hubx-kpi-sub" style="color: {{ $c['color'] }}">{{ $c['sub'] }}</p>
            </div>
        @endforeach
    </div>
</div>
