@props(['event', 'header', 'tab'])

@php
    $isOverview = $tab === 'overview';
    $m = $isOverview
        ? \App\Support\HubModuleInspector::overview($event, $header)
        : \App\Support\HubModuleInspector::data($event, $header, $tab);
    $statusColor = match (true) {
        $m['pct'] === null || $m['pct'] === 0 => 'var(--color-muted)',
        $m['pct'] >= 60 => 'var(--color-success)',
        default => 'var(--color-danger)',
    };
    $isEmpty = ! $isOverview && ($m['pct'] === null || $m['pct'] === 0) && $m['recent']->isEmpty();
@endphp

<div class="ehx-panel">
    <div class="ehx-panel-head">
        <span class="ehx-panel-icon" style="background: color-mix(in srgb, {{ $m['color'] }} 16%, transparent); color: {{ $m['color'] }}">
            <x-icon :name="$m['icon']" class="h-4 w-4" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9.5px]">Inspector</p>
            <p class="text-[14px] font-extrabold text-ink">{{ $m['label'] }}</p>
        </div>
        <span class="ehx-pill" style="background: color-mix(in srgb, {{ $statusColor }} 16%, transparent); color: {{ $statusColor }}">
            {{ $m['statusWord'] }}
        </span>
    </div>

    <div class="ehx-panel-detail">
        @if ($m['owner'])
            <div class="ehx-panel-owner">
                <x-user-avatar :user="$m['owner']" size="h-6 w-6" text="text-[9px]" />
                <p class="truncate text-[11px] text-muted"><span class="font-semibold text-ink">{{ $m['owner']->name }}</span> · Owner</p>
            </div>
        @else
            <p class="text-[10.5px] text-muted">No owner assigned</p>
        @endif

        @if ($isOverview)
            @if (! empty($m['gates']))
                <div>
                    <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Readiness Gates</p>
                    <div class="mt-1.5">
                        @foreach ($m['gates'] as $gate)
                            <div class="ehx-panel-gate-row">
                                <span class="ehx-panel-gate-dot" style="background: {{ $gate['met'] ? 'var(--color-success)' : 'var(--color-warning)' }}"></span>
                                <span class="text-[11.5px] font-semibold text-ink">{{ $gate['label'] }}</span>
                                <span class="ml-auto shrink-0 text-[10px] text-muted">{{ $gate['note'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="ehx-panel-tertiary">
                <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Needs Attention</p>
                @if ($m['attention']->isNotEmpty())
                    @foreach ($m['attention'] as $signal)
                        <a href="{{ route('events.hub', [$event, 'tab' => $signal['tab']]) }}" wire:navigate class="ehx-panel-attention-row">
                            <x-icon :name="$signal['icon']" class="h-3.5 w-3.5 text-muted" />
                            <span class="text-[11.5px] font-semibold text-ink">{{ $signal['label'] }}</span>
                            <span class="ml-auto shrink-0 text-[10.5px] text-muted">{{ $signal['why'] }}</span>
                        </a>
                    @endforeach
                @else
                    <p class="text-[10px] text-muted opacity-75">Nothing needs a person right now.</p>
                @endif

                @if ($m['recent']->isNotEmpty())
                    <div class="mt-2.5">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Recent Activity</p>
                        @foreach ($m['recent'] as $entry)
                            <div class="ehx-panel-activity-row">
                                <x-user-avatar :user="$entry->user" size="h-5 w-5" text="text-[8px]" />
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] text-muted">{{ $entry->summary() }}</p>
                                    <p class="text-[9.5px] text-muted opacity-75">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="mt-2.5">
                    <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Related Modules</p>
                    <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                        @foreach ($m['quickLinks'] as $link)
                            <a href="{{ route('events.hub', [$event, 'tab' => $link['tab']]) }}" wire:navigate
                               class="flex flex-col items-center gap-1 rounded-xl border border-line bg-white/70 px-2 py-1.5 text-center transition hover:border-gold-400">
                                <x-icon :name="$link['icon']" class="h-3 w-3 text-muted" />
                                <span class="text-[9px] font-semibold text-muted">{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            @if ($m['supportsAdd'])
                <a href="{{ route('events.hub', [$event, 'tab' => $m['tab'], 'action' => 'add']) }}" wire:navigate class="ehx-panel-action">
                    <p class="text-[9.5px] font-bold uppercase tracking-wide text-white/60">Action</p>
                    <p class="mt-1 text-[13px] font-bold">+ {{ $m['addLabel'] }}</p>
                </a>
            @endif

            <div class="ehx-panel-tertiary">
                @if ($isEmpty)
                    <div class="ehx-panel-empty">
                        <span class="ehx-panel-empty-icon" style="background: color-mix(in srgb, {{ $m['color'] }} 14%, transparent); color: {{ $m['color'] }}">
                            <x-icon :name="$m['icon']" class="h-4 w-4" />
                        </span>
                        <p class="text-[11.5px] font-bold text-ink">Nothing logged yet</p>
                        @if ($m['purpose'])
                            <p class="mt-0.5 text-[10.5px] text-muted">{{ $m['purpose'] }}</p>
                        @endif
                        @unless ($m['supportsAdd'])
                            <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" wire:navigate class="ehx-panel-empty-cta">
                                Open {{ $m['label'] }} →
                            </a>
                        @endunless
                    </div>
                @else
                    @if ($m['recent']->isNotEmpty())
                        <div>
                            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Recent Activity</p>
                            @foreach ($m['recent'] as $entry)
                                <div class="ehx-panel-activity-row">
                                    <x-user-avatar :user="$entry->user" size="h-5 w-5" text="text-[8px]" />
                                    <div class="min-w-0">
                                        <p class="truncate text-[11px] text-muted">{{ $entry->summary() }}</p>
                                        <p class="text-[9.5px] text-muted opacity-75">{{ $entry->user?->name ?? 'System' }} · {{ $entry->created_at->diffForHumans() }}</p>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <p class="text-[10px] text-muted opacity-75">No recent activity</p>
                    @endif
                @endif

                @if (count($m['quickLinks']) > 1)
                    <div class="mt-2.5">
                        <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Related Modules</p>
                        <div class="mt-1.5 grid grid-cols-3 gap-1.5">
                            @foreach ($m['quickLinks'] as $link)
                                <a href="{{ route('events.hub', [$event, 'tab' => $link['tab']]) }}" wire:navigate
                                   class="flex flex-col items-center gap-1 rounded-xl border border-line bg-white/70 px-2 py-1.5 text-center transition hover:border-gold-400">
                                    <x-icon :name="$link['icon']" class="h-3 w-3 text-muted" />
                                    <span class="text-[9px] font-semibold text-muted">{{ $link['label'] }}</span>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endif
    </div>

    @unless ($isOverview)
        <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" wire:navigate class="ehx-panel-viewall mt-2" style="color: var(--color-gold-700); background: var(--color-gold-50);">
            Open {{ $m['label'] }} →
        </a>
    @endunless
</div>
