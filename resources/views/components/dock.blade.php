@props([
    'id',                    // unique key in the dock store
    'label',                 // vertical text on the spine
    'color' => 'var(--color-ink)',   // spine colour — usually the module's
    'count' => null,         // optional badge
    'width' => '380px',
    'order' => 0,            // 0 sits above 1 on the right edge
    'title' => null,         // panel heading; defaults to the label
    'subtitle' => null,
    'icon' => 'archive',
    'bare' => false,         // true when the body brings its own header
])

{{--
    A collapsible right-edge panel. Chrome only — the caller supplies the body,
    so a tab can wrap its own controls (keeping wire: bindings intact) rather
    than routing actions through a separate component.

    Spines stack from the vertical centre; the open panel is always centred, so
    a tall panel never hangs off the bottom regardless of which spine opened it.
--}}
<div x-data class="contents">

    {{-- spine --}}
    <button type="button"
            dusk="dock-spine-{{ $id }}"
            @click="$store.dock.toggle('{{ $id }}')"
            :aria-expanded="$store.dock.is('{{ $id }}')"
            :title="$store.dock.is('{{ $id }}') ? 'Hide {{ $label }}' : 'Show {{ $label }}'"
            class="pointer-events-auto fixed right-0 z-30 flex h-24 items-center gap-1.5 rounded-l-xl py-4 pl-2 pr-1.5 text-white shadow-float transition-all hover:pl-2.5"
            style="background: {{ $color }}; top: calc(50% + {{ $order * 104 }}px - 52px)">
        <span class="text-eyebrow font-black uppercase"
              style="writing-mode: vertical-rl; text-orientation: mixed; letter-spacing: 0.14em">{{ $label }}</span>
        @if ($count)
            <span class="rounded-full bg-white/25 px-1 py-0.5 text-eyebrow font-black leading-none">{{ $count }}</span>
        @endif
    </button>

    {{-- panel --}}
    <div x-show="$store.dock.is('{{ $id }}')"
         dusk="dock-panel-{{ $id }}"
         @click.outside="$store.dock.close()"
         x-cloak
         @keydown.escape.window="$store.dock.close()"
         x-transition:enter="transition ease-[cubic-bezier(0.22,1,0.36,1)] duration-300"
         x-transition:enter-start="opacity-0 translate-x-16 scale-[0.98]"
         x-transition:enter-end="opacity-100 translate-x-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-x-0 scale-100"
         x-transition:leave-end="opacity-0 translate-x-16 scale-[0.98]"
         class="pointer-events-auto fixed right-9 top-1/2 z-30 flex max-h-[88vh] max-w-[92vw] -translate-y-1/2 flex-col overflow-hidden rounded-l-2xl border border-r-0 border-line bg-white shadow-overlay"
         style="width: {{ $width }}">

        @unless ($bare)
            {{-- Light tinted header, matching the Documents drawer: the module's
                 colour as a wash rather than a heavy navy bar. --}}
            <div class="flex items-center gap-3 border-b px-5 py-3.5"
                 style="background: linear-gradient(135deg,
                            color-mix(in srgb, {{ $color }} 17%, transparent),
                            color-mix(in srgb, {{ $color }} 7%, transparent) 65%,
                            color-mix(in srgb, {{ $color }} 4%, transparent));
                        border-color: color-mix(in srgb, {{ $color }} 20%, transparent)">
                <span class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg shadow-sm" style="background: {{ $color }}">
                    <x-icon :name="$icon" class="h-4 w-4 text-white" />
                </span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-xs font-bold text-navy-900">{{ $title ?? $label }}</p>
                    @if ($subtitle)
                        <p class="truncate text-eyebrow text-muted">{{ $subtitle }}</p>
                    @endif
                </div>
            </div>
        @endunless

        <div class="flex-1 overflow-y-auto">
            {{ $slot }}
        </div>

        <button type="button" @click="$store.dock.close()"
                class="shrink-0 border-t border-line py-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-navy-400 transition hover:bg-page/60 hover:text-navy-900">
            Close ›
        </button>
    </div>
</div>
