<!doctype html>
{{--
    One page per guest. The greeter holds this at an arrivals barrier while
    looking for one face — so it carries one name, large, and every movement
    that person makes across the event as a timeline.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — VIP Transfer Sheet</title>
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

@forelse ($people as $person)
    @php $g = $person['guest']; @endphp
    <div class="sheet flex min-h-screen flex-col">

        {{-- ═══ masthead: the name is the headline ═══ --}}
        <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-12 py-8 text-white">
            <div class="pointer-events-none absolute -right-16 -top-24 h-72 w-72 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.3),transparent_70%)]"></div>
            <div class="relative">
                <p class="text-3xs font-bold uppercase tracking-[0.3em] text-gold-300">
                    {{ $g->categoryLabel() }} · Transfer Sheet
                </p>
                <h1 class="mt-2 text-5xl font-black leading-none text-white" style="font-family:'Spectral',Georgia,serif">
                    {{ $g->name }}
                </h1>
                <p class="mt-3 text-sm text-white/65">
                    {{ $event->name }}
                    @if ($g->hotel) · Staying at {{ $g->hotel }}@endif
                </p>
            </div>
        </div>

        {{-- ═══ at a glance ═══ --}}
        <div class="flex items-stretch border-b-2 border-navy-900">
            <div class="flex-1 px-12 py-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">Their number</p>
                <p class="text-xl font-black leading-tight text-navy-900">{{ $g->phone ?: '—' }}</p>
            </div>
            <div class="flex-1 border-l border-line px-8 py-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">Movements</p>
                <p class="text-xl font-black leading-tight text-navy-900">{{ $person['legs']->count() }}</p>
            </div>
            <div class="border-l-2 border-gold-300 bg-gold-50 px-8 py-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-gold-800">Event control</p>
                @if ($control['phone'])
                    <p class="text-xl font-black leading-tight text-navy-900">{{ $control['phone'] }}</p>
                @else
                    <p class="text-sm font-black leading-tight text-navy-900">No number set</p>
                @endif
                @if ($control['name'])<p class="text-3xs font-semibold text-navy-600">{{ $control['name'] }}</p>@endif
            </div>
        </div>

        {{-- ═══ the timeline of their movements ═══ --}}
        <div class="flex-1 px-12 py-6">
            <p class="mb-4 text-3xs font-bold uppercase tracking-[0.2em] text-muted">Their itinerary</p>

            @foreach ($person['legs'] as $leg)
                @php $m = $leg->transport; @endphp
                <div class="avoid-break relative mb-5 border-l-2 border-navy-200 pl-6 last:mb-0">
                    {{-- timeline node --}}
                    <span class="absolute -left-[7px] top-1 h-3 w-3 rounded-full {{ $leg->direction === 'arrival' ? 'bg-emerald-500' : 'bg-sky-500' }}"></span>

                    <div class="flex flex-wrap items-baseline gap-2">
                        <span class="rounded px-2 py-0.5 text-3xs font-black uppercase tracking-wide {{ $leg->direction === 'arrival' ? 'bg-emerald-100 text-emerald-800' : 'bg-sky-100 text-sky-800' }}">
                            {{ ucfirst($leg->direction ?: 'movement') }}
                        </span>
                        <span class="text-lg font-black text-navy-900">
                            {{ $leg->arrival_on?->format('D j M') ?? 'Date TBC' }}
                        </span>
                        @if ($leg->flight_no)
                            <span class="text-sm font-bold text-navy-700">
                                {{ $leg->airline }} {{ $leg->flight_no }}
                                @if ($leg->arrival_time) · {{ substr($leg->arrival_time, 0, 5) }}@endif
                            </span>
                        @endif
                    </div>

                    <div class="mt-2 grid grid-cols-2 gap-4 rounded-xl border border-line bg-page/40 px-4 py-3">
                        <div>
                            <p class="text-3xs font-bold uppercase tracking-[0.14em] text-muted">Pick up</p>
                            <p class="text-base font-bold leading-tight text-navy-900">
                                {{ $leg->pickup_time ? substr($leg->pickup_time, 0, 5) : ($m?->effectiveDeparture()?->format('H:i') ?? 'TBC') }}
                                <span class="text-xs font-semibold text-navy-600">· {{ $leg->pickup_point ?: '—' }}</span>
                            </p>
                        </div>
                        <div>
                            <p class="text-3xs font-bold uppercase tracking-[0.14em] text-muted">Drop off</p>
                            <p class="text-base font-bold leading-tight text-navy-900">{{ $leg->drop_point ?: '—' }}</p>
                        </div>
                    </div>

                    {{-- the car, the plate, the driver — what the guest is told to look for --}}
                    @if ($m)
                        <div class="mt-2 flex flex-wrap items-center gap-x-6 gap-y-1 rounded-xl bg-navy-900 px-4 py-3 text-white">
                            <span class="flex items-center gap-2">
                                <span class="flex h-8 w-8 items-center justify-center rounded-lg bg-gold-500 text-sm font-black text-navy-950">{{ $m->ref_no }}</span>
                                <span class="text-3xs uppercase tracking-wide text-white/50">Car</span>
                            </span>
                            <span>
                                <span class="block text-3xs uppercase tracking-wide text-white/50">Vehicle</span>
                                <span class="text-sm font-bold">{{ $m->vehicle?->label() ?? $m->vehicleType?->name ?? 'TBC' }}</span>
                            </span>
                            <span>
                                <span class="block text-3xs uppercase tracking-wide text-white/50">Driver</span>
                                <span class="text-sm font-bold">{{ $m->driver?->name ?? 'To be assigned' }}</span>
                            </span>
                            @if ($m->contactNumber())
                                <span>
                                    <span class="block text-3xs uppercase tracking-wide text-white/50">Driver phone</span>
                                    <span class="text-sm font-bold">{{ $m->contactNumber() }}</span>
                                </span>
                            @endif
                        </div>
                    @else
                        <p class="mt-2 rounded-xl border border-dashed border-amber-300 bg-amber-50 px-4 py-2.5 text-xs font-semibold text-amber-800">
                            No vehicle assigned yet for this movement.
                        </p>
                    @endif

                    {{-- protocol is kept apart from logistics on purpose --}}
                    @if ($leg->protocol_note || $leg->luggage_note || $leg->notes)
                        <div class="mt-2 space-y-1">
                            @if ($leg->protocol_note)
                                <p class="rounded-lg border-l-2 border-gold-400 bg-gold-50 px-3 py-2 text-xs font-semibold text-navy-900">
                                    <span class="text-3xs font-black uppercase tracking-wide text-gold-700">Protocol</span><br>
                                    {{ $leg->protocol_note }}
                                </p>
                            @endif
                            @if ($leg->luggage_note)
                                <p class="text-xs text-navy-700"><span class="font-bold">Luggage:</span> {{ $leg->luggage_note }}</p>
                            @endif
                            @if ($leg->notes)
                                <p class="text-xs text-muted">{{ $leg->notes }}</p>
                            @endif
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <div class="border-t border-line px-12 py-3 text-3xs text-muted">
            {{ $event->name }} · {{ $scope }} transfer sheet · {{ $g->name }} ·
            Generated {{ now()->format('d M Y H:i') }}
        </div>
    </div>
@empty
    <div class="p-16 text-center">
        <p class="text-lg font-bold text-navy-900">No priority guests yet.</p>
        <p class="mt-1 text-sm text-muted">
            Set a guest's category to VIP or Speaker and they get their own sheet here.
        </p>
    </div>
@endforelse

</body>
</html>
