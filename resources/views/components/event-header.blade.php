@props(['event', 'header'])

{{-- Soft Command Event Hub header — identity, health, readiness, next action. --}}

@php
    $title = $header['title'];
    $critical = $header['critical'];
    $readiness = $header['readiness'];
    $live = $header['live'];

    $readyPct = (int) ($readiness['pct'] ?? 0);

    $where = collect([$event->city, $event->country])->filter()->implode(', ');
    $when = $event->starts_at
        ? $event->starts_at->format('j M').' – '.($event->ends_at?->format('j M Y') ?? $event->starts_at->format('Y'))
        : null;

    $type = str($event->type ?? 'Event')->replace('_', ' ')->title();
    $stage = \App\Support\Workflow::label('event_stage', $event->stage);

    $criticalHref = $critical
        ? route('events.hub', [$event, 'tab' => $critical['tab'] ?? 'overview'])
        : null;
@endphp

<div class="eo-domain-card eo-mission-card overflow-hidden !p-0">
    <div class="flex flex-wrap items-start gap-4 px-5 py-4 lg:px-6">
        <span class="relative grid h-12 w-12 shrink-0 place-items-center overflow-hidden rounded-2xl bg-eo-navy ring-1 ring-eo-teal/30">
            @if ($event->logoUrl())
                <img src="{{ $event->logoUrl() }}" alt="" class="h-full w-full object-cover">
            @else
                <x-event-crest :event="$event" class="h-full w-full" />
            @endif
        </span>

        <div class="min-w-0 flex-1">
            <div class="mb-2 flex flex-wrap items-center gap-2">
                <span class="eo-journey-chip">Event DNA · {{ $type }}</span>
                <x-eo.status-pill tone="live">{{ $stage }}</x-eo.status-pill>
                <span class="eo-dna-pill !normal-case !tracking-normal">{{ $readyPct }}% readiness</span>
                @if (! empty($live['label']))
                    <x-eo.status-pill :tone="str_contains($live['tone'] ?? '', 'risk') ? 'risk' : (str_contains($live['tone'] ?? '', 'warn') ? 'warn' : 'ok')">
                        {{ $live['label'] }}
                    </x-eo.status-pill>
                @endif
            </div>

            <h1 class="eo-title truncate text-[20px] lg:text-[24px]">
                {{ $title['lead'] }}@if ($title['tail'])<span class="font-medium text-eo-muted"> · {{ $title['tail'] }}</span>@endif
            </h1>
            <p class="mt-1 text-[13px] text-eo-muted">
                {{ collect([$where, $when, $event->client?->name, 'Delegate journey'])->filter()->implode(' · ') }}
            </p>
        </div>

        <div class="flex flex-wrap gap-2">
            <x-eo.button variant="ghost" size="sm" href="{{ route('events.index') }}">Portfolio</x-eo.button>
            @if ($criticalHref)
                <x-eo.button size="sm" href="{{ $criticalHref }}">{{ $critical['cta'] ?? 'Next action' }}</x-eo.button>
            @endif
        </div>
    </div>

    {{-- Phase E: the 3-card grid (health / readiness / live desk) and the
         alert-card banner below it are now one card — same figures, same
         data (EventCommandHeader::for()), no longer told three times. --}}
    <div class="border-t border-eo-line bg-eo-workspace/70 px-5 py-4 lg:px-6">
        <x-eo.mission-card variant="hub" :event="$event" :header="$header" class="!shadow-none" />
    </div>
</div>
