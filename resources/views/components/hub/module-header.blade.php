@props(['event', 'header', 'tab'])

@php
    $m = \App\Support\HubModuleInspector::data($event, $header, $tab);
    $statusColor = match (true) {
        $m['pct'] === null || $m['pct'] === 0 => 'var(--color-muted)',
        $m['pct'] >= 60 => 'var(--color-success)',
        default => 'var(--color-danger)',
    };
@endphp

<div class="ehx-module-header">
    <div class="ehx-module-header-top">
        <span class="ehx-module-header-icon" style="background: color-mix(in srgb, {{ $m['color'] }} 16%, transparent); color: {{ $m['color'] }}">
            <x-icon :name="$m['icon']" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-[17px] font-extrabold text-ink">{{ $m['label'] }}</h2>
                <span class="ehx-pill" style="background: color-mix(in srgb, {{ $statusColor }} 16%, transparent); color: {{ $statusColor }}">
                    {{ $m['statusWord'] }}
                </span>
            </div>
            @if ($m['purpose'])
                <p class="mt-0.5 text-[12px] text-muted">{{ $m['purpose'] }}</p>
            @endif
        </div>

        <div class="flex shrink-0 items-center gap-2">
            <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" class="ehx-btn ehx-btn-primary !py-1.5 !px-3 !text-[11.5px]">
                Open {{ $m['label'] }}
            </a>
        </div>
    </div>

    <div class="ehx-module-header-row">
        @if ($m['pct'] !== null)
            <div class="ehx-module-header-stat">
                <span class="ehx-module-header-stat-value" style="color: {{ $statusColor }}">{{ $m['pct'] }}%</span>
                <span class="ehx-module-header-stat-label">Readiness</span>
            </div>
        @endif

        @foreach ($m['metrics'] ?? [] as $metric)
            <div class="ehx-module-header-stat">
                <span class="ehx-module-header-stat-value">{{ $metric['value'] }}</span>
                <span class="ehx-module-header-stat-label">{{ $metric['label'] }}</span>
            </div>
        @endforeach

        <div class="ehx-module-header-stat">
            <span class="ehx-module-header-stat-value !text-[13px]">{{ $m['owner']?->name ?? 'No owner assigned' }}</span>
            <span class="ehx-module-header-stat-label">Owner</span>
        </div>

        @if ($m['nextAction'])
            <div class="ehx-module-header-next">
                <span class="ehx-module-header-stat-label">Next Action</span>
                <a href="{{ route('events.hub', [$event, 'tab' => $m['nextAction']['tab']]) }}" wire:navigate class="ehx-module-header-stat-value !text-[13px] hover:underline">
                    {{ $m['nextAction']['title'] }} →
                </a>
            </div>
        @endif
    </div>
</div>
