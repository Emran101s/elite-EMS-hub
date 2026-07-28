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

    {{-- ══ Module tabs ══
         The active module gets the navy tile and says what it is for; the rest
         are icon and name. Sub-labels on all twenty-one would be a wall of
         text you scroll past — on the one you are in, it is the caption. ══ --}}
    @php
        $modules = \App\Models\Event::HUB_TABS;
    @endphp
    <div class="sticky top-0 z-20 -mx-1 mt-3 bg-page/90 px-1 py-1.5 backdrop-blur">
        <nav class="scrollbar-none flex items-stretch gap-1 overflow-x-auto" aria-label="Event modules">
            @foreach ($modules as $key => [$label, $note, $icon])
                @continue (! $event->moduleEnabled($key))
                <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                   @if ($tab === $key) aria-current="page" @endif
                   @class([
                       'group flex shrink-0 items-center gap-2 whitespace-nowrap rounded-xl px-3 py-2 transition',
                       'bg-navy-950 text-white shadow-[0_10px_24px_-16px_rgba(11,31,58,0.9)]' => $tab === $key,
                       'text-navy-500 hover:bg-white hover:text-navy-900' => $tab !== $key,
                   ])>
                    <x-icon :name="$icon" @class([
                        'h-4 w-4 shrink-0',
                        'text-gold-400' => $tab === $key,
                        'text-navy-300 group-hover:text-navy-500' => $tab !== $key,
                    ]) />
                    <span class="leading-tight">
                        <span class="block text-[12.5px] font-bold">{{ $label }}</span>
                        @if ($tab === $key)
                            <span class="block text-[10px] font-semibold text-white/55">{{ $note }}</span>
                        @endif
                    </span>
                </a>
            @endforeach
        </nav>
    </div>

    {{-- ══ Live status ══
         The bar the show-day team reads. It is under the tabs rather than in
         the hero because it changes by the hour and the hero does not. ══ --}}
    @php $live = $header['live']; @endphp
    <div class="mt-3 overflow-hidden rounded-[22px] border border-line bg-white shadow-[0_10px_30px_-24px_rgba(11,31,58,0.45)]">
        <div class="flex flex-wrap items-stretch">
            <div class="flex min-w-[220px] flex-1 items-center gap-2.5 bg-navy-950 px-5 py-3.5 sm:flex-none">
                <span class="relative flex h-2.5 w-2.5 shrink-0">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full {{ $live['tone'] }} opacity-60"></span>
                    <span class="relative inline-flex h-2.5 w-2.5 rounded-full {{ $live['tone'] }}"></span>
                </span>
                <span class="leading-tight">
                    <span class="block text-eyebrow font-bold uppercase tracking-[0.2em] text-gold-300/90">Live status</span>
                    <span class="block text-[13px] font-bold text-white">{{ $live['label'] }}</span>
                </span>
            </div>

            <div class="scrollbar-none flex flex-1 items-center gap-x-6 gap-y-2 overflow-x-auto px-5 py-3">
                @foreach ($live['items'] as $item)
                    <div class="flex shrink-0 items-center gap-2">
                        <x-icon :name="$item['icon']" class="h-4 w-4 shrink-0 text-navy-300" />
                        <span class="leading-tight">
                            <span class="pf block text-[16px] font-black {{ $item['tone'] }}">{{ $item['value'] }}</span>
                            <span class="block text-[10.5px] text-muted">{{ $item['label'] }}</span>
                        </span>
                    </div>
                @endforeach
            </div>

            <div class="flex shrink-0 items-center px-4 py-3">
                <a href="{{ route('operations-room') }}"
                   class="flex h-10 items-center gap-2 rounded-xl bg-navy-950 px-4 text-[12.5px] font-bold text-white transition hover:bg-navy-800">
                    View Live Dashboard →
                </a>
            </div>
        </div>
    </div>

    <div class="mt-5">
        @includeIf('events.hub.' . $tab, ['event' => $event, 'health' => $health, 'ai' => $ai, 'alerts' => $alerts, 'workload' => $workload])
    </div>
</x-layouts.app>
