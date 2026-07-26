@props(['primary' => [], 'events' => []])
{{--
    The Event Command Canvas — a circular command arena rather than a rectangle.

    A radial field with concentric orbit rings puts the primary event genuinely
    at the centre of gravity; the pods sit on the rings and connect back with
    spokes. Zoom and pan are transform-only, so nothing reflows.

    Canvas is the only view. A command surface that also offers a table is two
    products sharing a header.
--}}
<section class="rounded-[22px] border border-cc-line bg-white p-4 cc-lift-2 xl:p-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-[13px] font-extrabold uppercase tracking-[0.14em] text-cc-navy">Event Command Canvas</h2>
            <p class="mt-1 text-[12px] text-cc-ink-2">Live view of all events and operations</p>
        </div>
        <div class="flex items-center gap-2">
            <span class="flex items-center gap-2 rounded-full bg-cc-mist px-3 py-1.5 text-[11px] font-bold text-cc-ink-2">
                <span class="relative flex h-2 w-2">
                    <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-cc-ok opacity-60"></span>
                    <span class="relative inline-flex h-2 w-2 rounded-full bg-cc-ok"></span>
                </span>
                {{ count($events) + 1 }} events live
            </span>
            <button type="button" class="grid h-9 w-9 place-items-center rounded-xl border border-cc-line bg-white text-cc-ink-2 transition hover:border-cc-gold hover:text-cc-navy" title="Expand">
                <x-canvas.icon name="expand" :size="16" />
            </button>
        </div>
    </div>

    {{-- ══ THE ARENA ══ --}}
    <div class="relative mt-4 overflow-hidden rounded-[32px] border border-cc-line bg-cc-mist/50">

        {{-- the field: a radial well that falls away from the centre --}}
        <div class="cc-well pointer-events-none absolute inset-0"></div>
        <div class="cc-map pointer-events-none absolute inset-0 opacity-50"></div>

        {{-- canvas controls --}}
        <div class="absolute left-3 top-3 z-30 flex flex-col gap-1 rounded-2xl border border-cc-line bg-white/95 p-1 backdrop-blur cc-lift-1">
            @foreach ([['in', 'zin'], ['out', 'zout'], ['reset', 'center'], [null, 'layers']] as [$act, $icon])
                <button type="button" @if ($act) data-zoom="{{ $act }}" @endif
                        class="grid h-8 w-8 place-items-center rounded-xl text-cc-ink-2 transition hover:bg-cc-mist hover:text-cc-navy">
                    <x-canvas.icon :name="$icon" :size="16" />
                </button>
            @endforeach
        </div>

        {{-- minimap --}}
        <div class="absolute bottom-3 right-3 z-30 hidden w-[128px] rounded-2xl border border-cc-line bg-white/90 p-2 backdrop-blur cc-lift-1 lg:block">
            <div class="relative h-[50px] overflow-hidden rounded-xl bg-cc-mist">
                <span class="cc-hex-flat absolute left-1/2 top-1/2 h-3.5 w-3 -translate-x-1/2 -translate-y-1/2 bg-cc-navy"></span>
                @foreach ($events as $e)
                    <span class="absolute h-1.5 w-1.5 rounded-full bg-cc-gray" style="left:{{ $e['x'] + 14 }}%;top:{{ $e['y'] * 0.6 + 16 }}%"></span>
                @endforeach
            </div>
            <div class="mt-2 flex items-center gap-1.5">
                <x-canvas.icon name="zout" :size="12" class="text-cc-ink-3" />
                <span class="h-1 flex-1 rounded-full bg-cc-line"><span class="block h-1 w-1/2 rounded-full bg-cc-blue"></span></span>
                <x-canvas.icon name="zin" :size="12" class="text-cc-ink-3" />
            </div>
        </div>

        {{-- the stage: transformed by zoom/pan --}}
        <div class="relative h-[690px] cursor-grab select-none xl:h-[740px]">
            <div data-canvas-stage class="absolute inset-0 origin-center transition-transform duration-200 ease-out">

                {{-- orbit rings + spokes --}}
                <svg class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true">
                    @foreach ($events as $e)
                        <line x1="50%" y1="50%" x2="{{ $e['x'] + $e['w'] / 2 }}%" y2="{{ $e['y'] + 8 }}%"
                              stroke="currentColor" class="text-cc-gray" stroke-width="1.3" stroke-dasharray="3 7" />
                    @endforeach
                    <circle cx="50%" cy="50%" r="26%" fill="none" stroke="currentColor" class="text-cc-line" stroke-width="1.3" />
                    <circle cx="50%" cy="50%" r="38%" fill="none" stroke="currentColor" class="text-cc-line" stroke-width="1.3" stroke-dasharray="2 9" />
                    <circle cx="50%" cy="50%" r="49%" fill="none" stroke="currentColor" class="text-cc-line" stroke-width="1.3" stroke-dasharray="2 14" />
                </svg>

                {{-- orbiting pods --}}
                @foreach ($events as $e)
                    <div class="absolute" style="left:{{ $e['x'] }}%;top:{{ $e['y'] }}%;width:{{ $e['w'] }}%;min-width:288px">
                        <x-canvas.event-command-object :event="$e" />
                    </div>
                @endforeach

                {{-- the primary event — the one dark hexagon at the centre of gravity --}}
                <div class="absolute left-1/2 top-1/2 w-[268px] -translate-x-1/2 -translate-y-1/2">
                    <div class="group relative">
                        {{-- a soft halo so the centre reads as the well of the field --}}
                        <span class="pointer-events-none absolute -inset-14 rounded-full bg-cc-gold/10 blur-2xl transition duration-500 group-hover:bg-cc-gold/20"></span>
                        <span class="cc-hex absolute -inset-[3px] bg-cc-gold opacity-90 transition group-hover:opacity-100"></span>
                        <div class="cc-hex relative bg-gradient-to-b from-cc-navy to-cc-navy-3 px-8 py-8 text-center transition duration-300 group-hover:cc-glow">
                            <div class="flex items-center justify-center gap-2">
                                <span class="rounded-full bg-cc-gold px-2.5 py-1 text-[9px] font-extrabold uppercase tracking-[0.16em] text-cc-navy">Live Event</span>
                                <x-canvas.icon name="star" :size="15" class="text-cc-gold" />
                            </div>

                            <h3 class="mt-3 text-[19px] font-extrabold leading-tight tracking-tight text-white">{{ $primary['name'] }}</h3>
                            <p class="mt-2 flex items-center justify-center gap-1.5 text-[11px] text-white/65"><x-canvas.icon name="cal" :size="12" />{{ $primary['dates'] }}</p>
                            <p class="mt-1 flex items-center justify-center gap-1.5 text-[11px] text-white/65"><x-canvas.icon name="pin" :size="12" />{{ $primary['venue'] }}</p>

                            <div class="mt-4 flex items-center justify-center gap-5">
                                @php $r = 20; $c = 2 * M_PI * $r; @endphp
                                <span class="relative grid h-[52px] w-[52px] place-items-center">
                                    <svg width="52" height="52" viewBox="0 0 52 52" class="-rotate-90">
                                        <circle cx="26" cy="26" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="4" class="text-white/15" />
                                        <circle cx="26" cy="26" r="{{ $r }}" fill="none" stroke="currentColor" stroke-width="4" stroke-linecap="round"
                                                class="text-cc-ok" stroke-dasharray="{{ round($c, 1) }}" stroke-dashoffset="{{ round($c - $c * $primary['health'] / 100, 1) }}" />
                                    </svg>
                                    <span class="absolute text-[12px] font-extrabold text-white">{{ $primary['health'] }}%</span>
                                </span>
                                <div class="text-left">
                                    <p class="text-[17px] font-extrabold leading-none text-white">{{ $primary['participants'] }}</p>
                                    <p class="mt-1 text-[10px] text-white/55">Participants</p>
                                </div>
                                <div class="text-left">
                                    <p class="text-[17px] font-extrabold leading-none text-cc-risk">{{ $primary['risks'] }}</p>
                                    <p class="mt-1 text-[10px] text-white/55">Risks</p>
                                </div>
                            </div>

                            <p class="mt-4 text-[9.5px] font-bold uppercase tracking-[0.16em] text-white/45">Next Action</p>
                            <p class="mt-1 text-[12px] font-bold text-cc-gold">{{ $primary['nextAction'] }}</p>

                            <a href="#" class="mt-4 inline-flex items-center gap-2 rounded-full bg-cc-gold px-5 py-2.5 text-[12px] font-extrabold text-cc-navy transition hover:bg-cc-gold-2">
                                Enter Control Room <x-canvas.icon name="chevR" :size="14" />
                            </a>
                        </div>
                    </div>
                </div>

                {{-- add — a hexagon, like every other object on this field --}}
                <button type="button" class="group absolute bottom-[7%] right-[9%] grid h-[84px] w-[76px] place-items-center">
                    <span class="cc-hex-flat absolute inset-0 border-2 border-dashed border-cc-gray bg-white/70 transition group-hover:border-cc-gold group-hover:bg-cc-gold/10"></span>
                    <span class="relative grid place-items-center gap-1 text-cc-ink-3 transition group-hover:text-cc-navy">
                        <x-canvas.icon name="plus" :size="17" />
                        <span class="text-[9.5px] font-bold">New Event</span>
                    </span>
                </button>
            </div>
        </div>
    </div>
</section>
