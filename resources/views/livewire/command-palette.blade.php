<div x-data="{
        open: false,
        active: 0,
        items() { return [...this.$refs.list.querySelectorAll('[data-palette-item]')] },
        show() { this.open = true; this.active = 0; this.$nextTick(() => { this.$refs.q.focus(); this.paint() }) },
        move(step) {
            const n = this.items().length;
            if (! n) return;
            this.active = (this.active + step + n) % n;
            this.paint();
            this.items()[this.active].scrollIntoView({ block: 'nearest' });
        },
        paint() {
            this.items().forEach((el, i) => el.setAttribute('aria-selected', i === this.active ? 'true' : 'false'));
        },
        go() { const el = this.items()[this.active]; if (el) { this.open = false; el.click() } },
     }"
     @keydown.window.prevent.cmd.k="show()"
     @keydown.window.prevent.ctrl.k="show()"
     @open-palette.window="show()"
     @keydown.escape.window="open = false"
     x-init="window.Livewire?.hook('morph.updated', () => { if (open) { active = Math.min(active, Math.max(items().length - 1, 0)); paint() } })">

    @if (! request()->routeIs('login'))
        {{-- The header trigger — looks like the old search box, but works. --}}
        <button type="button" @click="show()"
                class="relative hidden h-10 w-44 cursor-text items-center rounded-full border border-line bg-page pl-10 pr-3 text-left text-[12.5px] text-muted transition hover:border-navy-300 lg:flex xl:w-56">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><x-icon name="search" class="h-4 w-4" /></span>
            Search or jump to…
            <span class="ml-auto rounded-md border border-line bg-white px-1.5 py-0.5 font-sans text-eyebrow font-bold text-muted">⌘K</span>
        </button>
    @endif

    {{-- Palette --}}
    <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[90]">
        <div x-transition.opacity @click="open = false" class="absolute inset-0 bg-navy-950/50 backdrop-blur-sm"></div>

        <div x-show="open" @click.outside="open = false" role="dialog" aria-modal="true" aria-label="Command palette"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-2 scale-98" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="absolute left-1/2 top-[14vh] w-full max-w-xl -translate-x-1/2 overflow-hidden rounded-[22px] border border-line bg-white shadow-[0_20px_50px_-20px_rgba(11,31,58,0.5)]">

            <div class="relative border-b border-line bg-white">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-muted"><x-icon name="search" class="h-4 w-4" /></span>
                <input x-ref="q" type="text" wire:model.live.debounce.200ms="q"
                       @input="active = 0"
                       @keydown.arrow-down.prevent="move(1)"
                       @keydown.arrow-up.prevent="move(-1)"
                       @keydown.enter.prevent="go()"
                       role="combobox" aria-expanded="true" aria-controls="palette-list" aria-autocomplete="list"
                       placeholder="Search, or try “icft budget”, “new task on gala”, “overdue invoices”…"
                       class="h-13 w-full border-0 bg-transparent py-4 pl-11 pr-4 text-sm text-navy-900 outline-none placeholder:text-muted">
            </div>

            <div x-ref="list" id="palette-list" role="listbox" class="max-h-[52vh] overflow-y-auto p-2">
                @forelse ($groups as $group => $rows)
                    <p class="px-3 pb-1 pt-2.5 text-eyebrow font-bold uppercase tracking-[0.18em] text-muted" role="presentation">{{ $group }}</p>
                    @foreach ($rows as $row)
                        <a href="{{ $row['href'] }}" @click="open = false" data-palette-item role="option" aria-selected="false"
                           @mouseenter="active = items().indexOf($el); paint()"
                           class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 transition hover:bg-page aria-selected:bg-page">
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-semibold text-navy-900">{{ $row['label'] }}</span>
                                @if ($row['sub'])<span class="block truncate text-eyebrow text-muted">{{ $row['sub'] }}</span>@endif
                            </span>
                            <span class="shrink-0 text-eyebrow font-bold text-muted">↵</span>
                        </a>
                    @endforeach
                @empty
                    @if (mb_strlen(trim($q)) >= 2)
                        <p class="px-3 py-8 text-center text-xs text-muted">Nothing matches “{{ $q }}”.</p>
                    @endif
                @endforelse
                @if (mb_strlen(trim($q)) === 1)
                    <p class="px-3 py-3 text-center text-eyebrow text-muted">Type at least two characters to search records.</p>
                @endif
            </div>

            <div class="flex items-center gap-4 border-t border-line bg-page px-4 py-2 text-eyebrow text-muted" aria-hidden="true">
                <span><b class="font-bold text-ink">↑↓</b> move</span>
                <span><b class="font-bold text-ink">↵</b> open</span>
                <span><b class="font-bold text-ink">esc</b> close</span>
            </div>
        </div>
    </div>
</div>
