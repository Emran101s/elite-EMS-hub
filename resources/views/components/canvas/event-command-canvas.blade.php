@props(['primary' => [], 'events' => []])
{{--
    The Event Command Canvas. Objects float on a mapped ground, joined by dotted
    links to the primary event at the centre. Zoom and pan are transform-only.
--}}
<section class="rounded-[22px] border border-cc-line bg-white p-4 cc-lift-2 xl:p-5">

    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-[13px] font-extrabold uppercase tracking-[0.14em] text-cc-navy">Event Command Canvas</h2>
            <p class="mt-1 text-[12px] text-cc-ink-2">Live view of all events and operations</p>
        </div>
        <div class="flex items-center gap-2">
            <div class="flex items-center gap-1 rounded-xl border border-cc-line bg-cc-mist p-1">
                @foreach (['canvas' => 'Canvas View', 'list' => 'List View', 'calendar' => 'Calendar View'] as $key => $label)
                    <button type="button" data-view-btn="{{ $key }}" aria-pressed="{{ $key === 'canvas' ? 'true' : 'false' }}"
                            class="rounded-lg px-3 py-1.5 text-[11.5px] font-bold text-cc-ink-2 transition aria-pressed:bg-white aria-pressed:text-cc-navy aria-pressed:cc-lift-1">{{ $label }}</button>
                @endforeach
            </div>
            <button type="button" class="grid h-9 w-9 place-items-center rounded-xl border border-cc-line bg-white text-cc-ink-2 transition hover:border-cc-gold hover:text-cc-navy">
                <x-canvas.icon name="expand" :size="16" />
            </button>
        </div>
    </div>

    {{-- ══ CANVAS VIEW ══ --}}
    <div data-view-pane="canvas" class="relative mt-4 overflow-hidden rounded-2xl border border-cc-line bg-cc-mist/40">
        <div class="cc-map pointer-events-none absolute inset-0 opacity-70"></div>

        {{-- canvas controls --}}
        <div class="absolute left-3 top-3 z-20 flex flex-col gap-1 rounded-xl border border-cc-line bg-white p-1 cc-lift-1">
            @foreach ([['in', 'zin'], ['out', 'zout'], ['reset', 'center'], [null, 'layers']] as [$act, $icon])
                <button type="button" @if ($act) data-zoom="{{ $act }}" @endif
                        class="grid h-8 w-8 place-items-center rounded-lg text-cc-ink-2 transition hover:bg-cc-mist hover:text-cc-navy">
                    <x-canvas.icon :name="$icon" :size="16" />
                </button>
            @endforeach
        </div>

        {{-- minimap --}}
        <div class="absolute bottom-3 right-3 z-20 hidden w-[130px] rounded-xl border border-cc-line bg-white/90 p-2 backdrop-blur cc-lift-1 lg:block">
            <div class="cc-map relative h-[52px] rounded-md bg-cc-mist">
                <span class="absolute left-1/2 top-1/2 h-3 w-3 -translate-x-1/2 -translate-y-1/2 rotate-45 rounded-[2px] bg-cc-navy"></span>
                @foreach ($events as $e)
                    <span class="absolute h-1.5 w-1.5 rounded-full bg-cc-gray" style="left:{{ $e['x'] + 12 }}%;top:{{ $e['y'] * 0.7 + 12 }}%"></span>
                @endforeach
            </div>
            <div class="mt-2 flex items-center gap-1.5">
                <x-canvas.icon name="zout" :size="12" class="text-cc-ink-3" />
                <span class="h-1 flex-1 rounded-full bg-cc-line"><span class="block h-1 w-1/2 rounded-full bg-cc-blue"></span></span>
                <x-canvas.icon name="zin" :size="12" class="text-cc-ink-3" />
            </div>
        </div>

        {{-- the stage: transformed by zoom/pan --}}
        {{-- Tall enough that the five orbiting objects clear the centre hexagon at
             every width — the ring geometry, not the card sizes, sets this. --}}
        <div class="relative h-[740px] cursor-grab select-none xl:h-[860px]">
            <div data-canvas-stage class="absolute inset-0 origin-center transition-transform duration-200 ease-out">

                {{-- dotted links from centre to each object --}}
                <svg class="pointer-events-none absolute inset-0 h-full w-full" aria-hidden="true">
                    @foreach ($events as $e)
                        <line x1="50%" y1="50%" x2="{{ $e['x'] + $e['w'] / 2 }}%" y2="{{ $e['y'] + 14 }}%"
                              stroke="currentColor" class="text-cc-gray" stroke-width="1.4" stroke-dasharray="3 6" />
                    @endforeach
                    <circle cx="50%" cy="50%" r="30%" fill="none" stroke="currentColor" class="text-cc-line" stroke-width="1.2" />
                    <circle cx="50%" cy="50%" r="44%" fill="none" stroke="currentColor" class="text-cc-line" stroke-width="1.2" stroke-dasharray="2 8" />
                </svg>

                {{-- orbiting objects --}}
                @foreach ($events as $e)
                    <div class="absolute" style="left:{{ $e['x'] }}%;top:{{ $e['y'] }}%;width:{{ $e['w'] }}%;min-width:230px">
                        <x-canvas.event-command-object :event="$e" />
                    </div>
                @endforeach

                {{-- the primary event — the one dark hexagon --}}
                <div class="absolute left-1/2 top-1/2 w-[264px] -translate-x-1/2 -translate-y-1/2">
                    <div class="group relative">
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

                {{-- add --}}
                <button type="button" class="absolute bottom-[6%] right-[10%] grid h-[86px] w-[86px] place-items-center gap-1 rounded-full border-2 border-dashed border-cc-gray text-cc-ink-3 transition hover:border-cc-gold hover:text-cc-navy">
                    <x-canvas.icon name="plus" :size="18" />
                    <span class="text-[10px] font-bold">New Event</span>
                </button>
            </div>
        </div>
    </div>

    {{-- ══ LIST VIEW ══ --}}
    <div data-view-pane="list" hidden class="mt-4 overflow-x-auto rounded-2xl border border-cc-line">
        <table class="w-full min-w-[720px] border-collapse text-left">
            <thead>
                <tr class="bg-cc-mist text-[10px] font-bold uppercase tracking-[0.12em] text-cc-ink-3">
                    <th class="px-4 py-3">Event</th><th class="px-4 py-3">Status</th><th class="px-4 py-3">Dates</th>
                    <th class="px-4 py-3">Location</th><th class="px-4 py-3 text-right">Participants</th>
                    <th class="px-4 py-3 text-right">Budget</th><th class="px-4 py-3 text-right">Health</th>
                </tr>
            </thead>
            <tbody>
                @foreach (array_merge([[
                    'name' => $primary['name'], 'statusLabel' => 'Live', 'dates' => $primary['dates'],
                    'location' => $primary['venue'], 'participants' => $primary['participants'],
                    'budget' => '—', 'health' => $primary['health'],
                ]], $events) as $e)
                    <tr class="border-t border-cc-line transition hover:bg-cc-mist/60">
                        <td class="px-4 py-3 text-[13px] font-bold text-cc-navy">{{ $e['name'] }}</td>
                        <td class="px-4 py-3 text-[12px] text-cc-ink-2">{{ $e['statusLabel'] }}</td>
                        <td class="px-4 py-3 text-[12px] text-cc-ink-2">{{ $e['dates'] }}</td>
                        <td class="px-4 py-3 text-[12px] text-cc-ink-2">{{ $e['location'] }}</td>
                        <td class="px-4 py-3 text-right text-[12.5px] font-semibold tabular-nums text-cc-navy">{{ $e['participants'] }}</td>
                        <td class="px-4 py-3 text-right text-[12.5px] font-semibold tabular-nums text-cc-navy">{{ $e['budget'] }}</td>
                        <td class="px-4 py-3 text-right text-[12.5px] font-bold tabular-nums text-cc-navy">{{ $e['health'] }}%</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{-- ══ CALENDAR VIEW ══ --}}
    <div data-view-pane="calendar" hidden class="mt-4 rounded-2xl border border-cc-line p-4">
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-6">
            @foreach (['Aug', 'Sep', 'Oct', 'Nov', 'Dec', 'Jan'] as $i => $month)
                <div class="rounded-xl border border-cc-line bg-cc-mist/50 p-3">
                    <p class="text-[11px] font-extrabold uppercase tracking-[0.14em] text-cc-navy">{{ $month }} 2026</p>
                    <div class="mt-3 space-y-1.5">
                        @foreach (collect($events)->filter(fn ($e) => str_contains($e['dates'], $month)) as $e)
                            <p class="truncate rounded-md bg-white px-2 py-1.5 text-[11px] font-semibold text-cc-navy cc-lift-1">{{ $e['name'] }}</p>
                        @endforeach
                        @if ($month === 'Nov')
                            <p class="truncate rounded-md bg-cc-navy px-2 py-1.5 text-[11px] font-semibold text-white">{{ $primary['name'] }}</p>
                        @endif
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
