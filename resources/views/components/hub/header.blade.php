@props(['event', 'header'])

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
@endphp

<div class="ehx-header">
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

    <div class="flex shrink-0 items-center gap-2">
        <span class="ehx-header-icon-row">
            <span class="ehx-header-icon" title="Star"><x-icon name="star" class="h-3.5 w-3.5" /></span>
            <span class="ehx-header-icon" title="Expand"><x-icon name="grid" class="h-3.5 w-3.5" /></span>
            <button type="button" class="ehx-header-icon" title="Event Utilities" @click="utilitiesOpen = true">
                <x-icon name="dots" class="h-3.5 w-3.5" />
            </button>
        </span>
        <a href="{{ route('events.index') }}" class="ehx-btn ehx-btn-ghost">Portfolio</a>
        <a href="{{ route('events.hub', [$event, 'tab' => $header['critical']['tab'] ?? 'overview']) }}" class="ehx-btn ehx-btn-primary">
            {{ $header['critical']['cta'] ?? 'Open Event' }}
        </a>
    </div>
</div>
