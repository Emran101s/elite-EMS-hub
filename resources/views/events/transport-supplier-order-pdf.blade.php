<!doctype html>
{{--
    A commercial order to a transport vendor: here is what I need, please quote.
    One page per supplier. No client prices — this is a request they cost, not an
    invoice.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Transport Order</title>
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .order { page-break-after: always; }
        .order:last-child { page-break-after: auto; }
        .avoid-break { break-inside: avoid; }
        thead { display: table-header-group; }
    </style>
</head>
<body class="bg-white text-navy-900">

@forelse ($orders as $order)
    @php $supplier = $order['supplier']; @endphp
    <div class="order flex min-h-screen flex-col">

        {{-- ═══ masthead ═══ --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-12 py-7 text-white">
            <div class="pointer-events-none absolute -right-12 -top-20 h-60 w-60 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.26),transparent_70%)]"></div>
            <div class="relative flex items-start justify-between gap-8">
                <div>
                    <p class="text-3xs font-bold uppercase tracking-[0.28em] text-gold-300">Transport Order</p>
                    <h1 class="mt-1 text-3xl font-black leading-tight" style="font-family:'Spectral',Georgia,serif">
                        {{ $supplier?->name ?? 'Vehicles to be assigned' }}
                    </h1>
                    <p class="mt-1 text-sm text-white/65">{{ $event->name }} · {{ $event->city }}</p>
                </div>
                <div class="shrink-0 text-right">
                    <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Order date</p>
                    <p class="text-sm font-bold">{{ now()->format('j M Y') }}</p>
                    <p class="mt-2 text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Event dates</p>
                    <p class="text-sm font-bold">{{ $event->starts_at?->format('j') }}–{{ $event->ends_at?->format('j M Y') }}</p>
                </div>
            </div>
        </div>

        {{-- from / to --}}
        <div class="grid grid-cols-2 border-b-2 border-navy-900">
            <div class="border-r border-line px-12 py-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">From</p>
                <p class="text-sm font-bold text-navy-900">{{ $company?->name ?? 'Elite Business Hub' }}</p>
                <p class="text-3xs text-navy-600">
                    {{ collect([$control['name'], $company?->phone ?: $control['phone'], $company?->email])->filter()->implode(' · ') }}
                </p>
            </div>
            <div class="px-12 py-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">To</p>
                <p class="text-sm font-bold text-navy-900">{{ $supplier?->name ?? 'Supplier to be confirmed' }}</p>
                <p class="text-3xs text-navy-600">
                    {{ collect([$supplier?->phone, $supplier?->email])->filter()->implode(' · ') ?: 'Contact details on file' }}
                </p>
            </div>
        </div>

        <div class="flex-1 px-12 py-8">

            {{-- what we need --}}
            <div class="flex items-baseline gap-3 border-b border-navy-900 pb-1.5">
                <span class="text-lg font-black text-gold-700">01</span>
                <h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Vehicles required</h2>
            </div>
            <table class="mt-3 w-full border-collapse">
                <thead>
                    <tr class="border-b border-line text-left text-3xs font-bold uppercase tracking-wide text-navy-500">
                        <th class="py-2 pr-3">Date</th>
                        <th class="py-2 pr-3">Vehicle type</th>
                        <th class="py-2 pr-3 text-center">Capacity</th>
                        <th class="py-2 pr-3 text-center">Trips</th>
                        <th class="py-2 text-right">Vehicles</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order['requirements'] as $r)
                        <tr class="border-b border-line last:border-0">
                            <td class="py-2 pr-3 text-sm font-semibold text-navy-900">
                                {{ $r['date'] ? \Carbon\Carbon::parse($r['date'])->format('D j M') : 'TBC' }}
                            </td>
                            <td class="py-2 pr-3 text-sm text-navy-800">{{ $r['type'] }}</td>
                            <td class="py-2 pr-3 text-center text-xs text-navy-700">{{ $r['capacity'] ? 'max '.$r['capacity'] : '—' }}</td>
                            <td class="py-2 pr-3 text-center text-xs text-navy-700">{{ $r['trips'] }}</td>
                            <td class="py-2 text-right text-sm font-black text-navy-900">{{ $r['vehicles'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- the schedule they're quoting against --}}
            <div class="mt-8 flex items-baseline gap-3 border-b border-navy-900 pb-1.5">
                <span class="text-lg font-black text-gold-700">02</span>
                <h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Movement schedule</h2>
            </div>
            <table class="mt-3 w-full border-collapse">
                <thead>
                    <tr class="border-b border-line text-left text-3xs font-bold uppercase tracking-wide text-navy-500">
                        <th class="py-2 pr-3">When</th>
                        <th class="py-2 pr-3">Route</th>
                        <th class="py-2 pr-3">Vehicle</th>
                        <th class="py-2 text-center">Pax</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($order['runs'] as $m)
                        <tr class="border-b border-line last:border-0">
                            <td class="py-2 pr-3 text-xs font-bold text-navy-900 whitespace-nowrap">
                                {{ $m->depart_at?->format('D j M · H:i') ?? 'TBC' }}
                            </td>
                            <td class="py-2 pr-3 text-xs text-navy-800">{{ $m->pickup_from ?: '—' }} → {{ $m->drop_to ?: '—' }}</td>
                            <td class="py-2 pr-3 text-xs text-navy-700">{{ $m->vehicleType?->name ?? '—' }}</td>
                            <td class="py-2 text-center text-xs text-navy-700">{{ $m->paxCount() ?: '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            {{-- requirements & terms --}}
            <div class="mt-8 grid grid-cols-2 gap-8">
                <div>
                    <div class="flex items-baseline gap-3 border-b border-navy-900 pb-1.5">
                        <span class="text-lg font-black text-gold-700">03</span>
                        <h2 class="text-base font-black uppercase tracking-wide text-navy-900">Driver requirements</h2>
                    </div>
                    <ul class="mt-3 list-disc space-y-1.5 pl-5 text-xs text-navy-700">
                        <li>Professional drivers, uniformed, English-speaking where possible.</li>
                        <li>Vehicles clean, air-conditioned, and no older than the agreed standard.</li>
                        <li>Bottled water on board; child seats on request.</li>
                        <li>Drivers to carry this order and reach event control on arrival.</li>
                    </ul>
                </div>
                <div>
                    <div class="flex items-baseline gap-3 border-b border-navy-900 pb-1.5">
                        <span class="text-lg font-black text-gold-700">04</span>
                        <h2 class="text-base font-black uppercase tracking-wide text-navy-900">Terms</h2>
                    </div>
                    <ul class="mt-3 list-disc space-y-1.5 pl-5 text-xs text-navy-700">
                        <li>Please quote per the vehicles-required table above.</li>
                        <li>Rates to include fuel, driver hours, parking and tolls.</li>
                        <li>Confirm availability in writing before the event.</li>
                        <li>Changes inside 24 hours handled by direct arrangement.</li>
                    </ul>
                </div>
            </div>

            {{-- branding --}}
            <div class="mt-6 rounded-xl border border-gold-200 bg-gold-50/50 px-5 py-3">
                <p class="text-3xs font-bold uppercase tracking-[0.14em] text-gold-800">Branding</p>
                <p class="mt-0.5 text-xs text-navy-700">
                    Event signage / name boards to be displayed for VIP and speaker pickups as advised.
                    No third-party advertising on vehicles for the duration of the event.
                </p>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-line px-12 py-3 text-3xs text-muted">
            <span>{{ $company?->name ?? $event->name }} · Transport order · {{ $supplier?->name ?? 'unassigned' }}</span>
            <span>{{ $order['runs']->count() }} movements · {{ $order['days'] }} days · Generated {{ now()->format('d M Y') }}</span>
        </div>
    </div>
@empty
    <div class="p-16 text-center">
        <p class="text-lg font-bold text-navy-900">No movements to order.</p>
        <p class="mt-1 text-sm text-muted">Add movements, then generate a supplier order per vendor here.</p>
    </div>
@endforelse

</body>
</html>
