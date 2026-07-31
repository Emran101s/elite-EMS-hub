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

         Underlines instead. Nothing is drawn but the words, so the row is quiet
         until you look at it, and the active module is the only mark on it. It
         is sticky at every size now that it costs 62px rather than 136, so the
         way to the next module is always one glance away. ══ --}}
    @php
        $modules = \App\Models\Event::HUB_TABS;
        // Where the work is. Only modules with something genuinely waiting
        // appear here — see EventCommandHeader::attention().
        $attention = $header['attention'] ?? [];
    @endphp
    <div class="sticky top-0 z-20 -mx-4 mt-3 border-b border-line bg-page/92 px-4 backdrop-blur lg:-mx-6 lg:px-6">
        {{-- Twenty-one names need ~1550px and the work area is 1186, so on a
             desktop they wrap to two rows: everything on screen, nothing to
             discover by dragging. Narrow screens keep the scroller, where
             wrapping would make five rows. --}}
        <nav class="scrollbar-none flex items-end gap-x-1 overflow-x-auto lg:flex-wrap lg:overflow-x-visible"
             aria-label="Event modules">
            @foreach ($modules as $key => [$label, $note, $icon])
                @continue (! $event->moduleEnabled($key))
                <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                   @if ($tab === $key) aria-current="page" @endif
                   title="{{ $note }}"
                   @class([
                       'group relative flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 py-[7px] text-[12.5px] transition',
                       'border-gold-500 font-bold text-navy-950' => $tab === $key,
                       'border-transparent font-semibold text-navy-400 hover:text-navy-900' => $tab !== $key,
                   ])>
                    {{-- No icon. Twenty-one glyphs beside twenty-one words is
                         noise twice over — the word already names the module —
                         and the icons alone cost 441px, which is the third row
                         this used to wrap to. --}}
                    {{ $label }}

                    {{-- What is waiting behind this module. Twenty-one names of
                         equal weight mean the only way to learn whether Risks
                         has anything in it is to open Risks. --}}
                    @if ($n = $attention[$key] ?? null)
                        <span title="{{ $n['why'] }}" @class([
                            'grid h-[16px] min-w-[16px] shrink-0 place-items-center rounded-full px-1 text-[9.5px] font-black tabular-nums',
                            'bg-risk/12 text-red-700' => $n['tone'] === 'alarm',
                            'bg-gold-100 text-gold-800' => $n['tone'] !== 'alarm',
                        ])>{{ $n['count'] > 99 ? '99+' : $n['count'] }}</span>
                    @endif
                </a>
            @endforeach
        </nav>
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
