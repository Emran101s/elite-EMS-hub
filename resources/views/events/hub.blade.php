@php $theme = $event->theme(); @endphp

<x-layouts.app :title="$event->name . ' — Event Hub'"
               :hide-title-row="true"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Events', 'href' => route('events.index')],
                   ['label' => $event->name, 'href' => route('events.hub', $event)],
                   ['label' => \App\Models\Event::moduleLabel($tab)],
               ]">

    {{-- ══ The header ══
         Identity, scale, the one thing that needs a person, module-by-module
         progress, where to go, and what is true right now. See
         resources/views/components/event-header.blade.php for why it is these
         four blocks and not the one white bar it replaces. ══ --}}
    <x-event-header :event="$event" :header="$header" />

    {{-- ══ Module nav ══
         Twenty-one modules used to be twenty-one tiles: icon, name, a sub-label
         on the active one, 44px a row, three rows, 136px. A row of buttons that
         tall is furniture — it competes with the work for the eye and costs a
         seventh of a laptop screen on every page.

         Words instead of tiles, still — that lesson holds, and the roster has
         grown to twenty-three since (pricing, reports, AI joined later; the
         width math below is the same shape, just tighter). Twenty-three words
         of equal grey weight told you *where* you were, never *what kind of
         work* you were in. Every module already has a colour in
         Event::MODULE_COLORS (used for document folders and chips elsewhere) —
         Plan is blue, Programme teal, Logistics amber, Exhibition violet, Sell
         green, Risks red. The nav now wears that: a small dot per module, and
         the active one becomes a solid pill in its own colour. Quiet until you
         look at it — one row is grey, one is not — and the colour underneath
         you tells you which family of work you're standing in before you've
         read a single word. Costs the same handful of rows as before. ══ --}}
    @php
        $modules = \App\Models\Event::HUB_TABS;
        // Where the work is. Only modules with something genuinely waiting
        // appear here — see EventCommandHeader::attention().
        $attention = $header['attention'] ?? [];
    @endphp
    <div class="sticky top-0 z-20 -mx-4 mt-3 border-b border-line bg-page/92 px-4 backdrop-blur lg:-mx-6 lg:px-6">
        {{-- Twenty-three names (now with a dot each) still land in two rows on
             a laptop-width desktop: everything on screen, nothing to discover
             by dragging. Narrow screens keep the scroller, where wrapping
             would run to five rows. --}}
        <nav class="scrollbar-none flex items-center gap-x-1 gap-y-1 overflow-x-auto py-1.5 lg:flex-wrap lg:overflow-x-visible"
             aria-label="Event modules">
                @foreach ($modules as $key => [$label, $note, $icon])
                    @continue (! $event->moduleEnabled($key))
                    @php
                        $active = $tab === $key;
                        $hex = \App\Models\Event::moduleColor($key);
                    @endphp
                    <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                       @if ($active) aria-current="page" @endif
                       title="{{ $note }}"
                       @class([
                           'group relative flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-full px-2.5 py-[6px] text-[12.5px] font-bold transition',
                           'text-white shadow-[0_6px_14px_-6px_rgba(11,31,58,0.55)]' => $active,
                           'font-semibold text-navy-400 hover:bg-navy-50 hover:text-navy-900' => ! $active,
                       ])
                       style="{{ $active ? 'background:' . $hex . ';' : '' }}">
                        {{-- No icon glyph — the word already names the module, and
                             twenty-one icons cost 441px, a third row on its own.
                             The dot carries the colour instead, for a tenth the width. --}}
                        <span class="h-[5px] w-[5px] shrink-0 rounded-full transition"
                              style="background: {{ $active ? 'rgba(255,255,255,.85)' : $hex }}; opacity: {{ $active ? 1 : 0.5 }}"
                              aria-hidden="true"></span>
                        {{ $label }}

                        {{-- What is waiting behind this module. Twenty-one names of
                             equal weight mean the only way to learn whether Risks
                             has anything in it is to open Risks. --}}
                        @if ($n = $attention[$key] ?? null)
                            <span title="{{ $n['why'] }}" @class([
                                'grid h-[16px] min-w-[16px] shrink-0 place-items-center rounded-full px-1 text-[9.5px] font-black tabular-nums',
                                'bg-white/25 text-white' => $active,
                                'bg-risk/12 text-red-700' => ! $active && $n['tone'] === 'alarm',
                                'bg-gold-100 text-gold-800' => ! $active && $n['tone'] !== 'alarm',
                            ])>{{ $n['count'] > 99 ? '99+' : $n['count'] }}</span>
                        @endif
                    </a>
                @endforeach
        </nav>
        {{-- Edge fades hint the scroller holds more than what's on screen —
             only matters below lg, where the nav scrolls instead of wraps. --}}
        <span class="pointer-events-none absolute inset-y-0 left-0 w-6 bg-gradient-to-r from-page to-transparent lg:hidden" aria-hidden="true"></span>
        <span class="pointer-events-none absolute inset-y-0 right-0 w-6 bg-gradient-to-l from-page to-transparent lg:hidden" aria-hidden="true"></span>
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
