@props(['months', 'lanes', 'active'])

<div class="overflow-hidden rounded-lg border border-line bg-white shadow-raise">
    <div class="flex flex-wrap items-center gap-x-3 gap-y-2 border-b border-line bg-page px-4 py-3.5">
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Timeline</p>
            <h2 class="text-[16px] font-bold text-ink">{{ $months['label'] ?? 'Strategic timeline' }}</h2>
        </div>
        <p class="hidden text-[11.5px] text-muted sm:block">Where every mission sits in the year.</p>

        <div class="ms-auto flex items-center gap-2">
            <button type="button" data-fp-today class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-3 py-1.5 text-[11.5px] font-bold text-ink transition hover:border-navy-300">
                <x-icon name="calendar" class="h-3.5 w-3.5" /> Today
            </button>
            <div class="flex h-8 items-center gap-0.5 rounded-lg border border-line bg-white px-1">
                <button type="button" data-fp-zoom="-1" class="grid h-6 w-6 place-items-center rounded-md text-muted transition hover:bg-page" aria-label="Zoom out">−</button>
                <span data-fp-level class="w-10 text-center text-[10.5px] font-bold tabular-nums text-ink">100%</span>
                <button type="button" data-fp-zoom="1" class="grid h-6 w-6 place-items-center rounded-md text-muted transition hover:bg-page" aria-label="Zoom in">+</button>
            </div>
        </div>
    </div>

    @if (! $months)
        <p class="px-4 py-10 text-center text-[12px] text-muted">No mission carries a date yet, so there is no path to draw.</p>
    @else
        <div class="scrollbar-none overflow-x-auto p-4">
            <div data-fp-canvas data-fp-base="{{ max(880, count($months['list']) * 190) }}"
                 style="min-width: {{ max(880, count($months['list']) * 190) }}px">

                <div class="relative ms-[172px] h-9 border-b border-line">
                    @foreach ($months['list'] as $month)
                        <span class="absolute top-1" style="left: {{ $month['left'] }}%">
                            <span @class(['rounded-md px-2 py-1 text-[10.5px] font-bold uppercase tracking-[0.14em]',
                                'bg-navy-900 text-white' => $month['current'],
                                'text-muted' => ! $month['current']])>{{ $month['label'] }}</span>
                        </span>
                    @endforeach
                </div>

                <div class="relative">
                    <div class="pointer-events-none absolute inset-y-0 left-[172px] right-0 z-0" aria-hidden="true">
                        @foreach ($months['list'] as $month)
                            <span class="absolute inset-y-0 w-px bg-line/60" style="left: {{ $month['left'] }}%"></span>
                        @endforeach
                        @if ($months['todayLeft'] !== null)
                            <span data-fp-line class="absolute inset-y-0 w-px bg-gold-500/70" style="left: {{ $months['todayLeft'] }}%">
                                <span class="absolute -top-[3px] left-0 h-0 w-0 border-y-[6px] border-l-[9px] border-y-transparent border-l-gold-500 drop-shadow-[0_2px_3px_rgba(212,175,55,0.4)]"></span>
                            </span>
                        @endif
                    </div>

                    @foreach ($lanes as $lane)
                        <div @class(['relative flex items-stretch border-b border-line/70 last:border-b-0', 'bg-page/60' => $loop->even])>
                            <div class="z-10 flex w-[172px] shrink-0 items-center gap-2.5 py-4 pe-4 {{ $loop->even ? 'bg-page/60' : 'bg-white' }}">
                                <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-page text-muted">
                                    <x-icon :name="$lane['icon']" class="h-4 w-4" />
                                </span>
                                <span class="min-w-0">
                                    <span class="block truncate text-[12px] font-bold text-ink">{{ $lane['label'] }}</span>
                                    <span class="block text-[10px] text-muted">{{ $lane['missions']->count() }} {{ str('mission')->plural($lane['missions']->count()) }}</span>
                                </span>
                            </div>

                            <div class="relative min-h-[108px] flex-1 py-3">
                                <div class="pointer-events-none absolute inset-x-0 bottom-2 h-px bg-line/50" aria-hidden="true"></div>

                                @foreach ($lane['missions'] as $m)
                                    @php
                                        $start = $m['event']->starts_at;
                                        $left = $start ? round($months['from']->diffInDays($start) / $months['span'] * 100, 3) : 0;
                                        $on = $active && $m['id'] === $active['id'];
                                    @endphp
                                    <span class="pointer-events-none absolute bottom-2 z-0 h-2.5 w-2.5 -translate-x-1/2 translate-y-1/2 rounded-full ring-2 ring-white"
                                          style="left: {{ $left }}%; background: {{ $m['statusHex'] }}" aria-hidden="true"></span>
                                    <button type="button" wire:click="activate({{ $m['id'] }})" wire:key="fp-{{ $m['id'] }}"
                                            @class(['absolute top-3 z-10 flex w-[236px] items-center gap-2.5 rounded-xl border p-2 text-left transition',
                                                'border-gold-400 bg-white shadow-float ring-2 ring-gold-400/25' => $on,
                                                'border-line bg-white/90 shadow-sm backdrop-blur hover:-translate-y-0.5 hover:border-gold-300 hover:shadow-lg' => ! $on])
                                            style="left: min({{ $left }}%, calc(100% - 236px))">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-[12px] font-bold text-ink">{{ $m['name'] }}</span>
                                            <span class="block truncate text-[10px] text-muted">{{ $m['shortDates'] }} · {{ $m['where'] }}</span>
                                            <span class="mt-1 flex items-center gap-1.5">
                                                @if ($m['statusLabel'])<span class="rounded-full px-1.5 py-0.5 text-[8.5px] font-bold bg-info-soft text-info-ink">{{ $m['statusLabel'] }}</span>@endif
                                                <span class="text-[10px] font-bold tabular-nums text-muted">{{ $m['progress'] }}%</span>
                                            </span>
                                            <span class="mt-1 block h-[3px] overflow-hidden rounded-full bg-page">
                                                <span class="block h-full rounded-full" style="width: {{ $m['progress'] }}%; background: {{ $m['statusHex'] }}"></span>
                                            </span>
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
