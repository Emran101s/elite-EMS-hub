<!doctype html>
{{--
    The driver's document. One card per movement with the names riding in it,
    preceded by the fleet count the supplier needs to quote and dispatch.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Transport Manifest</title>
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .avoid-break { break-inside: avoid; }
        thead { display: table-header-group; }
    </style>
</head>
<body class="bg-white text-navy-900">

    {{-- ═══ header ═══ --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-10 py-7 text-white">
        <div class="pointer-events-none absolute -right-10 -top-16 h-52 w-52 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.26),transparent_70%)]"></div>
        <div class="relative flex items-end justify-between gap-8">
            <div class="min-w-0">
                <p class="text-3xs font-bold uppercase tracking-[0.28em] text-gold-300">Transport Manifest</p>
                <h1 class="mt-1 text-3xl font-black leading-tight text-white" style="font-family:'Spectral',Georgia,serif">{{ $event->name }}</h1>
                {{-- Says on its face which slice this is, so a filtered export is
                     never mistaken for the whole plan once it's printed. --}}
                @if (($selection ?? 'All movements') !== 'All movements')
                    <p class="mt-1.5 inline-block rounded-full bg-gold-400/15 px-2.5 py-1 text-3xs font-bold uppercase tracking-[0.16em] text-gold-200">{{ $selection }}</p>
                @endif
                <p class="mt-1 text-xs text-white/55">
                    {{ $movements->count() }} {{ \Illuminate\Support\Str::plural('movement', $movements->count()) }}
                    · {{ $movements->sum(fn ($m) => $m->paxCount()) }} passengers
                    · In departure order · Generated {{ now()->format('d M Y') }}
                </p>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Vehicles</p>
                <p class="text-3xl font-black leading-none text-white">{{ $fleet->sum('vehicles') }}</p>
            </div>
        </div>
    </div>

    {{-- ═══ fleet count — what to order ═══ --}}
    @if ($fleet->isNotEmpty())
        <div class="avoid-break border-b border-line px-10 py-5">
            <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">Vehicles required</p>
            <table class="mt-2 w-full border-collapse">
                <thead>
                    <tr class="border-b border-line text-left text-3xs font-bold uppercase tracking-wide text-navy-500">
                        <th class="py-1.5 pr-3">Vehicle</th>
                        <th class="py-1.5 pr-3 text-center">Capacity</th>
                        <th class="py-1.5 pr-3 text-center">Runs</th>
                        <th class="py-1.5 pr-3 text-center">Passengers</th>
                        <th class="py-1.5 text-right">Vehicles</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($fleet as $f)
                        <tr class="border-b border-line last:border-0">
                            <td class="py-1.5 pr-3 text-xs font-semibold text-navy-900">{{ $f['name'] }}</td>
                            <td class="py-1.5 pr-3 text-center text-xs text-navy-700">max {{ $f['capacity'] }}</td>
                            <td class="py-1.5 pr-3 text-center text-xs text-navy-700">{{ $f['runs'] }}</td>
                            <td class="py-1.5 pr-3 text-center text-xs text-navy-700">{{ $f['pax'] }}</td>
                            <td class="py-1.5 text-right text-sm font-black text-navy-900">×{{ $f['vehicles'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- ═══ movements, day by day ═══ --}}
    <div class="px-10 py-6">
        @forelse ($days as $day => $group)
            <div class="mb-2 mt-4 first:mt-0">
                <p class="border-b-2 border-navy-900 pb-1 text-sm font-black uppercase tracking-wide text-navy-900">
                    {{ $day === 'unscheduled' ? 'Not yet scheduled' : \Carbon\Carbon::parse($day)->format('l, j F Y') }}
                    <span class="ml-2 text-3xs font-bold text-muted">
                        {{ $group->count() }} {{ \Illuminate\Support\Str::plural('movement', $group->count()) }}
                        · {{ $group->sum(fn ($m) => $m->paxCount()) }} pax
                    </span>
                </p>
            </div>

            @foreach ($group as $m)
                <div class="avoid-break mb-3 rounded-xl border border-line">
                    {{-- movement header --}}
                    <div class="flex items-start justify-between gap-4 border-b border-line bg-page/50 px-4 py-2.5">
                        <div class="min-w-0">
                            @php $legClass = ['arrival' => 'bg-emerald-100 text-emerald-800', 'departure' => 'bg-sky-100 text-sky-800'][$m->leg] ?? 'bg-navy-100 text-navy-700'; @endphp
                            <p class="text-sm font-bold text-navy-900">
                                <span class="mr-1 rounded bg-navy-900 px-1.5 py-0.5 align-middle text-3xs font-black text-white">CAR {{ $m->ref_no }}</span>
                                {{ $m->depart_at?->format('H:i') ?? 'TBC' }}
                                <span class="mx-1 rounded px-1.5 py-0.5 align-middle text-3xs font-bold uppercase tracking-wide {{ $legClass }}">{{ $m->legLabel() }}</span>
                                {{ $m->serviceType?->name ?? $m->route }}
                            </p>
                            <p class="mt-0.5 text-3xs text-muted">
                                @if ($m->pickup_from || $m->drop_to){{ $m->pickup_from ?: '—' }} → {{ $m->drop_to ?: '—' }}@endif
                                @if ($m->provider) · {{ $m->provider }}@endif
                            </p>
                            <div class="mt-1.5 flex flex-wrap gap-x-5 gap-y-1">
                                @foreach (array_filter([
                                    'Pick-up' => $m->depart_at?->format('D, d M · H:i'),
                                    'Flight' => $m->flight_no,
                                    'Lands' => $m->arrive_at?->format('d M · H:i'),
                                    'Driver' => $m->driver_contact,
                                ]) as $label => $value)
                                    <span class="text-3xs">
                                        <span class="font-bold uppercase tracking-[0.12em] text-muted">{{ $label }}</span>
                                        <span class="ml-1 font-semibold text-navy-800">{{ $value }}</span>
                                    </span>
                                @endforeach
                            </div>
                        </div>
                        <div class="shrink-0 text-right">
                            <p class="text-xs font-bold text-navy-900">
                                {{ $m->vehicleType?->name ?? '—' }}@if ($m->vehicleCount() > 1) ×{{ $m->vehicleCount() }}@endif
                            </p>
                            <p class="text-3xs text-muted">{{ $m->paxCount() }} / {{ $m->seats() ?: '—' }} seats</p>
                        </div>
                    </div>

                    {{-- the names --}}
                    @if ($m->manifest->isNotEmpty())
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-line text-left text-3xs font-bold uppercase tracking-wide text-muted">
                                    <th class="w-10 py-1.5 pl-4 text-center">#</th>
                                    <th class="py-1.5 pr-3">Passenger</th>
                                    <th class="py-1.5 pr-3">Airline</th>
                                    <th class="py-1.5 pr-3">Flight</th>
                                    <th class="py-1.5 pr-3">Arrival</th>
                                    <th class="py-1.5 pr-3">Phone</th>
                                    <th class="py-1.5 pr-4">Pick-up point</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($m->manifestByVehicle() as $vehicleNo => $riders)
                                    {{-- Several vehicles on one run: each driver gets their own list. --}}
                                    @if ($m->vehicles > 1)
                                        <tr class="bg-navy-50/70">
                                            <td colspan="7" class="py-1 pl-4 text-3xs font-black uppercase tracking-wider text-navy-700">
                                                Car {{ $m->vehicleLabel((int) $vehicleNo) }} ·
                                                {{ $m->vehicleType?->name ?? 'Vehicle' }} {{ $vehicleNo }} of {{ $m->vehicles }}
                                                <span class="font-semibold text-muted">· {{ $riders->count() }} {{ \Illuminate\Support\Str::plural('passenger', $riders->count()) }}@if ($m->seatsPerVehicle()) of {{ $m->seatsPerVehicle() }} seats @endif</span>
                                            </td>
                                        </tr>
                                    @endif
                                    @foreach ($riders->values() as $i => $p)
                                    <tr class="border-b border-line last:border-0">
                                        <td class="py-1.5 pl-4 text-center text-3xs font-bold text-navy-300">{{ $i + 1 }}</td>
                                        <td class="py-1.5 pr-3 text-xs font-semibold text-navy-900">{{ $p->name }}</td>
                                        <td class="py-1.5 pr-3 text-xs text-navy-700">{{ $p->airline ?: '—' }}</td>
                                        <td class="py-1.5 pr-3 text-xs font-semibold text-navy-800">{{ $p->flight_no ?: '—' }}</td>
                                        <td class="py-1.5 pr-3 text-xs text-navy-700">
                                            {{ $p->arrival_on?->format('j M') ?? '—' }}{{ $p->arrival_time ? ' · '.$p->arrival_time : '' }}
                                        </td>
                                        <td class="py-1.5 pr-3 text-xs text-navy-700">{{ $p->phone ?: '—' }}</td>
                                        <td class="py-1.5 pr-4 text-xs text-navy-700">{{ $p->pickup_point ?: '—' }}</td>
                                    </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="px-4 py-3 text-xs italic text-navy-300">
                            No names on this manifest yet{{ $m->passengers ? " — {$m->passengers} passengers expected" : '' }}.
                        </p>
                    @endif

                    @if ($m->notes)
                        <p class="border-t border-line px-4 py-2 text-3xs text-navy-600">{{ $m->notes }}</p>
                    @endif
                </div>
            @endforeach
        @empty
            <p class="py-16 text-center text-sm text-muted">No movements planned yet.</p>
        @endforelse

        <p class="mt-6 border-t border-line pt-3 text-3xs text-muted">
            {{ $event->name }} · Transport manifest · Generated {{ now()->format('d M Y H:i') }}
        </p>
    </div>
</body>
</html>
