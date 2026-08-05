@php
    $span = $board['spanMinutes'];
    $start = $board['start'];
    $moduleHex = \App\Models\Event::moduleColor('transportation');
    // Bar geometry: where a run sits on the axis and how wide it is.
    $geo = function ($m) use ($start, $span) {
        $s = $m->effectiveDeparture();
        $e = $m->estimatedEnd();
        if (! $s || ! $e) return null;
        $left = max(0, $start->diffInMinutes($s) / $span * 100);
        $width = max(3.5, $s->diffInMinutes($e) / $span * 100);
        return ['left' => round($left, 3), 'width' => round(min($width, 100 - $left), 3)];
    };
    $conflict = fn ($m) => in_array($m->id, $board['conflictIds'], true);
@endphp

<div>
    {{-- ══ header strip ══ --}}
    <div class="strip-dark -mx-4 -mt-4 mb-4 !rounded-none px-4 py-4 text-white sm:-mx-6 sm:-mt-6 sm:px-6">
        <div class="mb-3 h-0.5 w-12 rounded-full" style="background: {{ $moduleHex }}" aria-hidden="true"></div>
        <div class="flex flex-wrap items-center gap-3">
            <a href="{{ route('events.hub', ['event' => $event, 'tab' => 'transportation']) }}"
               class="text-xs font-semibold text-white/50 hover:text-white">← Transportation</a>

            @if ($conflictCount)
                <span class="ml-auto flex items-center gap-2 rounded-full bg-red-500/20 px-3 py-1">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-red-300">
                        ⚠ {{ $conflictCount }} {{ \Illuminate\Support\Str::plural('conflict', $conflictCount) }}
                    </span>
                </span>
            @else
                <span class="ml-auto flex items-center gap-2 rounded-full bg-emerald-500/20 px-3 py-1">
                    <span class="text-eyebrow font-bold uppercase tracking-[0.14em] text-emerald-300">No clashes</span>
                </span>
            @endif
        </div>
        <h1 class="mt-2 flex items-center gap-2.5 text-2xl font-black leading-tight">
            <span class="flex h-8 w-8 items-center justify-center rounded-lg text-white" style="background: {{ $moduleHex }}">
                <x-icon name="truck" class="h-4 w-4" />
            </span>
            Dispatch Board
        </h1>
        <p class="text-xs text-white/55">{{ $event->name }} · drag a run onto another {{ $groupBy }} to reassign</p>
    </div>

    {{-- ══ controls ══ --}}
    <div class="mb-4 flex flex-wrap items-center gap-3">
        @if ($days->count() > 1)
            <div class="flex flex-wrap gap-1.5">
                @foreach ($days as $d)
                    <button type="button" wire:click="setDay('{{ $d }}')"
                            @class([
                                'rounded-full px-3 py-1.5 text-xs font-bold transition',
                                'bg-navy-900 text-white' => $day === $d,
                                'bg-navy-50 text-navy-600 hover:bg-navy-100' => $day !== $d,
                            ])>
                        {{ \Carbon\Carbon::parse($d)->format('D j M') }}
                    </button>
                @endforeach
            </div>
        @endif

        <div class="ml-auto flex rounded-xl border border-line bg-white p-0.5">
            @foreach (['driver' => 'By driver', 'vehicle' => 'By vehicle'] as $by => $label)
                <button type="button" wire:click="setGroupBy('{{ $by }}')"
                        @class([
                            'rounded-lg px-3 py-1.5 text-xs font-bold transition',
                            'bg-navy-900 text-white' => $groupBy === $by,
                            'text-navy-500 hover:text-navy-900' => $groupBy !== $by,
                        ])>{{ $label }}</button>
            @endforeach
        </div>
    </div>

    @if ($flash)
        <x-alert tone="ok" class="mb-4">{{ $flash }}</x-alert>
    @endif

    {{-- On a phone a Gantt is a lie — say so rather than break the layout. --}}
    <div class="mb-4 rounded-xl border border-gold-200 bg-gold-50/50 px-4 py-2.5 text-xs font-semibold text-navy-700 lg:hidden">
        The dispatch board is best on a tablet or desktop. On a phone, use
        <a href="{{ route('events.transport.live', $event) }}" class="font-bold text-navy-900 underline">Live</a>.
    </div>

    @if ($board['lanes']->isEmpty() && $board['unassigned']->isEmpty())
        <x-empty icon="truck" title="Nothing scheduled on this day"
                 hint="Movements with a departure time appear here as lanes you can dispatch." />
    @else
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <div class="min-w-[800px]" data-dispatch>

                    {{-- ══ time axis ══ --}}
                    <div class="flex border-b border-line bg-page/50">
                        <div class="w-44 shrink-0 border-r border-line px-4 py-2 text-eyebrow font-bold uppercase tracking-wide text-muted">
                            {{ $groupBy === 'vehicle' ? 'Vehicle' : 'Driver' }}
                        </div>
                        <div class="relative flex-1">
                            <div class="flex">
                                @foreach ($board['hours'] as $h)
                                    <div class="flex-1 border-r border-line/60 px-1 py-2 text-eyebrow font-bold text-muted last:border-0">{{ $h }}</div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    {{-- ══ resource lanes ══ --}}
                    @foreach ($board['lanes'] as $lane)
                        @php $hasClash = $lane['runs']->contains(fn ($m) => $conflict($m)); @endphp
                        <div class="flex border-b border-line last:border-0">
                            {{-- lane label --}}
                            <div @class([
                                    'w-44 shrink-0 border-r border-line px-4 py-3',
                                    'bg-red-50/60' => $hasClash,
                                ])>
                                <p class="truncate text-sm font-bold text-navy-900">{{ $lane['label'] }}</p>
                                @if ($lane['sublabel'])<p class="truncate text-eyebrow text-muted">{{ $lane['sublabel'] }}</p>@endif
                                @if ($hasClash)
                                    <p class="mt-0.5 text-eyebrow font-bold uppercase tracking-wide text-red-600">⚠ Double-booked</p>
                                @endif
                            </div>

                            {{-- the track --}}
                            <div class="relative min-h-[4rem] flex-1" data-lane="{{ $lane['key'] }}">
                                {{-- hour gridlines --}}
                                <div class="pointer-events-none absolute inset-0 flex">
                                    @foreach ($board['hours'] as $h)
                                        <div class="flex-1 border-r border-line/40 last:border-0"></div>
                                    @endforeach
                                </div>

                                @foreach ($lane['runs'] as $m)
                                    @php $g = $geo($m); @endphp
                                    @if ($g)
                                        <div data-run="{{ $m->id }}"
                                             style="left: {{ $g['left'] }}%; width: {{ $g['width'] }}%;"
                                             @class([
                                                 'absolute top-2 bottom-2 cursor-grab overflow-hidden rounded-lg border px-2 py-1 text-white shadow-sm transition',
                                                 'border-red-300 bg-red-600' => $conflict($m),
                                                 'border-gold-300 bg-gold-500 !text-navy-950' => ! $conflict($m) && $m->isPriority(),
                                                 'border-navy-700 bg-navy-800' => ! $conflict($m) && ! $m->isPriority(),
                                                 'opacity-50' => $m->status === 'cancelled',
                                             ])
                                             title="Car {{ $m->refLabel() }} · {{ $m->effectiveDeparture()?->format('H:i') }}–{{ $m->estimatedEnd()?->format('H:i') }} · {{ $m->pickup_from }} → {{ $m->drop_to }}{{ $conflict($m) ? ' · CLASHES' : '' }}">
                                            <p class="truncate text-eyebrow font-black leading-tight">
                                                {{ $m->refLabel() }} · {{ $m->effectiveDeparture()?->format('H:i') }}
                                            </p>
                                            <p class="truncate text-eyebrow leading-tight opacity-80">
                                                {{ \Illuminate\Support\Str::limit(($m->pickup_from ?: '—').'→'.($m->drop_to ?: '—'), 22) }}
                                            </p>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endforeach

                    {{-- ══ unassigned lane — the pool to dispatch from ══ --}}
                    @if ($board['unassigned']->isNotEmpty())
                        <div class="flex border-t-2 border-navy-900 bg-amber-50/40">
                            <div class="w-44 shrink-0 border-r border-line px-4 py-3">
                                <p class="text-sm font-bold text-amber-700">Unassigned</p>
                                <p class="text-eyebrow text-muted">{{ $board['unassigned']->count() }} to place</p>
                            </div>
                            <div class="relative min-h-[4rem] flex-1" data-lane="none">
                                <div class="pointer-events-none absolute inset-0 flex">
                                    @foreach ($board['hours'] as $h)
                                        <div class="flex-1 border-r border-line/40 last:border-0"></div>
                                    @endforeach
                                </div>
                                @foreach ($board['unassigned'] as $m)
                                    @php $g = $geo($m); @endphp
                                    @if ($g)
                                        <div data-run="{{ $m->id }}"
                                             style="left: {{ $g['left'] }}%; width: {{ $g['width'] }}%;"
                                             class="absolute top-2 bottom-2 cursor-grab overflow-hidden rounded-lg border border-dashed border-amber-400 bg-white px-2 py-1 text-navy-900 shadow-sm"
                                             title="Car {{ $m->refLabel() }} — drag onto a {{ $groupBy }} to dispatch">
                                            <p class="truncate text-eyebrow font-black leading-tight">{{ $m->refLabel() }} · {{ $m->effectiveDeparture()?->format('H:i') }}</p>
                                            <p class="truncate text-eyebrow leading-tight text-muted">{{ \Illuminate\Support\Str::limit(($m->pickup_from ?: '—').'→'.($m->drop_to ?: '—'), 22) }}</p>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        </div>
                    @endif
                </div>
            </div>
        </div>

        {{-- legend --}}
        <div class="mt-3 flex flex-wrap items-center gap-4 text-eyebrow text-muted">
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-navy-800"></span> Scheduled</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-gold-500"></span> Priority</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded bg-red-600"></span> Double-booked</span>
            <span class="flex items-center gap-1.5"><span class="h-3 w-3 rounded border border-dashed border-amber-400 bg-white"></span> Unassigned</span>
        </div>
    @endif
</div>

@script
<script>
    // Drag a run bar onto another lane to reassign its driver / vehicle.
    // Same pattern as the guest pool: lanes receive, Livewire re-renders the truth.
    (() => {
        const wire = $wire;

        const wireUp = () => {
            document.querySelectorAll('[data-lane]').forEach((lane) => {
                if (lane.dataset.sortableBound) return;
                lane.dataset.sortableBound = '1';

                new window.Sortable(lane, {
                    group: 'dispatch',
                    sort: false,
                    animation: 140,
                    draggable: '[data-run]',
                    ghostClass: 'opacity-40',
                    onAdd(evt) {
                        const runId = Number(evt.item?.dataset?.run);
                        const laneKey = lane.dataset.lane;
                        evt.item.remove();               // truth comes from the re-render
                        if (runId && laneKey) {
                            wire.reassign(runId, laneKey);
                        }
                    },
                });
            });
        };

        wireUp();
        Livewire.hook('morphed', wireUp);
    })();
</script>
@endscript
