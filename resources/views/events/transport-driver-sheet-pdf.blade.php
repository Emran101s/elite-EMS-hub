<!doctype html>
{{--
    One page per driver per day. Deliberately the largest type in the PDF suite:
    this is read in a moving car, sometimes at night, by someone who wants the
    phone number and the pickup time and nothing else.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Driver Trip Sheet</title>
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .sheet { page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }
        .avoid-break { break-inside: avoid; }
    </style>
</head>
<body class="bg-white text-navy-900">

@forelse ($sheets as $sheet)
    @php $driver = $sheet['driver']; @endphp
    <div class="sheet flex min-h-screen flex-col">

        {{-- ═══ masthead ═══ --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-10 py-6 text-white">
            <div class="pointer-events-none absolute -right-10 -top-16 h-52 w-52 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.26),transparent_70%)]"></div>
            <div class="relative flex items-end justify-between gap-6">
                <div class="min-w-0">
                    <p class="text-3xs font-bold uppercase tracking-[0.28em] text-gold-300">Driver Trip Sheet</p>
                    <h1 class="mt-1 text-4xl font-black leading-none text-white">{{ $driver?->name ?? 'Unassigned' }}</h1>
                    <p class="mt-2 text-sm text-white/70">
                        {{ $event->name }}
                        @if ($driver?->supplier) · {{ $driver->supplier->name }}@endif
                    </p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">
                        {{ $sheet['date']?->format('l') ?? 'Unscheduled' }}
                    </p>
                    <p class="text-2xl font-black leading-tight text-white">{{ $sheet['date']?->format('j M Y') ?? '—' }}</p>
                    <p class="mt-1 text-3xs font-bold uppercase tracking-wide text-gold-300">
                        {{ $sheet['runs']->count() }} {{ \Illuminate\Support\Str::plural('trip', $sheet['runs']->count()) }}
                        @if ($sheet['duty']) · {{ \App\Models\TransportDriver::readableMinutes($sheet['duty']) }} on duty @endif
                    </p>
                </div>
            </div>
        </div>

        {{-- ═══ the driver's own details, big ═══ --}}
        <div class="flex items-stretch gap-0 border-b-2 border-navy-900">
            <div class="flex-1 px-10 py-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">Your number</p>
                <p class="text-2xl font-black leading-tight text-navy-900">{{ $driver?->phone ?: '—' }}</p>
            </div>
            @if ($driver?->licence_no)
                <div class="border-l border-line px-8 py-4">
                    <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">Licence</p>
                    <p class="text-lg font-bold text-navy-900">{{ $driver->licence_no }}</p>
                </div>
            @endif
            {{-- Bordered on purpose: findable without reading anything else on the page. --}}
            <div class="border-l-2 border-red-300 bg-red-50 px-8 py-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-red-700">Emergency · Event control</p>
                {{-- A dash here reads as "none needed". Say the gap out loud so
                     whoever prints this notices before handing it to a driver. --}}
                @if ($control['phone'])
                    <p class="text-2xl font-black leading-tight text-red-800">{{ $control['phone'] }}</p>
                @else
                    <p class="text-base font-black leading-tight text-red-800">No number set</p>
                    <p class="text-3xs font-semibold text-red-600">Add a company phone in Settings</p>
                @endif
                @if ($control['name'])
                    <p class="text-3xs font-semibold text-red-700">{{ $control['name'] }}</p>
                @endif
            </div>
        </div>

        {{-- ═══ the runs ═══ --}}
        <div class="flex-1 px-10 py-5">
            @foreach ($sheet['runs'] as $m)
                @php $vehicleName = $m->vehicle?->label() ?? $m->vehicleType?->name; @endphp
                <div class="avoid-break mb-4 rounded-2xl border-2 border-navy-200 last:mb-0">

                    {{-- when & which car — the two things scanned first --}}
                    <div class="flex items-center gap-5 rounded-t-xl bg-navy-900 px-5 py-3 text-white">
                        <span class="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl {{ $m->isPriority() ? 'bg-gold-500 text-navy-950' : 'bg-white/15 text-white' }} text-xl font-black">
                            {{ $m->ref_no }}
                        </span>
                        <div>
                            <p class="text-3xs font-bold uppercase tracking-[0.2em] text-gold-300">Pick up at</p>
                            <p class="text-3xl font-black leading-none">{{ $m->effectiveDeparture()?->format('H:i') ?? 'TBC' }}</p>
                        </div>
                        @if ($m->delayed_to)
                            <span class="rounded-lg bg-amber-400 px-2.5 py-1 text-3xs font-black uppercase tracking-wide text-navy-950">
                                Delayed from {{ $m->depart_at?->format('H:i') }}
                            </span>
                        @endif
                        <div class="ml-auto text-right">
                            <p class="text-sm font-bold">{{ $vehicleName ?: 'Vehicle TBC' }}</p>
                            <p class="text-3xs uppercase tracking-wide text-white/50">
                                {{ $m->legLabel() }}@if ($m->flight_no) · Flight {{ $m->flight_no }}@endif
                            </p>
                        </div>
                    </div>

                    {{-- from → to, set large --}}
                    <div class="grid grid-cols-2 gap-0 border-b border-line">
                        <div class="border-r border-line px-5 py-3">
                            <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">From</p>
                            <p class="text-lg font-bold leading-tight text-navy-900">{{ $m->pickup_from ?: '—' }}</p>
                        </div>
                        <div class="px-5 py-3">
                            <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">To</p>
                            <p class="text-lg font-bold leading-tight text-navy-900">{{ $m->drop_to ?: '—' }}</p>
                        </div>
                    </div>

                    {{-- who is riding --}}
                    @php $riders = $m->manifestByVehicle(); @endphp
                    @if ($m->manifest->isNotEmpty())
                        <table class="w-full border-collapse">
                            <thead>
                                <tr class="border-b border-line text-left text-3xs font-bold uppercase tracking-wide text-navy-500">
                                    <th class="py-2 pl-5 pr-2 w-8"></th>
                                    <th class="py-2 pr-3">Passenger</th>
                                    <th class="py-2 pr-3">Phone</th>
                                    <th class="py-2 pr-5">Notes</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($riders as $vehicleNo => $group)
                                    @if ($m->vehicleCount() > 1)
                                        <tr class="bg-navy-50">
                                            <td colspan="4" class="py-1.5 pl-5 text-3xs font-black uppercase tracking-wider text-navy-700">
                                                Car {{ $m->vehicleLabel((int) $vehicleNo) }} · vehicle {{ $vehicleNo }} of {{ $m->vehicles }}
                                            </td>
                                        </tr>
                                    @endif
                                    @foreach ($group->values() as $i => $p)
                                        <tr class="border-b border-line last:border-0">
                                            <td class="py-2 pl-5 pr-2 text-center text-3xs font-bold text-navy-300">{{ $i + 1 }}</td>
                                            <td class="py-2 pr-3">
                                                <span class="text-sm font-bold text-navy-900">{{ $p->name }}</span>
                                                @if ($p->isPriority())
                                                    <span class="ml-1 rounded bg-gold-100 px-1.5 py-0.5 text-3xs font-black uppercase text-gold-800">{{ $p->categoryLabel() }}</span>
                                                @endif
                                            </td>
                                            <td class="py-2 pr-3 text-base font-bold text-navy-800">{{ $p->phone ?: '—' }}</td>
                                            <td class="py-2 pr-5 text-3xs text-muted">
                                                {{ collect([$p->luggage_note, $p->notes])->filter()->implode(' · ') ?: '—' }}
                                            </td>
                                        </tr>
                                    @endforeach
                                @endforeach
                            </tbody>
                        </table>
                    @else
                        <p class="px-5 py-3 text-xs text-muted">
                            No names on this run yet — {{ $m->paxCount() ?: 0 }} expected.
                        </p>
                    @endif

                    @if ($m->notes)
                        <p class="border-t border-line bg-page/50 px-5 py-2.5 text-xs font-semibold text-navy-800">
                            {{ $m->notes }}
                        </p>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="border-t border-line px-10 py-3 text-3xs text-muted">
            {{ $event->name }} · Driver trip sheet · {{ $driver?->name }} ·
            Generated {{ now()->format('j M Y · H:i') }} ·
            Times are local. Call event control if anything changes.
        </div>
    </div>
@empty
    <div class="p-16 text-center">
        <p class="text-lg font-bold text-navy-900">No trips with a named driver.</p>
        <p class="mt-1 text-sm text-muted">
            Assign drivers to movements and each one gets their own sheet here.
        </p>
    </div>
@endforelse

</body>
</html>
