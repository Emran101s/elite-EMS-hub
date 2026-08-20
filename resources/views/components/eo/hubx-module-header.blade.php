@props(['event', 'header', 'tab'])

{{--
    Universal Module Header — shown at the top of a module's own tab
    content (not the event-wide identity header above it). Same shell for
    every module: icon, name, purpose line, readiness, status, owner, key
    metrics, next action, and — only where the module's own Livewire
    component actually supports it (?action=add on mount) — a real "Add"
    button, never an invented one.

    Reads app/Support/HubModuleInspector.php, the same view-model the
    right-side Inspector reads, so the two can't disagree.
--}}

@php
    $m = \App\Support\HubModuleInspector::data($event, $header, $tab);
    $statusColor = match (true) {
        $m['pct'] === null || $m['pct'] === 0 => '#94A3B8',
        $m['pct'] >= 60 => 'var(--color-eo-ok)',
        default => 'var(--color-eo-risk)',
    };
@endphp

<div class="hubx-module-header">
    <div class="hubx-module-header-top">
        <span class="hubx-module-header-icon" style="background: color-mix(in srgb, {{ $m['color'] }} 16%, transparent); color: {{ $m['color'] }}">
            <x-icon :name="$m['icon']" class="h-5 w-5" />
        </span>
        <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-center gap-2">
                <h2 class="text-[17px] font-extrabold text-eo-text">{{ $m['label'] }}</h2>
                <span class="hubx-pill" style="background: color-mix(in srgb, {{ $statusColor }} 16%, transparent); color: {{ $statusColor }}">
                    {{ $m['statusWord'] }}
                </span>
            </div>
            @if ($m['purpose'])
                <p class="mt-0.5 text-[12px] text-eo-muted">{{ $m['purpose'] }}</p>
            @endif
        </div>

        {{-- The module's own Add action lives in the Inspector now (its
             "Action" card) — Header's job is identity/readiness/metrics/
             owner/next-action, not initiating work, so it no longer
             duplicates that CTA here. --}}
        <div class="flex shrink-0 items-center gap-2">
            <x-eo.button size="sm" href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}">
                Open {{ $m['label'] }}
            </x-eo.button>
        </div>
    </div>

    <div class="hubx-module-header-row">
        @if ($m['pct'] !== null)
            <div class="hubx-module-header-stat">
                <span class="hubx-module-header-stat-value" style="color: {{ $statusColor }}">{{ $m['pct'] }}%</span>
                <span class="hubx-module-header-stat-label">Readiness</span>
            </div>
        @endif

        @foreach ($m['metrics'] ?? [] as $metric)
            <div class="hubx-module-header-stat">
                <span class="hubx-module-header-stat-value">{{ $metric['value'] }}</span>
                <span class="hubx-module-header-stat-label">{{ $metric['label'] }}</span>
            </div>
        @endforeach

        <div class="hubx-module-header-stat">
            <span class="hubx-module-header-stat-value !text-[13px]">{{ $m['owner']?->name ?? 'No owner assigned' }}</span>
            <span class="hubx-module-header-stat-label">Owner</span>
        </div>

        @if ($m['nextAction'])
            <div class="hubx-module-header-next">
                <span class="hubx-module-header-stat-label">Next Action</span>
                <a href="{{ route('events.hub', [$event, 'tab' => $m['nextAction']['tab']]) }}" wire:navigate class="hubx-module-header-stat-value !text-[13px] hover:underline">
                    {{ $m['nextAction']['title'] }} →
                </a>
            </div>
        @endif
    </div>
</div>
