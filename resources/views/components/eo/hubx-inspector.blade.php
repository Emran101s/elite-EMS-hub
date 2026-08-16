@props(['event', 'header', 'tab'])

{{--
    Universal Right Module Inspector — reads the same per-module view-model
    as the Universal Module Header (app/Support/HubModuleInspector.php), so
    the two surfaces can never disagree about the same module's numbers.
--}}

@php
    $m = \App\Support\HubModuleInspector::data($event, $header, $tab);
    $statusColor = match (true) {
        $m['pct'] === null || $m['pct'] === 0 => '#94A3B8',
        $m['pct'] >= 60 => 'var(--color-eo-ok)',
        default => 'var(--color-eo-risk)',
    };
@endphp

<div class="hubx-panel">
    <div class="hubx-panel-head">
        <span class="hubx-panel-icon" style="background: color-mix(in srgb, {{ $m['color'] }} 16%, transparent); color: {{ $m['color'] }}">
            <x-icon :name="$m['icon']" class="h-4 w-4" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="eo-label !text-[9.5px]">Inspector</p>
            <p class="text-[14px] font-extrabold text-eo-text">{{ $m['label'] }}</p>
        </div>
        <span class="hubx-pill" style="background: color-mix(in srgb, {{ $statusColor }} 16%, transparent); color: {{ $statusColor }}">
            {{ $m['statusWord'] }}
        </span>
    </div>

    @if ($m['pct'] !== null)
        <div class="hubx-panel-ring-wrap" style="--pr-pct: {{ $m['pct'] }}">
            <div class="hubx-panel-ring-inner">
                <span class="text-[20px] font-extrabold text-eo-text">{{ $m['pct'] }}%</span>
                <span class="text-[9px] font-bold uppercase tracking-wide text-eo-muted">Ready</span>
            </div>
        </div>
    @endif

    @if ($m['metrics'])
        <div class="mt-2">
            @foreach ($m['metrics'] as $metric)
                <div class="hubx-panel-metric-row">
                    <x-icon :name="$metric['icon']" class="h-3.5 w-3.5 text-eo-muted" />
                    <span class="text-[12px] font-bold text-eo-text">{{ $metric['value'] }}</span>
                    <span class="text-[11px] text-eo-muted">{{ $metric['label'] }}</span>
                </div>
            @endforeach
        </div>
    @else
        <p class="mt-3 text-[11.5px] text-eo-muted">No dedicated metrics for this module yet — open it directly for the full picture.</p>
    @endif

    @if ($m['owner'])
        <div class="mt-3">
            <p class="eo-label !text-[9.5px]">Ownership</p>
            <div class="mt-1.5 flex items-center gap-2">
                <x-user-avatar :user="$m['owner']" size="h-8 w-8" text="text-[10px]" />
                <div class="min-w-0">
                    <p class="truncate text-[12px] font-bold text-eo-text">{{ $m['owner']->name }}</p>
                    <p class="text-[10px] text-eo-muted">Event Owner</p>
                </div>
            </div>
        </div>
    @else
        <p class="mt-3 text-[10.5px] text-eo-muted">No owner assigned</p>
    @endif

    @if ($m['nextAction'])
        <div class="hubx-panel-next">
            <p class="text-[9.5px] font-bold uppercase tracking-wide text-white/60">Next Action</p>
            <p class="mt-1 text-[13px] font-bold">{{ $m['nextAction']['title'] }}</p>
            <p class="mt-0.5 text-[11px] text-white/60">{{ $m['nextAction']['due'] }} · {{ $m['nextAction']['level'] }} impact</p>
            <a href="{{ route('events.hub', [$event, 'tab' => $m['nextAction']['tab']]) }}" wire:navigate
               class="mt-2.5 inline-flex items-center justify-center rounded-lg bg-white px-3 py-1.5 text-[11px] font-bold text-eo-navy-deep">
                {{ $m['nextAction']['cta'] }}
            </a>
        </div>
    @endif

    @if ($m['recent']->isNotEmpty())
        <div class="mt-3">
            <p class="eo-label !text-[9.5px]">Recent Activity</p>
            @foreach ($m['recent'] as $entry)
                <div class="hubx-panel-activity-row">
                    <x-user-avatar :user="$entry->user" size="h-6 w-6" text="text-[9px]" />
                    <div class="min-w-0">
                        <p class="truncate text-[11.5px] font-semibold text-eo-text">{{ $entry->summary() }}</p>
                        <p class="text-[10px] text-eo-muted">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <p class="mt-3 text-[10.5px] text-eo-muted">No recent activity</p>
    @endif

    @if (count($m['quickLinks']) > 1)
        <div class="mt-3">
            <p class="eo-label !text-[9.5px]">Quick Links</p>
            <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                @foreach ($m['quickLinks'] as $link)
                    <a href="{{ route('events.hub', [$event, 'tab' => $link['tab']]) }}" wire:navigate
                       class="flex flex-col items-center gap-1 rounded-xl border border-eo-line bg-white/70 px-2 py-2 text-center transition hover:border-eo-teal">
                        <x-icon :name="$link['icon']" class="h-3.5 w-3.5 text-eo-muted" />
                        <span class="text-[9.5px] font-bold text-eo-text">{{ $link['label'] }}</span>
                    </a>
                @endforeach
            </div>
        </div>
    @endif

    <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" wire:navigate class="hubx-stack-viewall mt-2" style="color: var(--color-eo-teal-ink); background: var(--color-eo-teal-soft);">
        Open {{ $m['label'] }} →
    </a>
</div>
