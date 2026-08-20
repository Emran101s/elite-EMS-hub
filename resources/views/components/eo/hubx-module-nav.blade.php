@props(['event', 'header', 'activeTab'])

{{--
    Module Navigation Bar — replaces the old vertical Compact Module Rail
    with a horizontal pill-tab strip (Event Command Header architecture).
    Same underlying data as the rail it replaces: meters()/attention() off
    EventCommandHeader, nothing invented. Percentages moved off the tabs
    themselves — that detail lives in the Event Pulse Strip and the
    Inspector now, so a tab here is just a door: icon, label, and an issue
    badge when one is real.
--}}

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

<nav class="hubx-modnav" x-data="{ more: false, overflowing: false }"
     x-init="
        $nextTick(() => {
            const scroller = $refs.scroller;
            const check = () => { overflowing = scroller.scrollWidth > scroller.clientWidth + 2; };
            check();
            // The active tab isn't always the first one — on a narrow screen
            // it can load scrolled out of view, which reads as 'nothing is
            // selected' rather than 'swipe to see it'. Land on it directly.
            scroller.querySelector('.is-active')?.scrollIntoView({ inline: 'center', block: 'nearest' });
            new ResizeObserver(check).observe(scroller);
        })">
    {{-- overflowing adds a soft right-edge fade — the only hint on a phone
         that there's more to swipe to, since there's no scrollbar there. --}}
    <div class="hubx-modnav-scroll" x-ref="scroller" :class="{ 'is-overflowing': overflowing }">
        @foreach ($items as $m)
            <a href="{{ $m['href'] }}" wire:navigate class="hubx-modnav-item {{ $m['active'] ? 'is-active' : '' }}">
                <x-icon :name="$m['icon']" class="h-4 w-4" />
                <span>{{ $m['label'] }}</span>
                @if ($m['issues'] > 0)
                    <span class="hubx-modnav-badge {{ $m['tone'] === 'wait' ? 'is-wait' : '' }}">{{ $m['issues'] }}</span>
                @endif
            </a>
        @endforeach
    </div>

    {{-- Kept OUTSIDE .hubx-modnav-scroll on purpose — that container is
         overflow-x:auto (the browser implicitly sets overflow-y:auto too
         once one axis is non-visible), which clips an absolutely
         positioned dropdown that extends below it. Living as its own
         flex sibling means the flyout is never clipped, and "More" stays
         put rather than scrolling away with the primary tabs. --}}
    @if ($moreItems->isNotEmpty())
        <div class="relative shrink-0" @click.outside="more = false">
            <button type="button" @click="more = !more" :aria-expanded="more.toString()"
                    class="hubx-modnav-item {{ $moreActive ? 'is-active' : '' }}">
                <x-icon name="dots" class="h-4 w-4" />
                <span>More</span>
            </button>

            <div x-show="more" x-cloak class="hubx-modnav-flyout">
                @foreach ($moreItems as $m)
                    <a href="{{ $m['href'] }}" wire:navigate class="hubx-modnav-flyout-item {{ $m['active'] ? 'is-active' : '' }}">
                        <x-icon :name="$m['icon']" class="h-3.5 w-3.5" />
                        <span class="flex-1">{{ $m['label'] }}</span>
                        @if ($m['issues'] > 0)
                            <span class="hubx-modnav-badge {{ $m['tone'] === 'wait' ? 'is-wait' : '' }}">{{ $m['issues'] }}</span>
                        @endif
                    </a>
                @endforeach
            </div>
        </div>
    @endif
</nav>
