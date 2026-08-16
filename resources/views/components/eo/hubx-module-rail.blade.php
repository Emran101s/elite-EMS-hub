@props(['event', 'header', 'activeTab'])

{{--
    Vertical Module Rail — replaces the old flat tab feeling with readiness
    rings read straight off EventCommandHeader::meters() (the same numbers
    the header pills already show) and issue badges off attention(). A
    module meters() doesn't cover (approvals, venues, suppliers proper,
    files) simply shows no ring rather than an invented percentage.
--}}

@php
    $meters = collect($header['meters'] ?? [])->keyBy('key');
    $attention = collect($header['attention'] ?? []);
    $meterAlias = ['transportation' => 'logistics', 'venue' => 'logistics', 'suppliers' => 'logistics'];

    $primary = ['overview', 'agenda', 'budget', 'transportation', 'approvals', 'venue', 'suppliers', 'files'];
    $modules = \App\Models\Event::HUB_TABS;

    $build = function (string $key) use ($event, $meters, $attention, $meterAlias, $modules, $activeTab) {
        [$label,, $icon] = $modules[$key] ?? [ucfirst($key), '', 'archive'];
        $meter = $meters->get($meterAlias[$key] ?? $key);
        $signal = $attention->get($key);

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'pct' => $key === 'overview' ? null : $meter['pct'] ?? null,
            'issues' => $signal['count'] ?? 0,
            'color' => \App\Models\Event::moduleColor($key === 'overview' ? null : $key),
            'active' => $key === $activeTab,
            'enabled' => $event->moduleEnabled($key),
            'href' => route('events.hub', [$event, 'tab' => $key]),
        ];
    };

    $items = collect($primary)->map($build);
    $moreKeys = collect(array_keys($modules))->diff($primary)->diff(['ai', 'settings', 'reports']);
    $moreItems = $moreKeys->map($build)->filter(fn ($m) => $m['enabled'])->values();
@endphp

<div class="hubx-rail" x-data="{ more: false }">
    @foreach ($items as $m)
        @if ($m['enabled'])
            <a href="{{ $m['href'] }}" wire:navigate
               class="hubx-rail-item {{ $m['active'] ? 'is-active' : '' }}"
               title="{{ $m['label'] }}">
                <span class="hubx-rail-ring" style="--ri-pct: {{ $m['pct'] ?? 0 }}; --ri-color: {{ $m['color'] }}">
                    <span class="hubx-rail-ring-inner" style="--ri-color: {{ $m['color'] }}">
                        <x-icon :name="$m['icon']" class="h-4 w-4" />
                    </span>
                    @if ($m['issues'] > 0)
                        <span class="hubx-rail-badge">{{ $m['issues'] }}</span>
                    @endif
                </span>
                <span class="hubx-rail-label">{{ $m['label'] }}</span>
                @if ($m['pct'] !== null)
                    <span class="hubx-rail-pct" style="--ri-color: {{ $m['color'] }}">{{ $m['pct'] }}%</span>
                @endif
            </a>
        @endif
    @endforeach

    @if ($moreItems->isNotEmpty())
        <div class="hubx-rail-more">
            <button type="button" @click="more = !more" class="hubx-rail-item" title="More modules">
                <span class="hubx-rail-ring-inner" style="width: 42px; height: 42px; color: var(--color-eo-muted);">
                    <x-icon name="dots" class="h-4 w-4" />
                </span>
                <span class="hubx-rail-label">More</span>
            </button>

            <template x-if="more">
                <div class="flex flex-col items-center gap-2">
                    @foreach ($moreItems as $m)
                        <a href="{{ $m['href'] }}" wire:navigate
                           class="hubx-rail-item {{ $m['active'] ? 'is-active' : '' }}"
                           title="{{ $m['label'] }}">
                            <span class="hubx-rail-ring" style="--ri-pct: {{ $m['pct'] ?? 0 }}; --ri-color: {{ $m['color'] }}">
                                <span class="hubx-rail-ring-inner" style="--ri-color: {{ $m['color'] }}">
                                    <x-icon :name="$m['icon']" class="h-4 w-4" />
                                </span>
                                @if ($m['issues'] > 0)
                                    <span class="hubx-rail-badge">{{ $m['issues'] }}</span>
                                @endif
                            </span>
                            <span class="hubx-rail-label">{{ $m['label'] }}</span>
                        </a>
                    @endforeach
                </div>
            </template>
        </div>
    @endif
</div>
