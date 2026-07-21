<x-layouts.app title="Run of Show"
               :subtitle="$event->name . '  ·  EVT-' . str_pad($event->id, 3, '0', STR_PAD_LEFT) . ($event->venue ? '  ·  ' . $event->venue->name : '')"
               :crumbs="[
                   ['label' => 'Command Center', 'href' => route('home')],
                   ['label' => 'Events', 'href' => route('events.index')],
                   ['label' => $event->name, 'href' => route('events.hub', $event)],
                   ['label' => 'Run of Show'],
               ]">

    {{-- Top actions --}}
    <div class="mb-5 flex items-center justify-end gap-2">
        <a href="{{ route('events.run-of-show.pdf', [$event, 'day' => $day?->id]) }}" class="btn-ghost !h-10 gap-2">
            <x-icon name="chart" class="h-4 w-4" /> This day
        </a>
        <a href="{{ route('events.run-of-show.pdf', $event) }}" class="btn-ghost !h-10 gap-2">
            <x-icon name="chart" class="h-4 w-4" /> Full run
        </a>
        <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" class="btn-navy h-10 px-4 text-sm !text-white">Edit Agenda →</a>
    </div>

    {{-- Day tabs --}}
    @if ($days->isNotEmpty())
        <div class="mb-5 flex flex-wrap gap-2.5">
            @foreach ($days as $d)
                <a href="{{ route('events.run-of-show', [$event, 'day' => $d->id]) }}"
                   @class([
                       'w-32 rounded-2xl border px-4 py-3 transition',
                       'border-navy-900 bg-navy-900 text-white' => $day && $day->id === $d->id,
                       'border-line bg-white text-navy-600 hover:border-navy-200' => ! ($day && $day->id === $d->id),
                   ])>
                    <p class="text-3xs font-bold uppercase tracking-widest {{ $day && $day->id === $d->id ? 'text-gold-400' : 'text-muted' }}">Day {{ $loop->iteration }}</p>
                    <p class="mt-0.5 text-sm font-bold">{{ $d->date?->format('D, j M') }}</p>
                    <p class="text-[0.65rem] {{ $day && $day->id === $d->id ? 'text-white/60' : 'text-muted' }}">{{ $d->sessions->count() }} {{ str('item')->plural($d->sessions->count()) }}</p>
                </a>
            @endforeach
        </div>
    @endif

    {{-- Timeline --}}
    <div class="card overflow-hidden">
        @if ($timeline)
            <div class="overflow-x-auto">
                <div class="min-w-[820px]">
                    {{-- Hour axis --}}
                    <div class="relative ml-[150px] h-7 border-b border-line">
                        @foreach ($timeline['hours'] as $hour)
                            <span class="absolute top-1.5 -translate-x-1/2 text-[0.62rem] font-semibold text-navy-300" style="left: {{ $hour['left'] }}%">{{ $hour['label'] }}</span>
                        @endforeach
                    </div>

                    {{-- Lanes --}}
                    @foreach ($timeline['lanes'] as $lane)
                        <div class="flex items-stretch border-b border-line last:border-b-0">
                            <div class="flex w-[150px] shrink-0 flex-col justify-center border-r border-line px-4 py-4">
                                <p class="flex items-center gap-1.5 text-xs font-bold text-navy-900"><span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span> {{ $lane['room'] }}</p>
                                <p class="text-3xs text-muted">{{ $lane['blocks']->count() }} {{ str('session')->plural($lane['blocks']->count()) }}</p>
                            </div>
                            <div class="relative flex-1">
                                {{-- gridlines --}}
                                @foreach ($timeline['hours'] as $hour)
                                    <span class="absolute inset-y-0 w-px bg-line/70" style="left: {{ $hour['left'] }}%"></span>
                                @endforeach
                                {{-- session blocks --}}
                                <div class="relative py-3" style="min-height: 62px">
                                    @foreach ($lane['blocks'] as $b)
                                        <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}"
                                           class="group absolute top-3 flex h-10 items-center overflow-hidden rounded-lg px-2.5 text-white shadow-sm transition hover:brightness-95"
                                           style="left: {{ $b['left'] }}%; width: {{ $b['width'] }}%; background: {{ $b['hex'] }}"
                                           title="{{ $b['session']->title }} · {{ substr($b['session']->starts_at, 0, 5) }}–{{ substr($b['session']->ends_at, 0, 5) }}">
                                            <span class="truncate text-[0.68rem] font-bold">{{ $b['session']->title }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            {{-- Legend --}}
            <div class="flex flex-wrap items-center gap-x-5 gap-y-2 border-t border-line px-5 py-3.5">
                <span class="text-3xs font-bold uppercase tracking-widest text-muted">Legend</span>
                @foreach ($legend as [$label, $hex])
                    <span class="flex items-center gap-1.5 text-2xs font-medium text-navy-700">
                        <span class="h-3 w-4 rounded" style="background: {{ $hex }}"></span> {{ $label }}
                    </span>
                @endforeach
            </div>
        @else
            <div class="flex flex-col items-center px-8 py-16 text-center">
                <h3 class="text-sm font-bold text-navy-900">Nothing scheduled for this day</h3>
                <p class="mt-1 max-w-md text-xs text-muted">Add sessions in the agenda builder and they'll plot on the timeline here.</p>
                <a href="{{ route('events.hub', [$event, 'tab' => 'agenda']) }}" class="btn-gold mt-4 h-9 px-4 text-xs">Open Agenda Builder →</a>
            </div>
        @endif
    </div>

    {{-- Event Overview matrix --}}
    <div class="card mt-6 overflow-hidden">
        <div class="border-b border-line px-5 py-3.5"><h3 class="text-sm font-bold text-navy-900">Event Overview</h3></div>
        <div class="overflow-x-auto">
            <table class="w-full min-w-[640px]">
                <thead>
                    <tr class="border-b border-line">
                        <th class="w-32 px-5 py-3 text-left text-3xs font-bold uppercase tracking-widest text-muted">Module</th>
                        @foreach ($overview as $o)
                            <th class="px-4 py-3 text-left text-[0.65rem] font-bold text-navy-900">{{ $o['label'] }}<span class="ml-1 font-normal text-muted">{{ $o['date']?->format('j M') }}</span></th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ([['Agenda', 'agenda', '#0B1F3A'], ['Tasks', 'tasks', '#3B82F6']] as [$rowLabel, $rowKey, $rowColor])
                        <tr class="border-b border-line last:border-b-0">
                            <td class="px-5 py-3 text-xs font-semibold text-navy-900"><span class="mr-1.5 inline-block h-2 w-2 rounded-full align-middle" style="background: {{ $rowColor }}"></span>{{ $rowLabel }}</td>
                            @foreach ($overview as $o)
                                <td class="px-4 py-3">
                                    @if ($o[$rowKey] > 0)
                                        <span class="flex h-6 items-center justify-center rounded-lg text-2xs font-bold text-white" style="background: {{ $rowColor }}">{{ $o[$rowKey] }}</span>
                                    @else
                                        <span class="text-2xs text-navy-200">—</span>
                                    @endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
