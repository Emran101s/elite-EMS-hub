<!doctype html>
{{--
    Sent to the hotel. Deliberately carries no rate, no block total, no cost of
    any kind — if you add money to this page you have broken its purpose.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Rooming List — {{ $block->hotel }}</title>
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        thead { display: table-header-group; }
        tr { break-inside: avoid; }
    </style>
</head>
<body class="bg-white text-navy-900">

    {{-- ═══ header ═══ --}}
    <div class="relative overflow-hidden bg-gradient-to-br from-navy-900 to-[#061225] px-10 py-7 text-white">
        <div class="pointer-events-none absolute -right-10 -top-16 h-52 w-52 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.26),transparent_70%)]"></div>
        <div class="relative flex items-end justify-between gap-8">
            <div class="min-w-0">
                <p class="text-3xs font-bold uppercase tracking-[0.28em] text-gold-300">Rooming List</p>
                <h1 class="mt-1 text-3xl font-black leading-tight text-white" style="font-family:'Spectral',Georgia,serif">{{ $block->hotel }}</h1>
                <p class="mt-1 text-xs text-white/55">
                    {{ $event->name }}
                    @if ($block->check_in)
                        · {{ $block->check_in->format('j M Y') }} – {{ $block->check_out?->format('j M Y') ?? '—' }} <span class="text-white/40">(default dates)</span>
                    @endif
                </p>
            </div>
            <div class="shrink-0 text-right">
                <p class="text-3xs font-bold uppercase tracking-[0.2em] text-white/40">Rooms</p>
                <p class="text-3xl font-black leading-none text-white">{{ $block->filled() }}<span class="text-lg text-white/40"> / {{ $block->rooms_count }}</span></p>
                @if ($block->confirmation_number)
                    <p class="mt-1 text-3xs text-white/50">Confirmation #{{ $block->confirmation_number }}</p>
                @endif
            </div>
        </div>
    </div>

    {{-- ═══ block facts ═══ --}}
    <div class="flex flex-wrap gap-x-10 gap-y-2 border-b border-line px-10 py-4">
        @foreach ([
            'Room category' => $block->room_type ?: 'Standard',
            'Occupancy' => $block->occupancy ? (\App\Models\EventAccommodation::OCCUPANCIES[$block->occupancy] ?? '—') : '—',
            'Default check-in' => $block->check_in?->format('D, d M Y') ?? '—',
            'Default check-out' => $block->check_out?->format('D, d M Y') ?? '—',
            'Room-nights (total)' => $block->namedRoomNights() ?: '—',
            'Status' => $block->statusLabel(),
        ] as $label => $value)
            <div>
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">{{ $label }}</p>
                <p class="mt-0.5 text-sm font-semibold text-navy-900">{{ $value }}</p>
            </div>
        @endforeach
    </div>

    {{-- ═══ the list ═══ --}}
    <div class="px-10 py-6">
        @if ($rooms->isEmpty())
            <p class="py-16 text-center text-sm text-muted">No guests have been named in this block yet.</p>
        @else
            <table class="w-full border-collapse">
                <thead>
                    <tr class="border-b-2 border-navy-900 text-left text-3xs font-bold uppercase tracking-wide text-navy-600">
                        <th class="w-10 py-2 text-center">#</th>
                        <th class="py-2 pr-3">Guest name</th>
                        <th class="py-2 pr-3">Sharing with</th>
                        <th class="py-2 pr-3">Occupancy</th>
                        <th class="py-2 pr-3">Category</th>
                        <th class="py-2 pr-3">Check-in</th>
                        <th class="py-2 pr-3">Check-out</th>
                        <th class="py-2 pr-3 text-center">Nights</th>
                        <th class="py-2">Contact</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rooms as $i => $r)
                        <tr class="border-b border-line {{ $i % 2 ? 'bg-page/40' : '' }}">
                            <td class="py-2 text-center text-3xs font-bold text-navy-400">{{ $i + 1 }}</td>
                            <td class="py-2 pr-3 text-xs font-semibold text-navy-900">{{ $r->guest ?: '—' }}</td>
                            <td class="py-2 pr-3 text-xs text-navy-700">{{ $r->sharing_with ?: '—' }}</td>
                            <td class="py-2 pr-3 text-xs font-semibold text-navy-900">{{ $r->occupancyLabel() ?: '—' }}</td>
                            <td class="py-2 pr-3 text-xs text-navy-700">{{ $r->room_type ?: ($block->room_type ?: 'Standard') }}</td>
                            <td class="py-2 pr-3 text-xs text-navy-700">
                                {{ $r->check_in?->format('j M') ?? '—' }}{{ $r->arrival_time ? ' · '.$r->arrival_time : '' }}
                            </td>
                            <td class="py-2 pr-3 text-xs text-navy-700">
                                {{ $r->check_out?->format('j M') ?? '—' }}{{ $r->departure_time ? ' · '.$r->departure_time : '' }}
                            </td>
                            <td class="py-2 pr-3 text-center text-xs font-semibold text-navy-900">{{ $r->nights() ?: '—' }}</td>
                            <td class="py-2 text-3xs leading-tight text-navy-600">
                                {{ $r->guest_email ?: '' }}@if ($r->guest_email && $r->guest_phone)<br>@endif{{ $r->guest_phone ?: '' }}
                                @if (! $r->guest_email && ! $r->guest_phone)—@endif
                            </td>
                        </tr>
                    @endforeach

                    {{-- rooms held but not yet named — the hotel sees what's still coming --}}
                    @for ($n = $rooms->count(); $n < $block->rooms_count; $n++)
                        <tr class="border-b border-line {{ $n % 2 ? 'bg-page/40' : '' }}">
                            <td class="py-2 text-center text-3xs font-bold text-navy-300">{{ $n + 1 }}</td>
                            <td class="py-2 pr-3 text-xs italic text-navy-300" colspan="8">To be advised</td>
                        </tr>
                    @endfor
                </tbody>
            </table>
        @endif

        @if ($block->notes)
            <div class="mt-6 rounded-xl border border-line bg-page/40 p-4">
                <p class="text-3xs font-bold uppercase tracking-[0.16em] text-muted">Notes</p>
                <p class="mt-1 text-xs leading-relaxed text-navy-700">{{ $block->notes }}</p>
            </div>
        @endif

        <p class="mt-8 border-t border-line pt-3 text-3xs text-muted">
            {{ $event->name }} · Rooming list generated {{ now()->format('j M Y') }}
            @if ($block->cutoff_on) · Release date {{ $block->cutoff_on->format('j M Y') }} @endif
        </p>
    </div>
</body>
</html>
