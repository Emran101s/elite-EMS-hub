@props(['event', 'header', 'activeTab'])

@php
    $attention = collect($header['attention'] ?? []);
    $primary = ['overview', 'agenda', 'budget', 'transportation', 'approvals', 'venue', 'suppliers', 'files'];
    $modules = \App\Models\Event::HUB_TABS;

    $build = function (string $key) use ($event, $attention, $modules, $activeTab) {
        [$label,, $icon] = $modules[$key] ?? [ucfirst($key), '', 'archive'];
        $signal = $attention->get($key);

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'issues' => $signal['count'] ?? 0,
            'tone' => $signal['tone'] ?? 'alarm',
            'active' => $key === $activeTab,
            'enabled' => $event->moduleEnabled($key),
            'href' => route('events.hub', [$event, 'tab' => $key]),
        ];
    };

    $items = collect($primary)->map($build)->filter(fn ($m) => $m['enabled'])->values();
    $moreKeys = collect(array_keys($modules))->diff($primary)->diff(['ai', 'settings', 'reports']);
    $moreItems = $moreKeys->map($build)->filter(fn ($m) => $m['enabled'])->values();
    $moreActive = $moreItems->contains('active', true);
@endphp

<nav class="ehx-modnav" x-data="{ more: false, overflowing: false }"
     x-init="
        $nextTick(() => {
            const scroller = $refs.scroller;
            const check = () => { overflowing = scroller.scrollWidth > scroller.clientWidth + 2; };
            check();
            scroller.querySelector('.is-active')?.scrollIntoView({ inline: 'center', block: 'nearest' });
            new ResizeObserver(check).observe(scroller);
        })">
    <div class="ehx-modnav-scroll" x-ref="scroller" :class="{ 'is-overflowing': overflowing }">
        @foreach ($items as $m)
            <a href="{{ $m['href'] }}" wire:navigate class="ehx-modnav-item {{ $m['active'] ? 'is-active' : '' }}">
                <x-icon :name="$m['icon']" class="h-4 w-4" />
                <span>{{ $m['label'] }}</span>
                @if ($m['issues'] > 0)
                    <span class="ehx-modnav-badge {{ $m['tone'] === 'wait' ? 'is-wait' : '' }}">{{ $m['issues'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    @if ($moreItems->isNotEmpty())
        <div class="relative shrink-0" @click.outside="more = false">
            <button type="button" @click="more = !more" :aria-expanded="more.toString()"
                    class="ehx-modnav-item {{ $moreActive ? 'is-active' : '' }}">
                <x-icon name="dots" class="h-4 w-4" />
                <span>More</span>
            </button>

            <div x-show="more" x-cloak class="ehx-modnav-flyout">
                @foreach ($moreItems as $m)
                    <a href="{{ $m['href'] }}" wire:navigate class="ehx-modnav-flyout-item {{ $m['active'] ? 'is-active' : '' }}">
                        <x-icon :name="$m['icon']" class="h-3.5 w-3.5" />
                        <span class="flex-1">{{ $m['label'] }}</span>
                        @if ($m['issues'] > 0)
                            <span class="ehx-modnav-badge {{ $m['tone'] === 'wait' ? 'is-wait' : '' }}">{{ $m['issues'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</nav>
