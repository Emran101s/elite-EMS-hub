@props(['event', 'header', 'health'])

@php
    $title = $header['title'];
    $where = collect([$event->city, $event->country])->filter()->implode(', ');
    $when = $event->starts_at
        ? $event->starts_at->format('j M').' – '.($event->ends_at?->format('j M Y') ?? $event->starts_at->format('Y'))
        : 'Dates to be set';
    $type = str($event->type ?? 'Event')->replace('_', ' ')->title();
    $stage = \App\Support\Workflow::label('event_stage', $event->stage);
    $live = $header['live'];

    $stageTone = match (true) {
        str_contains(strtolower($stage), 'complet') => ['bg' => 'var(--color-success-soft)', 'text' => 'var(--color-success-ink)'],
        str_contains(strtolower($stage), 'live') || str_contains(strtolower($stage), 'progress') => ['bg' => 'var(--color-gold-50)', 'text' => 'var(--color-gold-700)'],
        default => ['bg' => 'var(--color-warning-soft)', 'text' => 'var(--color-warning-ink)'],
    };

    // The event's vitals — Event Pulse — live inside this same card now,
    // one hairline below identity, rather than floating as their own
    // strip underneath the header. Health itself is called out separately
    // as the ring in the top-right corner (see below); the strip carries
    // the five figures behind it.
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
    $healthPct = $health['score'] !== null ? max(0, min(100, (int) $health['score'])) : 0;
    $healthLabel = $health['score'] !== null ? ucfirst(str_replace('_', ' ', $health['status'])) : 'Not scored';

    $registered = $event->attendees->count();
    $expected = (int) $event->expected_participants;

    $readinessPct = $header['readiness']['pct'];
    $budgetBarPct = $budgetPct !== null ? max(0, min(100, $budgetPct)) : 0;

    $stats = [
        ['label' => 'Readiness', 'icon' => 'check', 'tone' => 'gold', 'value' => $readinessPct.'%',
            'sub' => $header['readiness']['met'].' / '.$header['readiness']['total'].' gates met', 'color' => 'var(--color-gold-700)', 'bar' => $readinessPct],
        ['label' => 'Days Out', 'icon' => 'clock', 'tone' => 'navy', 'value' => $countdown['value'] ?? '—',
            'sub' => $countdown['label'] ?? 'Unscheduled', 'color' => 'var(--color-ink)', 'bar' => null],
        ['label' => 'Budget Used', 'icon' => 'currency', 'tone' => 'success', 'value' => $budgetPct !== null ? $budgetPct.'%' : '—',
            'sub' => $event->money($cost['forecast']).' / '.$event->money($cost['cap']), 'color' => $budgetPct !== null && $budgetPct > 100 ? 'var(--color-danger-ink)' : 'var(--color-ink)', 'bar' => $budgetPct !== null ? $budgetBarPct : null],
        ['label' => 'Risk Level', 'icon' => 'bell', 'tone' => 'warning', 'value' => $riskLevel,
            'sub' => $event->risks->filter->isOpen()->count().' active', 'color' => $riskColor, 'bar' => null],
        ['label' => 'Attendees', 'icon' => 'users', 'tone' => 'purple', 'value' => number_format($expected > 0 ? $expected : $registered),
            'sub' => $registered.' registered', 'color' => 'var(--color-ink)', 'bar' => null],
    ];
@endphp

<div class="ehx-header">
    <div class="ehx-header-top">
        <div class="flex min-w-0 flex-1 items-start gap-3.5">
            <span class="ehx-header-avatar shrink-0" aria-hidden="true">
                <x-icon name="calendar" class="h-5 w-5" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="mb-2 flex flex-wrap items-center gap-2 max-w-full">
                    <span class="ehx-pill" style="background: {{ $stageTone['bg'] }}; color: {{ $stageTone['text'] }};">{{ $stage }}</span>
                    <span class="ehx-pill" style="background: var(--color-gold-50); color: var(--color-gold-700);">{{ $type }}</span>
                    @if (! empty($live['label']))
                        <span class="ehx-pill" style="background: {{ $live['tone'] === 'bg-risk' ? 'var(--color-danger-soft)' : ($live['tone'] === 'bg-warn' ? 'var(--color-warning-soft)' : 'var(--color-success-soft)') }}; color: {{ $live['tone'] === 'bg-risk' ? 'var(--color-danger-ink)' : ($live['tone'] === 'bg-warn' ? 'var(--color-warning-ink)' : 'var(--color-success-ink)') }};">
                            {{ $live['label'] }}
                        </span>
                    @endif
                </div>

                <h1 class="ehx-header-name">
                    {{ $title['lead'] }}@if ($title['tail'])<span class="font-medium text-muted"> · {{ $title['tail'] }}</span>@endif
                </h1>

                <p class="ehx-header-meta">
                    <span>{{ $where ?: 'Venue TBC' }}</span>
                    <span class="ehx-header-dot">{{ $when }}</span>
                    @if ($event->client)
                        <span class="ehx-header-dot">{{ $event->client->name }}</span>
                    @endif
                </p>
            </div>
        </div>

        <div class="flex shrink-0 items-center gap-3">
            <span class="ehx-header-icon-row">
                <span class="ehx-header-icon" title="Star"><x-icon name="star" class="h-3.5 w-3.5" /></span>
                <span class="ehx-header-icon" title="Expand"><x-icon name="grid" class="h-3.5 w-3.5" /></span>
                <button type="button" class="ehx-header-icon" title="Event Utilities" @click="utilitiesOpen = true">
                    <x-icon name="dots" class="h-3.5 w-3.5" />
                </button>
                <a href="{{ route('events.hub', [$event, 'tab' => 'settings']) }}" wire:navigate
                   class="ehx-header-icon" title="Event Settings">
                    <x-icon name="cog" class="h-3.5 w-3.5" />
                </a>
            </span>
            <a href="{{ route('events.index') }}" class="ehx-btn ehx-btn-ghost">Portfolio</a>
            <a href="{{ route('events.hub', [$event, 'tab' => $header['critical']['tab'] ?? 'overview']) }}" class="ehx-btn ehx-btn-primary">
                {{ $header['critical']['cta'] ?? 'Open Event' }}
            </a>

            <div class="ehx-health-ring-wrap">
                <div class="ehx-health-ring" style="--ehx-ring-color: {{ $healthColor }}; --ehx-ring-pct: {{ $healthPct }}%">
                    <span class="ehx-health-ring-value" style="color: {{ $healthColor }}">{{ $health['score'] !== null ? $health['score'] : '—' }}</span>
                </div>
                <p class="ehx-health-ring-label">Health</p>
                <p class="ehx-health-ring-status" style="color: {{ $healthColor }}">{{ $healthLabel }}</p>
            </div>
        </div>
    </div>

    <div class="ehx-header-stats">
        @foreach ($stats as $s)
            <div class="ehx-header-stat">
                <span class="ehx-header-stat-icon is-{{ $s['tone'] }}">
                    <x-icon :name="$s['icon']" class="h-4 w-4" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="ehx-header-stat-label">{{ $s['label'] }}</p>
                    <p class="ehx-header-stat-value" style="color: {{ $s['color'] }}">{{ $s['value'] }}</p>
                    <p class="ehx-header-stat-sub" style="color: {{ $s['color'] }}">{{ $s['sub'] }}</p>
                    @if ($s['bar'] !== null)
                        <span class="ehx-header-stat-bar"><span class="ehx-header-stat-bar-fill is-{{ $s['tone'] }}" style="width: {{ $s['bar'] }}%"></span></span>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
