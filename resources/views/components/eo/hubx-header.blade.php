@props(['event', 'header'])

@php
    $title = $header['title'];
    $readyPct = (int) ($header['readiness']['pct'] ?? 0);
    $where = collect([$event->city, $event->country])->filter()->implode(', ');
    $when = $event->starts_at
        ? $event->starts_at->format('j M').' – '.($event->ends_at?->format('j M Y') ?? $event->starts_at->format('Y'))
        : 'Dates to be set';
    $type = str($event->type ?? 'Event')->replace('_', ' ')->title();
    $stage = \App\Support\Workflow::label('event_stage', $event->stage);
    $live = $header['live'];

    $stageTone = match (true) {
        str_contains(strtolower($stage), 'complet') => ['bg' => 'var(--color-eo-ok-soft)', 'text' => '#15803d'],
        str_contains(strtolower($stage), 'live') || str_contains(strtolower($stage), 'progress') => ['bg' => 'var(--color-eo-teal-soft)', 'text' => 'var(--color-eo-teal-ink)'],
        default => ['bg' => 'var(--color-eo-warn-soft)', 'text' => '#92400e'],
    };
@endphp

<div class="hubx-header">
    <div class="min-w-0 flex-1">
        <div class="mb-2 flex flex-wrap items-center gap-2 max-w-full">
            <span class="hubx-pill" style="background: {{ $stageTone['bg'] }}; color: {{ $stageTone['text'] }};">{{ $stage }}</span>
            <span class="hubx-pill" style="background: var(--color-eo-teal-soft); color: var(--color-eo-teal-ink);">{{ $type }} · Delegate Journey</span>
            @if (! empty($live['label']))
                <span class="hubx-pill" style="background: {{ $live['tone'] === 'bg-risk' ? 'var(--color-eo-risk-soft)' : ($live['tone'] === 'bg-warn' ? 'var(--color-eo-warn-soft)' : 'var(--color-eo-ok-soft)') }}; color: {{ $live['tone'] === 'bg-risk' ? '#b91c1c' : ($live['tone'] === 'bg-warn' ? '#92400e' : '#15803d') }};">
                    {{ $live['label'] }}
                </span>
            @endif
        </div>

        <h1 class="hubx-header-name">
            {{ $title['lead'] }}@if ($title['tail'])<span class="font-medium text-eo-muted"> · {{ $title['tail'] }}</span>@endif
        </h1>

        <p class="hubx-header-meta">
            <span>{{ $where ?: 'Venue TBC' }}</span>
            <span class="hubx-header-dot">{{ $when }}</span>
            @if ($event->client)
                <span class="hubx-header-dot">{{ $event->client->name }}</span>
            @endif
        </p>
    </div>

    <div class="flex shrink-0 items-center gap-2">
        <x-eo.button variant="ghost" size="sm" href="{{ route('events.index') }}">Portfolio</x-eo.button>
        <x-eo.button size="sm" href="{{ route('events.hub', [$event, 'tab' => $header['critical']['tab'] ?? 'overview']) }}">
            {{ $header['critical']['cta'] ?? 'Open Event' }}
        </x-eo.button>
    </div>
</div>
