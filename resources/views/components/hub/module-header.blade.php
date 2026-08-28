@props(['event', 'header', 'tab'])

@php
    $m = \App\Support\HubModuleInspector::data($event, $header, $tab);
    $statusTone = match (true) {
        $m['pct'] === null || $m['pct'] === 0 => 'tone-muted',
        $m['pct'] >= 60 => 'tone-ok',
        default => 'tone-risk',
    };
    $statusValueColor = match ($statusTone) {
        'tone-ok' => 'var(--cx-ok)',
        'tone-risk' => 'var(--cx-risk)',
        default => 'rgba(234,240,251,.5)',
    };

    // A module with no readiness figure and no metrics (Overview, Reports)
    // would otherwise spend a whole second band of navy on one owner name.
    // In that case the owner folds up into the top row instead.
    $hasStats = $m['pct'] !== null || ! empty($m['metrics']) || $m['nextAction'];
@endphp

<div class="cx-canvas" style="padding: 0 0 16px">
    <div class="cx-modhero">
        <div class="cx-modhero-top">
            {{-- A light tint of the module's colour, not a transparent wash.
                 Overview's own colour IS the hero navy (#0B1F3A), so mixing
                 it toward transparent made the badge and its icon disappear
                 into the background entirely. Mixing toward white instead
                 gives every module a hex that reads on navy while keeping
                 its own hue, with the full-strength colour as the icon. --}}
            <span class="cx-modhero-hex" style="background: color-mix(in srgb, {{ $m['color'] }} 22%, white); color: {{ $m['color'] }}">
                <x-icon :name="$m['icon']" class="h-[18px] w-[18px]" />
            </span>

            <div class="min-w-0 flex-1">
                <div class="cx-modhero-title">
                    <h2>{{ $m['label'] }}</h2>
                    <span class="cx-tag {{ $statusTone }}">{{ $m['statusWord'] }}</span>
                </div>
                @if ($m['purpose'])
                    <p class="cx-modhero-purpose">{{ $m['purpose'] }}</p>
                @endif
                @unless ($hasStats)
                    <p class="cx-modhero-purpose" style="margin-top:3px">
                        <span style="color: rgba(234,240,251,.85); font-weight:600">{{ $m['owner']?->name ?? 'No owner assigned' }}</span>
                        <span style="opacity:.6"> · Owner</span>
                    </p>
                @endunless
            </div>

            <div class="cx-modhero-cta">
                <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" class="cx-btn cx-btn-accent">
                    Open {{ $m['label'] }}
                </a>
            </div>
        </div>

        @if ($hasStats)
        <div class="cx-modhero-stats">
            @if ($m['pct'] !== null)
                <div class="cx-modstat">
                    <span class="cx-msv" style="color: {{ $statusValueColor }}">{{ $m['pct'] }}%</span>
                    <span class="cx-msl">Readiness</span>
                </div>
            @endif

            @foreach ($m['metrics'] ?? [] as $metric)
                <div class="cx-modstat">
                    <span class="cx-msv">{{ $metric['value'] }}</span>
                    <span class="cx-msl">{{ $metric['label'] }}</span>
                </div>
            @endforeach

            <div class="cx-modstat">
                <span class="cx-msv" style="font-size: 13px">{{ $m['owner']?->name ?? 'No owner assigned' }}</span>
                <span class="cx-msl">Owner</span>
            </div>

            @if ($m['nextAction'])
                <div class="cx-modhero-next">
                    <span class="cx-msl">Next Action</span>
                    <a href="{{ route('events.hub', [$event, 'tab' => $m['nextAction']['tab']]) }}" wire:navigate>
                        {{ $m['nextAction']['title'] }} →
                    </a>
                </div>
            @endif
        </div>
        @endif
    </div>
</div>
