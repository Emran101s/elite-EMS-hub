<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800|inter:400,500,600,700,800" rel="stylesheet">
    <style>{!! $css !!}</style>
    @php $navy = '#0B1F3A'; $navy2 = '#16294A'; $gold = '#B08D2B'; @endphp
    <style>
        @page { size: A4 landscape; margin: 12mm 12mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }
        html, body { background:#fff; margin:0; color:#26313F; font-family:'Spectral', Georgia, serif; }

        .day { margin-top:16px; }
        .daytag { display:flex; align-items:baseline; gap:12px; border-bottom:2px solid {{ $gold }};
            padding-bottom:6px; break-after:avoid; break-inside:avoid; }
        .date { font-family:'Inter',sans-serif; font-size:10px; font-weight:800; letter-spacing:.13em;
            text-transform:uppercase; color:{{ $gold }}; }
        .dayname { font-size:16px; font-weight:700; color:{{ $navy }}; }

        table.t { width:100%; border-collapse:collapse; }
        table.t th { font-family:'Inter',sans-serif; font-size:7.5px; font-weight:700; letter-spacing:.12em;
            text-transform:uppercase; color:#8A94A6; text-align:left; padding:6px 8px; border-bottom:1px solid #E7E3D6; }
        table.t td { padding:7px 8px; border-bottom:1px solid #EFEDE4; font-size:11px; vertical-align:top; }
        table.t tr { break-inside:avoid; }

        .tm { font-family:'Inter',sans-serif; font-weight:700; font-size:11px; font-variant-numeric:tabular-nums;
            color:{{ $navy }}; white-space:nowrap; }
        .du { font-family:'Inter',sans-serif; font-size:9px; color:#8A94A6; font-variant-numeric:tabular-nums; white-space:nowrap; }
        .ti { font-weight:600; line-height:1.25; }
        .sp { font-family:'Inter',sans-serif; font-size:8.5px; font-weight:600; color:{{ $navy }}; margin-top:2px; }
        .rm { font-family:'Inter',sans-serif; font-size:10px; font-weight:600; color:#26313F; }
        .cue { font-family:'Inter',sans-serif; font-size:9px; color:#8A94A6; }
        .box { display:inline-block; width:9px; height:9px; border:1px solid #B9C2CF; border-radius:2px; }

        /* Crew rows are the point of this document, not noise — but they read quieter. */
        .crew td { background:#FAFAF7; }
        .crew .ti { color:#5B667A; font-weight:500; }
        .tag { font-family:'Inter',sans-serif; font-size:7px; font-weight:700; letter-spacing:.1em;
            text-transform:uppercase; border-radius:3px; padding:1px 5px; margin-left:6px;
            color:#6B5A22; background:#F1E7C6; }

        /* Venue-wide turnaround — where show-day problems happen. */
        .gap td { background:#FFF9E9; border-top:1px dashed #E3CE8F; border-bottom:1px dashed #E3CE8F; }
        .gap .g { font-family:'Inter',sans-serif; font-size:8.5px; font-weight:700; letter-spacing:.14em;
            text-transform:uppercase; color:{{ $gold }}; }

        .foot { margin-top:14px; border-top:1px solid #E7E3D6; padding-top:6px; font-family:'Inter',sans-serif;
            font-size:7.5px; letter-spacing:.1em; text-transform:uppercase; color:#A9A18C;
            display:flex; justify-content:space-between; }
    </style>
</head>
<body>

@php $rosSessions = collect($days)->sum(fn ($d) => $d['day']->sessions->count()); @endphp
<div style="border-radius:12px; overflow:hidden; margin-bottom:14px;">
    <x-pdf-header serif navy="#0B1F3A" gold="#D4AF37"
        eyebrow="Elite Business Hub · Run of Show · Operations"
        :title="$event->name"
        :subtitle="($single ? $single->label.' — '.($single->date?->format('l, j F Y') ?? '') : 'Full run of show · '.count($days).' '.\Illuminate\Support\Str::plural('day', count($days))).($event->venue ? ' · '.$event->venue->name : '')"
        :chips="[
            ['n' => (string) count($days), 'l' => 'Days'],
            ['n' => (string) $rosSessions, 'l' => 'Cues'],
        ]" />
</div>

@foreach ($days as $d)
    <div class="day">
        <div class="daytag">
            <span class="date">{{ $d['day']->date?->format('D · M j') }}</span>
            <span class="dayname">{{ $d['day']->label }}</span>
        </div>

        <table class="t">
            <thead>
                <tr>
                    <th style="width:11%">Time</th>
                    <th style="width:7%">Length</th>
                    <th>Session &amp; line-up</th>
                    <th style="width:17%">Room</th>
                    <th style="width:9%">Status</th>
                    <th style="width:14%">Cue / notes</th>
                    <th style="width:5%">Done</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($d['rows'] as $row)
                    @if ($row['gap'])
                        <tr class="gap">
                            <td class="tm">{{ $row['time'] }}</td>
                            <td class="du">{{ $row['length'] }}</td>
                            <td colspan="5" class="g">Venue clear — turnaround / reset</td>
                        </tr>
                    @else
                        @php $s = $row['session']; @endphp
                        <tr @class(['crew' => $row['crew']])>
                            <td class="tm">{{ $row['time'] }}</td>
                            <td class="du">{{ $row['length'] }}</td>
                            <td>
                                <span class="ti">{{ $s->title }}</span>@if ($row['crew'])<span class="tag">Crew</span>@endif
                                @if ($line = $s->speakerLine())<div class="sp">{{ $line }}</div>@endif
                            </td>
                            <td class="rm">{{ $s->room?->name ?? '—' }}</td>
                            <td class="cue">{{ $s->statusLabel() }}</td>
                            <td class="cue">{{ $s->flagged ? '⚑ Flagged' : '' }}</td>
                            <td><span class="box"></span></td>
                        </tr>
                    @endif
                @empty
                    <tr><td colspan="7" class="cue" style="padding:12px 8px;">Nothing scheduled.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endforeach

<div class="foot">
    <span>Elite Business Hub · Run of Show · Internal operations document</span>
    <span>{{ now()->format('j M Y') }}</span>
</div>

</body>
</html>
