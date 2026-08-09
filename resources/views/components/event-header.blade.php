@props(['event', 'header'])

{{-- Soft Command Event Hub header — identity, health, readiness, next action. --}}

@php
    $title = $header['title'];
    $health = $header['health'];
    $critical = $header['critical'];
    $readiness = $header['readiness'];
    $live = $header['live'];

    $healthScore = $health['score'] ?? null;
    $healthStatus = match ($health['group'] ?? null) {
        'risk' => 'risk',
        'warn' => 'warn',
        default => $healthScore === null ? 'pending' : 'ok',
    };

    $readyPct = (int) ($readiness['pct'] ?? 0);
    $readyStatus = match (true) {
        $readyPct >= 70 => 'ok',
        $readyPct >= 40 => 'warn',
        default => 'risk',
    };

    $where = collect([$event->city, $event->country])->filter()->implode(', ');
    $when = $event->starts_at
        ? $event->starts_at->format('j M').' – '.($event->ends_at?->format('j M Y') ?? $event->starts_at->format('Y'))
        : null;

    $type = str($event->type ?? 'Event')->replace('_', ' ')->title();
    $stage = \App\Support\Workflow::label('event_stage', $event->stage);

    $attention = collect($header['attention'] ?? []);
    $openCount = (int) $attention->sum(fn ($a) => (int) ($a['count'] ?? 0));
    $alarmCount = (int) $attention->filter(fn ($a) => ($a['tone'] ?? null) === 'alarm')->sum(fn ($a) => (int) ($a['count'] ?? 0));

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

    <div class="grid gap-3 border-t border-eo-line bg-eo-workspace/70 px-5 py-4 sm:grid-cols-3 lg:px-6">
        <x-eo.event-health-card
            title="Event health"
            :score="(int) ($healthScore ?? 0)"
            :status="$healthStatus"
            hint="Mission health index"
            class="!shadow-none"
        />
        <x-eo.readiness-card
            domain="Operational readiness"
            title="Readiness gates"
            :percent="$readyPct"
            :status="$readyStatus"
            :hint="($readiness['met'] ?? 0).' of '.($readiness['total'] ?? 0).' checkpoints'"
            class="!shadow-none"
        />
        <x-eo.operations-card
            title="Live desk"
            subtitle="Attention across modules"
            :open="$openCount"
            :due="max(0, $openCount - $alarmCount)"
            :blocked="$alarmCount"
            class="!shadow-none"
        />
    </div>

    @if ($critical)
        <div class="border-t border-eo-line px-5 py-3 lg:px-6">
            <x-eo.alert-card
                :tone="in_array($critical['level'] ?? '', ['Critical', 'High'], true) ? 'risk' : 'warn'"
                :title="$critical['title']"
            >
                {{ $critical['where'] }} · {{ $critical['due'] }} · {{ $critical['owner'] }}
            </x-eo.alert-card>
        </div>
    @endif
</div>
