<!doctype html>
{{--
    Landscape, one day per page, car number leftmost. Scanned against a
    clipboard rather than read, so density is the right call here — the
    generous type lives on the driver sheet instead.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Daily Movement Schedule</title>
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .day { page-break-after: always; }
        .day:last-child { page-break-after: auto; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
    </style>
</head>
<body class="bg-white text-navy-900">

@forelse ($days as $date => $runs)
    <div class="day">

        {{-- ═══ header ═══ --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-8 py-5 text-white">
            <div class="pointer-events-none absolute -right-10 -top-16 h-48 w-48 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.24),transparent_70%)]"></div>
            <div class="relative flex items-end justify-between gap-8">
                <div class="min-w-0">
                    <p class="text-3xs font-bold uppercase tracking-[0.28em] text-gold-300">Daily Movement Schedule</p>
                    <h1 class="mt-1 text-2xl font-black leading-tight text-white" style="font-family:'Spectral',Georgia,serif">{{ $event->name }}</h1>
                    @if ($selection !== 'All movements')
                        <p class="mt-1.5 inline-block rounded-full bg-gold-400/15 px-2.5 py-1 text-3xs font-bold uppercase tracking-[0.16em] text-gold-200">{{ $selection }}</p>
                    @endif
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">
                        {{ $date === 'unscheduled' ? 'Not yet scheduled' : \Carbon\Carbon::parse($date)->format('l') }}
                    </p>
                    <p class="text-2xl font-black leading-none text-white">
                        {{ $date === 'unscheduled' ? '—' : \Carbon\Carbon::parse($date)->format('j M Y') }}
                    </p>
                    <p class="mt-1 text-3xs font-bold uppercase tracking-wide text-gold-300">
                        {{ $runs->count() }} {{ \Illuminate\Support\Str::plural('movement', $runs->count()) }}
                        · {{ $runs->sum(fn ($m) => $m->paxCount()) }} passengers
                    </p>
                </div>
            </div>
        </div>

        {{-- ═══ the day ═══ --}}
        <table class="w-full border-collapse">
            <thead>
                <tr class="border-b-2 border-navy-900 bg-page/60 text-left text-3xs font-bold uppercase tracking-wide text-navy-600">
                    <th class="py-2 pl-8 pr-2 w-10">Car</th>
                    <th class="py-2 pr-3 w-16">Time</th>
                    <th class="py-2 pr-3 w-20">Leg</th>
                    <th class="py-2 pr-3">Route</th>
                    <th class="py-2 pr-3">Passengers</th>
                    <th class="py-2 pr-3 w-32">Vehicle</th>
                    <th class="py-2 pr-3 w-36">Driver</th>
                    <th class="py-2 pr-8 w-24">Status</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($runs as $m)
                    @php
                        $names = $m->manifest->take(4)->pluck('name')->implode(', ');
                        $more = max(0, $m->manifest->count() - 4);
                    @endphp
                    <tr class="border-b border-line {{ $m->status === 'cancelled' ? 'opacity-45' : '' }}">
                        <td class="py-2 pl-8 pr-2">
                            <span class="flex h-7 w-7 items-center justify-center rounded-lg text-3xs font-black {{ $m->isPriority() ? 'bg-gold-500 text-navy-950' : 'bg-navy-900 text-white' }}">
                                {{ $m->ref_no }}
                            </span>
                        </td>
                        <td class="py-2 pr-3">
                            <span class="text-sm font-black text-navy-900">{{ $m->effectiveDeparture()?->format('H:i') ?? 'TBC' }}</span>
                            @if ($m->delayed_to)
                                <span class="block text-3xs font-bold text-amber-700 line-through">{{ $m->depart_at?->format('H:i') }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-3">
                            @php $legClass = ['arrival' => 'bg-emerald-100 text-emerald-800', 'departure' => 'bg-sky-100 text-sky-800'][$m->leg] ?? 'bg-navy-100 text-navy-700'; @endphp
                            <span class="rounded px-1.5 py-0.5 text-3xs font-bold uppercase {{ $legClass }}">{{ $m->legLabel() }}</span>
                        </td>
                        <td class="py-2 pr-3">
                            <span class="text-xs font-bold text-navy-900">{{ $m->pickup_from ?: '—' }} → {{ $m->drop_to ?: '—' }}</span>
                            @if ($m->flight_no)
                                <span class="block text-3xs text-muted">Flight {{ $m->flight_no }}</span>
                            @endif
                        </td>
                        <td class="py-2 pr-3">
                            <span class="text-xs text-navy-800">{{ $names ?: ($m->paxCount() ? $m->paxCount().' expected' : '—') }}</span>
                            @if ($more)<span class="text-3xs font-semibold text-muted"> +{{ $more }} more</span>@endif
                        </td>
                        <td class="py-2 pr-3 text-xs text-navy-700">
                            {{ $m->vehicle?->label() ?? $m->vehicleType?->name ?? '—' }}
                            @if ($m->vehicleCount() > 1)<span class="font-bold"> ×{{ $m->vehicleCount() }}</span>@endif
                        </td>
                        <td class="py-2 pr-3">
                            @if ($m->driver)
                                <span class="block text-xs font-bold text-navy-900">{{ $m->driver->name }}</span>
                                @if ($m->contactNumber())<span class="text-3xs text-muted">{{ $m->contactNumber() }}</span>@endif
                            @else
                                <span class="text-3xs font-bold uppercase text-amber-600">Unassigned</span>
                            @endif
                        </td>
                        <td class="py-2 pr-8">
                            <span class="rounded px-1.5 py-0.5 text-3xs font-bold uppercase {{ $m->statusClass() }}">{{ $m->statusLabel() }}</span>
                            @if ($m->issue_note)
                                <span class="block text-3xs font-semibold text-red-700">{{ \Illuminate\Support\Str::limit($m->issue_note, 40) }}</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <div class="mt-4 flex items-center justify-between border-t border-line px-8 py-3 text-3xs text-muted">
            <span>
                {{ $event->name }} · Daily movement schedule ·
                {{ $date === 'unscheduled' ? 'Unscheduled' : \Carbon\Carbon::parse($date)->format('D j M Y') }}
            </span>
            <span>
                Event control {{ $control['phone'] ?: '—' }}
                @if ($control['name']) · {{ $control['name'] }}@endif
                · Generated {{ now()->format('d M Y H:i') }}
            </span>
        </div>
    </div>
@empty
    <div class="p-16 text-center">
        <p class="text-lg font-bold text-navy-900">Nothing to schedule.</p>
        <p class="mt-1 text-sm text-muted">No movements match the current selection.</p>
    </div>
@endforelse

</body>
</html>
