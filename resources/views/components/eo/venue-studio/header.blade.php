@props(['venue', 'header'])

@php
    $healthColor = match ($header['health']['status']) {
        'track' => '#15803d', 'warn' => '#92400e', 'risk' => '#b91c1c', default => 'var(--color-eo-muted)',
    };
@endphp

<div class="hubx-header">
    <div class="flex min-w-0 flex-1 items-start gap-3.5">
        <span class="hubx-header-avatar shrink-0" aria-hidden="true">
            <x-icon name="building" class="h-5 w-5" />
        </span>

        <div class="min-w-0 flex-1">
            <div class="mb-2 flex flex-wrap items-center gap-2 max-w-full">
                @if ($venue->type)
                    <span class="hubx-pill" style="background: var(--color-eo-teal-soft); color: var(--color-eo-teal-ink);">{{ $venue->type }}</span>
                @endif
                <span class="hubx-pill" style="background: color-mix(in srgb, {{ $healthColor }} 16%, transparent); color: {{ $healthColor }}">
                    Health {{ $header['health']['score'] }}
                </span>
                @if ($header['contract'])
                    <span class="hubx-pill {{ $header['contract']['class'] }}">Contract {{ $header['contract']['label'] }}</span>
                @endif
            </div>

            <h1 class="hubx-header-name">{{ $venue->name }}</h1>

            <p class="hubx-header-meta">
                <span>{{ $venue->locationLine() ?: 'Location not set' }}</span>
                @if ($venue->capacity)
                    <span class="hubx-header-dot">{{ number_format($venue->capacity) }} capacity</span>
                @endif
            </p>
        </div>
    </div>

    <div class="flex shrink-0 items-center gap-2">
        <x-eo.button variant="ghost" size="sm" href="{{ route('venues.index') }}">All Venues</x-eo.button>
        <x-eo.button size="sm" href="{{ route('venues.show', [$venue, 'tab' => 'spaces']) }}">Space Explorer</x-eo.button>
    </div>
</div>
