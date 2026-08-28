@props(['event', 'header', 'tab'])

@php
    $isOverview = $tab === 'overview';
    $m = $isOverview
        ? \App\Support\HubModuleInspector::overview($event, $header)
        : \App\Support\HubModuleInspector::data($event, $header, $tab);
    $statusTone = match (true) {
        $m['pct'] === null || $m['pct'] === 0 => 'tone-muted',
        $m['pct'] >= 60 => 'tone-ok',
        default => 'tone-risk',
    };
    $isEmpty = ! $isOverview && ($m['pct'] === null || $m['pct'] === 0) && $m['recent']->isEmpty();
@endphp

{{-- The inspector renders inside .ehx-col-panel, which is NOT inside any
     .cx-canvas — and every --cx-* token is defined on .cx-canvas. Without
     this wrapper the whole panel's colours resolve to nothing (navy head
     on navy text, unfilled status pill). Padding is zeroed because the
     canvas's own bottom padding is meant for a page, not a panel. --}}
<div class="cx-canvas" style="padding:0">
<div class="cx-panel">
    <div class="cx-lcard-head" style="background: var(--cx-espresso-1); border-bottom-color: transparent;">
        <span class="cx-cathex shrink-0" style="width:24px;height:26px;background: {{ $m['color'] }}">
            <x-icon :name="$m['icon']" class="h-3 w-3" />
        </span>
        <div class="min-w-0 flex-1">
            <p class="text-[9px] font-bold uppercase tracking-[0.14em]" style="color: rgba(240,231,213,.5)">Inspector</p>
            <p class="text-[13px] font-bold text-white">{{ $m['label'] }}</p>
        </div>
        <span class="cx-tag {{ $statusTone }}">{{ $m['statusWord'] }}</span>
    </div>

    <div class="cx-panel-sec space-y-2.5">
        @if ($m['owner'])
            <div class="flex items-center gap-2">
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
                            <div class="flex items-center gap-2 py-1">
                                <span class="cx-hexdot shrink-0" style="background: {{ $gate['met'] ? 'var(--cx-ok)' : 'var(--cx-warn)' }}"></span>
                                <span class="text-[11.5px] font-semibold text-ink">{{ $gate['label'] }}</span>
                                <span class="ml-auto shrink-0 text-[10px] text-muted">{{ $gate['note'] }}</span>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="space-y-2.5 border-t border-line pt-2.5">
                <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Needs Attention</p>
                @if ($m['attention']->isNotEmpty())
                    @foreach ($m['attention'] as $signal)
                        <a href="{{ route('events.hub', [$event, 'tab' => $signal['tab']]) }}" wire:navigate class="flex items-center gap-2 rounded-lg py-1 transition hover:bg-[var(--cx-surface-2)]">
                            <span class="cx-hexdot shrink-0" style="background: {{ $signal['tone'] === 'alarm' ? 'var(--cx-risk)' : 'var(--cx-warn)' }}"></span>
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
                            <div class="flex items-start gap-2 py-1">
                                <x-user-avatar :user="$entry->user" size="h-5 w-5" text="text-[8px]" />
                                <div class="min-w-0">
                                    <p class="truncate text-[11px] font-semibold text-ink">{{ $entry->describe() }}</p>
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
                               class="flex flex-col items-center gap-1 rounded-lg px-1 py-1.5 text-center transition hover:bg-[var(--cx-surface-2)]">
                                <span class="cx-cathex" style="width:20px;height:22px;background:var(--cx-surface-3);color:var(--cx-muted)"><x-icon :name="$link['icon']" class="h-2.5 w-2.5" /></span>
                                <span class="text-[9px] font-semibold text-muted">{{ $link['label'] }}</span>
                            </a>
                        @endforeach
                    </div>
                </div>
            </div>
        @else
            @if ($m['supportsAdd'])
                <a href="{{ route('events.hub', [$event, 'tab' => $m['tab'], 'action' => 'add']) }}" wire:navigate class="cx-spot-action block" style="margin-top:0">
                    <p class="text-[9.5px] font-bold uppercase tracking-wide text-white/60">Action</p>
                    <p class="mt-1 text-[13px] font-bold">+ {{ $m['addLabel'] }}</p>
                </a>
            @endif

            <div class="space-y-2.5 border-t border-line pt-2.5">
                @if ($isEmpty)
                    <div class="py-3 text-center">
                        <span class="cx-cathex mx-auto mb-2" style="width:26px;height:29px;background: {{ $m['color'] }}">
                            <x-icon :name="$m['icon']" class="h-3.5 w-3.5" />
                        </span>
                        <p class="text-[11.5px] font-bold text-ink">Nothing logged yet</p>
                        @if ($m['purpose'])
                            <p class="mt-0.5 text-[10.5px] text-muted">{{ $m['purpose'] }}</p>
                        @endif
                        @unless ($m['supportsAdd'])
                            <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" wire:navigate class="mt-2 inline-block text-[10.5px] font-bold" style="color: var(--cx-accent-ink)">
                                Open {{ $m['label'] }} →
                            </a>
                        @endunless
                    </div>
                @else
                    @if ($m['recent']->isNotEmpty())
                        <div>
                            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted !text-[9px] opacity-70">Recent Activity</p>
                            @foreach ($m['recent'] as $entry)
                                <div class="flex items-start gap-2 py-1">
                                    <x-user-avatar :user="$entry->user" size="h-5 w-5" text="text-[8px]" />
                                    <div class="min-w-0">
                                        <p class="truncate text-[11px] font-semibold text-ink">{{ $entry->describe() }}</p>
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
                                   class="flex flex-col items-center gap-1 rounded-lg px-1 py-1.5 text-center transition hover:bg-[var(--cx-surface-2)]">
                                    <span class="cx-cathex" style="width:20px;height:22px;background:var(--cx-surface-3);color:var(--cx-muted)"><x-icon :name="$link['icon']" class="h-2.5 w-2.5" /></span>
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
        <a href="{{ route('events.hub', [$event, 'tab' => $m['tab']]) }}" wire:navigate class="cx-btn cx-btn-accent m-3 justify-center" style="display:flex">
            Open {{ $m['label'] }} →
        </a>
    @endunless
</div>
</div>
