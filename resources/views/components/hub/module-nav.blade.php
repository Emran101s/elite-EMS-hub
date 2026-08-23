@props(['event', 'header', 'activeTab'])

@php
    $attention = collect($header['attention'] ?? []);
    $modules = \App\Models\Event::HUB_TABS;

    // Every module enabled for this event, in the Hub's own tab order.
    // ai/settings/reports stay out — reached elsewhere in the chrome, the
    // same exclusion this strip has always made.
    $build = function (string $key) use ($event, $attention, $modules, $activeTab) {
        [$label,, $icon] = $modules[$key];
        $signal = $attention->get($key);
        $dotTone = match ($signal['tone'] ?? null) {
            'wait' => 'warn',
            null => 'ok',
            default => 'alarm',
        };

        return [
            'key' => $key,
            'label' => $label,
            'icon' => $icon,
            'issues' => $signal['count'] ?? 0,
            'dotTone' => $dotTone,
            'active' => $key === $activeTab,
            'href' => route('events.hub', [$event, 'tab' => $key]),
        ];
    };

    $items = collect($modules)->keys()
        ->reject(fn ($key) => in_array($key, ['ai', 'settings', 'reports'], true))
        ->filter(fn ($key) => $event->moduleEnabled($key))
        ->map($build)
        ->values();

    $activeIndex = $items->search(fn ($m) => $m['active']);
    $activeIndex = $activeIndex === false ? -1 : $activeIndex;
@endphp

{{-- ══ Module dock ══
     A dark floating dock, one slot per module this event actually has
     switched on (Settings → Modules), in the Hub's own tab order. Every
     event enables a different set, so there's no fixed "primary eight" to
     hardcode — whatever doesn't fit the available width folds into "More
     Modules" instead, measured client-side against the dock's real width
     rather than left to a horizontal scroll. ══ --}}
<nav class="ehx-dock" x-data="{
        activeIndex: {{ $activeIndex }},
        visibleCount: {{ $items->count() }},
        moreOpen: false,
        ready: false,
        get moreActive() { return this.activeIndex >= this.visibleCount },
        widths: null,
        measure() {
            const row = this.$refs.row;
            const more = this.$refs.more;
            if (! row || ! more) return;
            // Each item's natural width is cached the first time every item
            // is guaranteed visible (visibleCount starts at total). A later
            // re-measure — from the font swap or a real resize — must reuse
            // that cache rather than re-reading offsetWidth live: by then
            // x-show has already hidden whatever didn't fit last time, and
            // a hidden item reads back as 0, corrupting the recount.
            if (! this.widths) {
                this.widths = [...row.querySelectorAll('[data-dock-item]')].map(el => el.offsetWidth);
            }
            // The row is the flexible sibling next to the fixed-width More
            // button (see markup below) — its clientWidth already excludes
            // More's own space via flex distribution, so there's nothing
            // left to reserve for it here.
            const rowW = row.clientWidth;
            const gap = 6;
            let sum = 0, count = 0;
            for (const w of this.widths) {
                const next = sum + (count > 0 ? gap : 0) + w;
                if (next > rowW) break;
                sum = next; count++;
            }
            this.visibleCount = Math.max(1, count);
            this.ready = true;
        },
        init() {
            // Two animation frames reliably lands after paint (a single
            // $nextTick can fire mid-transition, before layout settles).
            // That alone still isn't enough the first time a font hasn't
            // finished loading yet: labels measure narrower on the
            // fallback font, so more items appear to fit than actually
            // will once Plus Jakarta Sans swaps in — and nothing else
            // would trigger a re-measure at that point, since the row's
            // own width never changes when only its children's content
            // does. document.fonts.ready covers that swap explicitly — it
            // re-triggers measure(), but never clears the cached widths
            // itself, since by the time it fires some items may already be
            // hidden from an earlier pass (see the cache note above).
            const remeasure = () => requestAnimationFrame(() => requestAnimationFrame(() => this.measure()));
            remeasure();
            if (document.fonts?.ready) document.fonts.ready.then(remeasure);
            new ResizeObserver(remeasure).observe(this.$refs.row);

            // wire:navigate swaps the tab's content without necessarily
            // tearing this component down (same event, same enabled
            // modules, so the cached widths and cutoff both stay valid) —
            // but the swap itself can briefly leave the previous cutoff
            // showing against markup it no longer matches. Hide across the
            // whole transition and re-reveal only once settled, rather
            // than risk a frame of the wrong count.
            document.addEventListener('livewire:navigating', () => { this.ready = false; });
            document.addEventListener('livewire:navigated', remeasure);
        },
    }">
    <div class="ehx-dock-row" x-ref="row" :style="{ visibility: ready ? 'visible' : 'hidden' }">
        @foreach ($items as $i => $m)
            <a href="{{ $m['href'] }}" wire:navigate data-dock-item
               class="ehx-dock-item {{ $m['active'] ? 'is-active' : '' }}"
               x-show="{{ $i }} < visibleCount" title="{{ $m['label'] }}">
                <span class="ehx-dock-item-icon"><x-icon :name="$m['icon']" class="h-[19px] w-[19px]" /></span>
                <span class="ehx-dock-item-label">{{ $m['label'] }}</span>
                <span class="ehx-dock-dot is-{{ $m['dotTone'] }}"></span>
            </a>
        @endforeach
    </div>

    {{-- Outside the row deliberately — the row clips to fit the items that
         belong in it, and that overflow:hidden would just as happily clip
         this button's own flyout if it were a descendant of the row. --}}
    <div class="relative shrink-0" @click.outside="moreOpen = false">
        <button type="button" x-ref="more" @click="moreOpen = !moreOpen" :aria-expanded="moreOpen.toString()"
                class="ehx-dock-item ehx-dock-more" :class="{ 'is-active': moreActive }">
            <span class="ehx-dock-item-icon"><x-icon name="grid" class="h-[19px] w-[19px]" /></span>
            <span class="ehx-dock-item-label">More Modules</span>
        </button>

        <div x-show="moreOpen" x-cloak class="ehx-modnav-flyout">
            @foreach ($items as $i => $m)
                <a href="{{ $m['href'] }}" wire:navigate
                   class="ehx-modnav-flyout-item {{ $m['active'] ? 'is-active' : '' }}"
                   x-show="{{ $i }} >= visibleCount">
                    <x-icon :name="$m['icon']" class="h-3.5 w-3.5" />
                    <span class="flex-1">{{ $m['label'] }}</span>
                    @if ($m['issues'] > 0)
                        <span class="ehx-modnav-badge {{ $m['dotTone'] === 'warn' ? 'is-wait' : '' }}">{{ $m['issues'] }}</span>
                    @endif
                </a>
            @endforeach
        </div>
    </div>
</nav>
