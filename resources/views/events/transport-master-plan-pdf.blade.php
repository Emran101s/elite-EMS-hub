<!doctype html>
{{--
    The client's document, for sign-off. A proposal, not a manifest — summaries,
    a cover, and an approval block. Cost is shown as a total only; margin and
    supplier rates never appear here.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Transportation Plan</title>
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .page { page-break-after: always; }
        .page:last-child { page-break-after: auto; }
        .avoid-break { break-inside: avoid; }
        thead { display: table-header-group; }
    </style>
</head>
<body class="bg-white text-navy-900">

    {{-- ═══════════ COVER ═══════════ --}}
    <div class="page relative flex min-h-screen flex-col justify-between overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-16 py-20 text-white">
        <div class="pointer-events-none absolute -right-24 -top-32 h-[36rem] w-[36rem] rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.22),transparent_70%)]"></div>

        <div class="relative">
            @if ($company?->name)
                <p class="text-sm font-bold uppercase tracking-[0.3em] text-gold-300">{{ $company->name }}</p>
            @endif
            <p class="mt-2 text-3xs font-bold uppercase tracking-[0.28em] text-white/45">Transportation Plan</p>
        </div>

        <div class="relative">
            <h1 class="text-6xl font-black leading-[1.02] text-white" style="font-family:'Spectral',Georgia,serif">
                {{ $event->name }}
            </h1>
            <p class="mt-5 text-lg text-white/70">
                {{ $event->starts_at?->format('j') }}–{{ $event->ends_at?->format('j M Y') }}
                @if ($event->city) · {{ $event->city }}@endif
            </p>
            <div class="mt-8 flex flex-wrap gap-x-12 gap-y-4">
                <div>
                    <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Movements</p>
                    <p class="text-2xl font-black">{{ $summary['movements'] }}</p>
                </div>
                <div>
                    <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Vehicles</p>
                    <p class="text-2xl font-black">{{ $summary['vehicles'] }}</p>
                </div>
                <div>
                    <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Guests moved</p>
                    <p class="text-2xl font-black">{{ $summary['guests'] }}</p>
                </div>
                <div>
                    <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Over</p>
                    <p class="text-2xl font-black">{{ $summary['days'] }} {{ \Illuminate\Support\Str::plural('day', $summary['days']) }}</p>
                </div>
            </div>
        </div>

        <div class="relative flex items-end justify-between border-t border-white/15 pt-6">
            <div>
                <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Prepared for</p>
                <p class="text-sm font-semibold">{{ $event->client?->name ?? 'Client approval' }}</p>
            </div>
            <div class="text-right">
                <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Issued</p>
                <p class="text-sm font-semibold">{{ now()->format('j M Y') }} · v1.0</p>
            </div>
        </div>
    </div>

    {{-- ═══════════ THE PLAN ═══════════ --}}
    <div class="px-14 py-12">

        {{-- scope --}}
        <div class=" flex items-baseline gap-3 border-b border-navy-900 pb-1.5"><span class="text-lg font-black text-gold-500">01</span><h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Scope</h2></div>
        <p class="mt-3 max-w-[62ch] text-sm leading-relaxed text-navy-700">
            This plan covers all ground transportation for {{ $event->name }} across
            {{ $summary['days'] }} {{ \Illuminate\Support\Str::plural('day', $summary['days']) }}:
            <strong>{{ $summary['arrivals'] }} arrival</strong> and
            <strong>{{ $summary['departures'] }} departure</strong> transfers,
            @if ($summary['vip'])including <strong>{{ $summary['vip'] }} priority / VIP {{ \Illuminate\Support\Str::plural('movement', $summary['vip']) }}</strong>, @endif
            operated with {{ $summary['vehicles'] }} {{ \Illuminate\Support\Str::plural('vehicle', $summary['vehicles']) }}
            @if ($summary['drivers']) and {{ $summary['drivers'] }} {{ \Illuminate\Support\Str::plural('driver', $summary['drivers']) }}@endif.
        </p>

        {{-- movement summary by leg --}}
        <div class="mt-10 flex items-baseline gap-3 border-b border-navy-900 pb-1.5"><span class="text-lg font-black text-gold-500">02</span><h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Movement summary</h2></div>
        <div class="mt-3 grid grid-cols-3 gap-4">
            @foreach ($byLeg as $leg)
                <div class="avoid-break rounded-xl border border-line px-4 py-3">
                    <p class="text-3xs font-bold uppercase tracking-[0.14em] text-muted">{{ $leg['label'] }}</p>
                    <p class="mt-1 text-2xl font-black text-navy-900">{{ $leg['runs'] }}</p>
                    <p class="text-3xs text-muted">{{ $leg['pax'] }} passengers</p>
                </div>
            @endforeach
        </div>

        {{-- routes --}}
        <div class="mt-10 flex items-baseline gap-3 border-b border-navy-900 pb-1.5"><span class="text-lg font-black text-gold-500">03</span><h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Routes</h2></div>
        <table class="mt-3 w-full border-collapse">
            <thead>
                <tr class="border-b border-line text-left text-3xs font-bold uppercase tracking-wide text-navy-500">
                    <th class="py-2 pr-3">Route</th>
                    <th class="py-2 pr-3">Legs</th>
                    <th class="py-2 text-right">Trips</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($routes as $r)
                    <tr class="border-b border-line last:border-0">
                        <td class="py-2 pr-3 text-sm font-semibold text-navy-900">{{ $r['route'] }}</td>
                        <td class="py-2 pr-3 text-xs text-navy-600">{{ $r['legs'] }}</td>
                        <td class="py-2 text-right text-sm font-bold text-navy-900">{{ $r['runs'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        {{-- fleet --}}
        <div class="mt-10 flex items-baseline gap-3 border-b border-navy-900 pb-1.5"><span class="text-lg font-black text-gold-500">04</span><h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Vehicles</h2></div>
        <table class="mt-3 w-full border-collapse">
            <thead>
                <tr class="border-b border-line text-left text-3xs font-bold uppercase tracking-wide text-navy-500">
                    <th class="py-2 pr-3">Vehicle</th>
                    <th class="py-2 pr-3 text-center">Capacity</th>
                    <th class="py-2 pr-3 text-center">Trips</th>
                    <th class="py-2 text-right">Vehicles</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($fleet as $f)
                    <tr class="border-b border-line last:border-0">
                        <td class="py-2 pr-3 text-sm font-semibold text-navy-900">{{ $f['name'] }}</td>
                        <td class="py-2 pr-3 text-center text-xs text-navy-700">max {{ $f['capacity'] }}</td>
                        <td class="py-2 pr-3 text-center text-xs text-navy-700">{{ $f['runs'] }}</td>
                        <td class="py-2 text-right text-sm font-black text-navy-900">{{ $f['vehicles'] }}</td>
                    </tr>
                @endforeach
                <tr class="border-t-2 border-navy-900">
                    <td class="py-2 pr-3 text-sm font-black text-navy-900" colspan="3">Total fleet</td>
                    <td class="py-2 text-right text-sm font-black text-navy-900">{{ $summary['vehicles'] }}</td>
                </tr>
            </tbody>
        </table>

        {{-- cost — a total only, never a breakdown of margin --}}
        @if ($costCents > 0)
            <div class="mt-10 flex items-baseline gap-3 border-b border-navy-900 pb-1.5"><span class="text-lg font-black text-gold-500">05</span><h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Investment</h2></div>
            <div class="mt-3 flex items-center justify-between rounded-xl bg-navy-900 px-6 py-4 text-white">
                <p class="text-sm font-semibold text-white/70">Estimated transportation cost</p>
                <p class="text-2xl font-black">{{ $event->money($costCents) }}</p>
            </div>
            <p class="mt-2 text-3xs text-muted">
                Indicative, based on the planned movements above. Final billing follows the signed agreement.
            </p>
        @endif

        {{-- assumptions --}}
        <div class="mt-10 flex items-baseline gap-3 border-b border-navy-900 pb-1.5"><span class="text-lg font-black text-gold-500">{{ $costCents > 0 ? '06' : '05' }}</span><h2 class="text-lg font-black uppercase tracking-wide text-navy-900">Notes & assumptions</h2></div>
        <ul class="mt-3 max-w-[62ch] list-disc space-y-1.5 pl-5 text-sm text-navy-700">
            <li>Timings follow confirmed flight schedules; airport pickups adjust automatically to actual landing times.</li>
            <li>Vehicle counts assume the passenger numbers known at issue and scale with confirmed attendance.</li>
            <li>Priority and VIP movements are handled by dedicated vehicles with meet-and-greet where noted.</li>
            <li>Waiting time beyond 60 minutes at the airport, and movements added inside 24 hours, may attract a surcharge.</li>
        </ul>

        {{-- approval --}}
        <div class="avoid-break mt-12 rounded-2xl border border-line bg-page/40 px-8 py-7">
            <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">Approval</p>
            <p class="mt-1 max-w-[60ch] text-sm text-navy-700">
                Signing below confirms this transportation plan is approved to proceed.
            </p>
            <div class="mt-8 grid grid-cols-2 gap-10">
                @foreach (['For the client' => $event->client?->name, 'For '.($company?->name ?? 'the organiser') => $control['name']] as $role => $who)
                    <div>
                        <div class="h-10 border-b border-navy-400"></div>
                        <p class="mt-2 text-xs font-bold text-navy-900">{{ $role }}</p>
                        <p class="text-3xs text-muted">{{ $who ?: 'Name & signature' }} · Date</p>
                    </div>
                @endforeach
            </div>
        </div>

        <div class="mt-8 flex items-center justify-between border-t border-line pt-4 text-3xs text-muted">
            <span>{{ $company?->name ?? $event->name }} · Transportation plan · v1.0</span>
            <span>
                @if ($company?->phone){{ $company->phone }} · @endif
                Generated {{ now()->format('d M Y') }}
            </span>
        </div>
    </div>

</body>
</html>
