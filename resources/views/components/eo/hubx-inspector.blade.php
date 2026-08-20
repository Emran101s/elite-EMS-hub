@props(['event', 'header', 'tab'])

{{--
    Universal Right Inspector — reads the same per-module view-model as the
    Universal Module Header (app/Support/HubModuleInspector.php) so the two
    can never disagree, but the two have different jobs. The Header owns
    identity, readiness, metrics and the next action; this owns ownership
    detail, actions, recent activity and related modules — it never repeats
    the Header's own readiness ring or metric rows. Overview has no single
    module to inspect, so it reads HubModuleInspector::overview() instead —
    event-level readiness gates and cross-module attention.
--}}

@php
    $isOverview = $tab === 'overview';
    $m = $isOverview
        ? \App\Support\HubModuleInspector::overview($event, $header)
        : \App\Support\HubModuleInspector::data($event, $header, $tab);
    $statusColor = match (true) {
        $m['pct'] === null || $m['pct'] === 0 => '#94A3B8',
        $m['pct'] >= 60 => 'var(--color-eo-ok)',
        default => 'var(--color-eo-risk)',
    };
    // A module Inspector is genuinely empty only when it has neither
    // progress nor a paper trail yet — that's when a bare "No owner" /
    // "No recent activity" pair would otherwise leave the panel mostly
    // blank, so it gets one intentional empty-state message instead.
    $isEmpty = ! $isOverview && ($m['pct'] === null || $m['pct'] === 0) && $m['recent']->isEmpty();
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

    <div class="hubx-panel-detail">
        {{-- Ownership — every Inspector, module or event-level, opens with who owns it. --}}
        @if ($m['owner'])
            <div class="hubx-panel-owner">
                <x-user-avatar :user="$m['owner']" size="h-6 w-6" text="text-[9px]" />
                <p class="truncate text-[11px] text-eo-muted"><span class="font-semibold text-eo-text">{{ $m['owner']->name }}</span> · Owner</p>
            </div>
        @else
            <p class="text-[10.5px] text-eo-muted">No owner assigned</p>
        @endif

        @if ($isOverview)
            {{-- Readiness Gates — the detail behind Event Pulse's bare %,
                 gate by gate rather than a single number. --}}
            @if (! empty($m['gates']))
                <div>
                    <p class="eo-label !text-[9px] opacity-70">Readiness Gates</p>
                    <div class="mt-1.5">
                        @foreach ($m['gates'] as $gate)
                            <div class="hubx-panel-gate-row">
                                <span class="hubx-panel-gate-dot" style="background: {{ $gate['met'] ? 'var(--color-eo-ok)' : 'var(--color-eo-warn)' }}"></span>
                                <span class="text-[11.5px] font-semibold text-eo-text">{{ $gate['label'] }}</span>
                                <span class="ml-auto shrink-0 text-[10px] text-eo-muted">{{ $gate['note'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="hubx-panel-tertiary">
                {{-- Needs Attention — the same signals the Module Nav Bar's
                     badges carry, spelled out with a real reason and a way
                     in, worst first. --}}
                <p class="eo-label !text-[9px] opacity-70">Needs Attention</p>
                @if ($m['attention']->isNotEmpty())
                    @foreach ($m['attention'] as $signal)
                        <a href="{{ route('events.hub', [$event, 'tab' => $signal['tab']]) }}" wire:navigate class="hubx-panel-attention-row">
                            <x-icon :name="$signal['icon']" class="h-3.5 w-3.5 text-eo-muted" />
                            <span class="text-[11.5px] font-semibold text-eo-text">{{ $signal['label'] }}</span>
                            <span class="ml-auto shrink-0 text-[10.5px] text-eo-muted">{{ $signal['why'] }}</span>
                        </a>
                    @endforeach
                @else
                    <p class="text-[10px] text-eo-muted opacity-75">Nothing needs a person right now.</p>
                @endif

                @if ($m['recent']->isNotEmpty())
                    <div class="mt-2.5">
                        <p class="eo-label !text-[9px] opacity-70">Recent Activity</p>
                        @foreach ($m['recent'] as $entry)
                            <div class="hubx-panel-activity-row">
                                <x-user-avatar :user="$entry->user" size="h-5 w-5" text="text-[8px]" />
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] text-eo-muted">{{ $entry->summary() }}</p>
                                    <p class="text-[9.5px] text-eo-muted opacity-75">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-2.5">
                    <p class="eo-label !text-[9px] opacity-70">Related Modules</p>
                    <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                        @foreach ($m['quickLinks'] as $link)
                            <a href="{{ route('events.hub', [$event, 'tab' => $link['tab']]) }}" wire:navigate
                               class="flex flex-col items-center gap-1 rounded-xl border border-eo-line bg-white/70 px-2 py-1.5 text-center transition hover:border-eo-teal">
                                <x-icon :name="$link['icon']" class="h-3 w-3 text-eo-muted" />
                                <span class="text-[9px] font-semibold text-eo-muted">{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            {{-- Actions — a real, module-specific action, never an invented
                 one: only where the module's own Livewire component
                 actually honours ?action=add on mount. --}}
            @if ($m['supportsAdd'])
                <a href="{{ route('events.hub', [$event, 'tab' => $m['tab'], 'action' => 'add']) }}" wire:navigate class="hubx-panel-action">
                    <p class="text-[9.5px] font-bold uppercase tracking-wide text-white/60">Action</p>
                    <p class="mt-1 text-[13px] font-bold">+ {{ $m['addLabel'] }}</p>
                </a>
            @endif

            <div class="hubx-panel-tertiary">
                @if ($isEmpty)
                    {{-- One intentional empty state instead of a bare "No
                         recent activity" line and a lot of unused space. --}}
                    <div class="hubx-panel-empty">
                        <span class="hubx-panel-empty-icon" style="background: color-mix(in srgb, {{ $m['color'] }} 14%, transparent); color: {{ $m['color'] }}">
                            <x-icon :name="$m['icon']" class="h-4 w-4" />
                        </span>
                        <p class="text-[11.5px] font-bold text-eo-text">Nothing logged yet</p>
                        @if ($m['purpose'])
                            <p class="mt-0.5 text-[10.5px] text-eo-muted">{{ $m['purpose'] }}</p>
                        @endif
                        @unless ($m['supportsAdd'])
                            <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" wire:navigate class="hubx-panel-empty-cta">
                                Open {{ $m['label'] }} →
                            </a>
                        @endunless
                    </div>
                @else
                    @if ($m['recent']->isNotEmpty())
                        <div>
                            <p class="eo-label !text-[9px] opacity-70">Recent Activity</p>
                            @foreach ($m['recent'] as $entry)
                                <div class="hubx-panel-activity-row">
                                    <x-user-avatar :user="$entry->user" size="h-5 w-5" text="text-[8px]" />
                                    <div class="min-w-0">
                                        <p class="truncate text-[11px] text-eo-muted">{{ $entry->summary() }}</p>
                                        <p class="text-[9.5px] text-eo-muted opacity-75">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-[10px] text-eo-muted opacity-75">No recent activity</p>
                    @endif
                @endif

                @if (count($m['quickLinks']) > 1)
                    <div class="mt-2.5">
                        <p class="eo-label !text-[9px] opacity-70">Related Modules</p>
                        <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                            @foreach ($m['quickLinks'] as $link)
                                <a href="{{ route('events.hub', [$event, 'tab' => $link['tab']]) }}" wire:navigate
                                   class="flex flex-col items-center gap-1 rounded-xl border border-eo-line bg-white/70 px-2 py-1.5 text-center transition hover:border-eo-teal">
                                    <x-icon :name="$link['icon']" class="h-3 w-3 text-eo-muted" />
                                    <span class="text-[9px] font-semibold text-eo-muted">{{ $link['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @unless ($isOverview)
        <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" wire:navigate class="hubx-panel-viewall mt-2" style="color: var(--color-eo-teal-ink); background: var(--color-eo-teal-soft);">
            Open {{ $m['label'] }} →
        </a>
    @endunless
</div>
