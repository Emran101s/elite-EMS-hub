<div x-data="{ open: false }"
     @keydown.window.prevent.cmd.k="open = true; $nextTick(() => $refs.q.focus())"
     @keydown.window.prevent.ctrl.k="open = true; $nextTick(() => $refs.q.focus())"
     @open-palette.window="open = true; $nextTick(() => $refs.q.focus())"
     @keydown.escape.window="open = false">

    @if (! request()->routeIs('login'))
        {{-- The header trigger — looks like the old search box, but works. --}}
        <button type="button" @click="open = true; $nextTick(() => $refs.q.focus())"
                class="input relative hidden h-10 w-44 cursor-text items-center !rounded-full pl-10 pr-3 text-left text-[12.5px] text-muted lg:flex xl:w-56">
            <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-navy-400"><x-icon name="search" class="h-4 w-4" /></span>
            Search events, tasks, suppliers…
            <span class="ml-auto rounded-md border border-line bg-page px-1.5 py-0.5 font-sans text-eyebrow font-bold text-navy-400">⌘K</span>
        </button>
    @endif

    {{-- Palette --}}
    <div x-show="open" x-cloak style="display:none" class="fixed inset-0 z-[90]">
        <div x-transition.opacity @click="open = false" class="absolute inset-0 bg-navy-950/45 backdrop-blur-sm"></div>

        <div x-show="open" @click.outside="open = false"
             x-transition:enter="transition ease-out duration-150" x-transition:enter-start="opacity-0 -translate-y-2 scale-98" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             class="absolute left-1/2 top-[14vh] w-full max-w-xl -translate-x-1/2 overflow-hidden rounded-2xl border border-line bg-page shadow-2xl">

            <div class="relative border-b border-line bg-white">
                <span class="pointer-events-none absolute left-3.5 top-1/2 -translate-y-1/2 text-navy-400"><x-icon name="search" class="h-4 w-4" /></span>
                <input x-ref="q" type="text" wire:model.live.debounce.250ms="q"
                       placeholder="Search events, tasks, speakers, suppliers, venues, people…"
                       class="h-13 w-full border-0 bg-transparent py-4 pl-11 pr-4 text-sm text-navy-900 outline-none placeholder:text-muted">
            </div>

            <div class="max-h-[52vh] overflow-y-auto p-2">
                @forelse ($groups as $group => $rows)
                    <p class="px-3 pb-1 pt-2.5 text-eyebrow font-bold uppercase tracking-[0.18em] text-muted">{{ $group }}</p>
                    @foreach ($rows as $row)
                        <a href="{{ $row['href'] }}" @click="open = false"
                           class="flex items-center justify-between gap-3 rounded-xl px-3 py-2 transition hover:bg-white">
                            <span class="min-w-0">
                                <span class="block truncate text-xs font-semibold text-navy-900">{{ $row['label'] }}</span>
                                @if ($row['sub'])<span class="block truncate text-eyebrow text-muted">{{ $row['sub'] }}</span>@endif
                            </span>
                            <span class="shrink-0 text-eyebrow font-bold text-navy-300">↵</span>
                        </a>
                    @endforeach
                @empty
                    <p class="px-3 py-8 text-center text-xs text-muted">
                        {{ mb_strlen(trim($q)) < 2 ? 'Type at least two characters…' : 'Nothing matches “'.$q.'”.' }}
                    </p>
                @endforelse
            </div>
        </div>
    </div>
</div>
