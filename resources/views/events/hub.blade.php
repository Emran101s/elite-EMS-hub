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

    {{-- ══ The tucked bar ══
         The hero, the health strip, the tabs and the live bar together stand
         650px tall — on a 900px laptop that is the whole screen before a single
         figure of the module you came for. They all scroll away now, which is
         right, but then nothing says which event you are in or how to reach
         another module. This slim rail stays: who, what is waiting, and the way
         back. 48px against the 650 it lets you scroll past. ══ --}}
    <div class="sticky top-0 z-30 -mx-1 hidden items-center gap-3 border-b border-line bg-page/92 px-3 py-2 backdrop-blur lg:flex">
        <span class="grid h-6 w-6 shrink-0 place-items-center overflow-hidden rounded-full bg-navy-950 ring-1 ring-gold-400/60">
            @if ($event->logoUrl())
                <img src="{{ $event->logoUrl() }}" alt="" class="h-full w-full object-cover">
            @else
                <x-event-crest :event="$event" class="h-full w-full" />
            @endif
        </span>

        <span class="min-w-0 truncate text-[12.5px] font-bold text-navy-900">{{ $event->name }}</span>
        <span class="h-3.5 w-px shrink-0 bg-line" aria-hidden="true"></span>
        <span class="shrink-0 text-eyebrow font-bold uppercase tracking-[0.14em] text-gold-700">{{ \App\Models\Event::moduleLabel($tab) }}</span>

        {{-- The same counts as the tabs, in the same two tones, so the rail and
             the row can never disagree about what is waiting. --}}
        @if ($attentionBar = $header['attention'] ?? [])
            <span class="ms-auto flex shrink-0 flex-wrap items-center gap-1.5">
                @foreach ($attentionBar as $key => $n)
                    <a href="{{ route('events.hub', [$event, 'tab' => $key]) }}"
                       @class([
                           'rounded-full px-2 py-0.5 text-[10.5px] font-bold transition hover:brightness-95',
                           'bg-risk/12 text-red-700' => $n['tone'] === 'alarm',
                           'bg-gold-100 text-gold-800' => $n['tone'] === 'wait',
                       ])>{{ $n['count'] }} {{ \App\Models\Event::moduleLabel($key) }}</a>
                @endforeach
            </span>
        @endif

        <button type="button" onclick="this.closest('main').scrollTo({top: 0, behavior: 'smooth'})"
                class="{{ ($header['attention'] ?? []) ? '' : 'ms-auto' }} shrink-0 rounded-lg bg-white px-2.5 py-1 text-eyebrow font-bold text-navy-600 ring-1 ring-line transition hover:text-navy-900">
            ↑ Modules
        </button>
    </div>

    {{-- ══ Module tabs ══
         The active module gets the navy tile and says what it is for; the rest
         are icon and name. Sub-labels on all twenty-one would be a wall of
         text you scroll past — on the one you are in, it is the caption. ══ --}}
    @php
        $modules = \App\Models\Event::HUB_TABS;
        // Where the work is. Only modules with something genuinely waiting
        // appear here — see EventCommandHeader::attention().
        $attention = $header['attention'] ?? [];
    @endphp
    {{-- Sticky earns its keep for a one-line scroller you keep returning to.
         Wrapped, the whole row is already on screen, so pinning three rows to
         the top would cost 124px of every screenful for nothing — it scrolls
         away with the rest. --}}
    <div class="sticky top-0 z-20 -mx-1 mt-3 bg-page/90 px-1 py-1.5 backdrop-blur lg:static lg:bg-transparent lg:backdrop-blur-none">
        {{-- Twenty-one tabs need ~2380px and the work area is 1186 — so half of
             them, and half of the counts above, sat behind a scrollbar that is
             deliberately invisible. On a desktop they wrap instead: two rows,
             everything on screen, nothing to discover by dragging. Narrow
             screens keep the scroller, where wrapping would make five rows. --}}
        <nav class="scrollbar-none flex items-stretch gap-1 overflow-x-auto lg:flex-wrap lg:overflow-x-visible"
             aria-label="Event modules">
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

                    {{-- What is waiting behind this tab. Twenty tabs of equal
                         weight mean the only way to learn whether Risks has
                         anything in it is to open Risks. --}}
                    @if ($n = $attention[$key] ?? null)
                        <span title="{{ $n['why'] }}" @class([
                            'ms-0.5 grid h-[18px] min-w-[18px] shrink-0 place-items-center rounded-full px-1 text-[10px] font-black tabular-nums',
                            'bg-white/20 text-white' => $tab === $key,
                            'bg-risk/12 text-red-700' => $tab !== $key && $n['tone'] === 'alarm',
                            'bg-gold-100 text-gold-800' => $tab !== $key && $n['tone'] === 'wait',
                        ])>{{ $n['count'] > 99 ? '99+' : $n['count'] }}</span>
                    @endif
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
                <a href="{{ route('home') }}"
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
