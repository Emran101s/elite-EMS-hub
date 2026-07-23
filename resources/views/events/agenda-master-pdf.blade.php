<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800|inter:400,500,600,700,800" rel="stylesheet">
    <style>{!! $css !!}</style>
    @php
        $navy = '#0B1F3A'; $navy2 = '#16294A'; $gold = '#B08D2B';
        $all = $event->agendaDays->flatMap->sessions;
        $unconfirmed = $all->reject(fn ($s) => $s->isSettled())->count();
        $min = fn ($t) => (int) substr((string) $t, 0, 2) * 60 + (int) substr((string) $t, 3, 2);
        $dur = function ($s) use ($min) {
            $d = max($min($s->ends_at) - $min($s->starts_at), 0);
            return $d >= 60 ? intdiv($d, 60).'h'.($d % 60 ? ' '.($d % 60).'m' : '') : $d.'m';
        };
    @endphp
    <style>
        @page { size: A4 portrait; margin: 13mm 12mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }
        html, body { background:#fff; margin:0; color:#26313F;
            font-family:'Spectral', Georgia, serif; }
        .sans { font-family:'Inter', system-ui, sans-serif; }

        .draft { display:flex; align-items:center; gap:10px; margin:0 0 14px; padding:9px 14px;
            border:1px solid #E3CE8F; border-left:3px solid {{ $gold }}; border-radius:9px; background:#F6EFDC;
            font-family:'Inter',sans-serif; font-size:11px; }
        .draft-tag { font-size:9px; font-weight:800; letter-spacing:.16em; text-transform:uppercase;
            color:#fff; background:{{ $gold }}; border-radius:4px; padding:2px 7px; }

        .day { margin-top:20px; break-inside:auto; }
        .daytag { display:flex; align-items:baseline; gap:12px; border-bottom:2px solid {{ $gold }};
            padding-bottom:7px; margin-bottom:0; break-after:avoid; break-inside:avoid; }
        .date { font-family:'Inter',sans-serif; font-size:10px; font-weight:800; letter-spacing:.13em;
            text-transform:uppercase; color:{{ $gold }}; }
        .dayname { font-size:17px; font-weight:700; color:{{ $navy }}; }
        .daymeta { font-family:'Inter',sans-serif; font-size:9px; color:#8A94A6; margin-left:auto; }

        table.t { width:100%; border-collapse:collapse; }
        table.t th { font-family:'Inter',sans-serif; font-size:7.5px; font-weight:700; letter-spacing:.12em;
            text-transform:uppercase; color:#8A94A6; text-align:left; padding:7px 8px; border-bottom:1px solid #E7E3D6; }
        table.t td { padding:7px 8px; border-bottom:1px solid #EFEDE4; font-size:11px; vertical-align:top;
            break-inside:avoid; }
        table.t tr { break-inside:avoid; }
        .tm { font-family:'Inter',sans-serif; font-weight:700; font-size:10px; font-variant-numeric:tabular-nums;
            color:{{ $navy }}; white-space:nowrap; }
        .du { font-family:'Inter',sans-serif; font-size:9px; color:#8A94A6; font-variant-numeric:tabular-nums; }
        .ti { font-weight:600; color:#26313F; line-height:1.3; }
        .sp { font-family:'Inter',sans-serif; font-size:8.5px; font-weight:600; color:{{ $navy }}; margin-top:2px; }
        .tk { font-family:'Inter',sans-serif; font-size:8px; letter-spacing:.08em; text-transform:uppercase;
            color:#8A94A6; margin-top:2px; }
        .rm { font-family:'Inter',sans-serif; font-size:9.5px; color:#4A5568; }
        .chip { font-family:'Inter',sans-serif; font-size:7.5px; font-weight:700; letter-spacing:.08em;
            text-transform:uppercase; border-radius:4px; padding:2px 6px; white-space:nowrap; }
        .ok { color:#2F6B4F; background:#DFF1E7; }
        .no { color:#8A6A12; background:#F6E6B8; }
        .crew td { background:#FAFAF7; }
        .crew .ti { color:#5B667A; font-weight:500; }

        .foot { margin-top:18px; border-top:1px solid #E7E3D6; padding-top:7px; font-family:'Inter',sans-serif;
            font-size:7.5px; letter-spacing:.1em; text-transform:uppercase; color:#A9A18C;
            display:flex; justify-content:space-between; }
    </style>
</head>
<body>

<div style="border-radius:12px; overflow:hidden; margin-bottom:14px;">
    <x-pdf-header serif navy="#0B1F3A" gold="#D4AF37"
        eyebrow="Elite Business Hub · Master Schedule · Internal"
        :title="$event->name"
        :subtitle="'Every session incl. crew & build'.($event->venue ? ' · '.$event->venue->name : '')"
        :chips="[
            ['n' => (string) $all->count(), 'l' => 'Sessions'],
            ['n' => (string) $event->agendaDays->count(), 'l' => 'Days'],
            ['n' => (string) $unconfirmed, 'l' => 'Unconfirmed'],
        ]" />
</div>

@if ($unconfirmed > 0)
    <div class="draft">
        <span class="draft-tag">Draft</span>
        <span><b>{{ $unconfirmed }} of {{ $all->count() }}</b> sessions not yet confirmed — not for distribution.</span>
    </div>
@endif

@foreach ($event->agendaDays as $d)
    <div class="day">
        <div class="daytag">
            <span class="date">{{ $d->date?->format('D · M j') }}</span>
            <span class="dayname">{{ $d->label }}</span>
            <span class="daymeta">{{ $d->sessions->count() }} {{ \Illuminate\Support\Str::plural('session', $d->sessions->count()) }}</span>
        </div>

        <table class="t">
            <thead>
                <tr>
                    <th style="width:15%">Time</th>
                    <th style="width:8%">Length</th>
                    <th>Session</th>
                    <th style="width:20%">Room</th>
                    <th style="width:12%">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($d->sessions->sortBy('starts_at') as $s)
                    @php $crew = in_array($s->track, \App\Services\AgendaProgram::CREW_ONLY, true); @endphp
                    <tr @class(['crew' => $crew])>
                        <td class="tm">{{ substr($s->starts_at, 0, 5) }}–{{ substr($s->ends_at, 0, 5) }}</td>
                        <td class="du">{{ $dur($s) }}</td>
                        <td>
                            <div class="ti">{{ $s->title }}</div>
                            @if ($line = $s->speakerLine())<div class="sp">{{ $line }}</div>@endif
                            <div class="tk">{{ str($s->type)->replace('_', ' ')->title() }}@if ($s->track) · {{ $s->track }} @endif</div>
                        </td>
                        <td class="rm">{{ $s->room?->name ?? '—' }}</td>
                        <td><span class="chip {{ $s->isSettled() ? 'ok' : 'no' }}">{{ $s->statusLabel() }}</span></td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="rm" style="padding:12px 8px;">No sessions scheduled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endforeach

<div class="foot">
    <span>Elite Business Hub · Master Schedule</span>
    <span>{{ now()->format('j M Y') }}</span>
</div>

</body>
</html>
