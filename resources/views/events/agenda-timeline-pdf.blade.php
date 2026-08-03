<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800|inter:400,500,600,700,800" rel="stylesheet">
    <style>{!! $css !!}</style>
    @php
        $allSessions = $event->agendaDays->flatMap->sessions;
        $totalSessions = $allSessions->count();
        $toMin = fn ($t) => (int) substr((string) $t, 0, 2) * 60 + (int) substr((string) $t, 3, 2);
        $totalHours = round($allSessions->sum(fn ($s) => max($toMin($s->ends_at) - $toMin($s->starts_at), 0)) / 60, 1);
        $roomTotal = $allSessions->pluck('room.name')->filter()->unique()->count();
        $navy = '#0B1F3A';
        $gold = '#D4AF37';
    @endphp
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { margin: 0; background: #fff; font-family: 'Inter', sans-serif; color: #0F172A; }
        .pf { font-family: 'Spectral', serif; }

        .body { padding: 14px 26px 26px; }
        .legendrow { display: flex; flex-wrap: wrap; align-items: center; gap: 7px 16px; padding: 9px 0 10px; border-bottom: 1px solid #E7ECF3; }
        .legendrow .cap { font-size: 8px; font-weight: 800; letter-spacing: 1.5px; text-transform: uppercase; color: #94A3B8; }
        .lgi { display: flex; align-items: center; gap: 6px; font-size: 10px; font-weight: 600; color: #334155; }
        .sw { width: 17px; height: 10px; border-radius: 3px; }
        .dot { width: 8px; height: 8px; border-radius: 50%; }
        .dashnote { font-size: 9px; font-style: italic; color: #94A3B8; }

        .day { margin-top: 18px; }
        .dayhead { display: flex; align-items: baseline; gap: 12px; border-bottom: 2px solid {{ $gold }}; padding-bottom: 6px; break-after: avoid; }
        .daytag { font-size: 9px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase; color: {{ $gold }}; background: {{ $navy }}; padding: 3px 9px; border-radius: 5px; }
        .dayname { font-size: 15px; font-weight: 800; color: {{ $navy }}; }
        .daymeta { margin-left: auto; font-size: 10px; font-weight: 600; color: #64748B; }

        .hoursrow { position: relative; height: 22px; margin-left: 150px; border-bottom: 1px solid #E7ECF3; margin-top: 10px; }
        .hourlbl { position: absolute; top: 5px; transform: translateX(-50%); font-size: 9px; font-weight: 700; color: #94A3B8; }
        .lane { display: flex; align-items: stretch; border-bottom: 1px solid #E7ECF3; break-inside: avoid; }
        .lane:last-child { border-bottom: none; }
        .lanehead { flex: 0 0 150px; width: 150px; padding: 11px 12px; }
        .laneroom { font-size: 11px; font-weight: 800; color: {{ $navy }}; }
        .lanemeta { font-size: 9px; color: #94A3B8; margin-top: 2px; }
        .track { position: relative; flex: 1; }
        .gline { position: absolute; top: 0; bottom: 0; width: 1px; background: #EEF2F8; }
        .lanebody { position: relative; padding: 8px 0; min-height: 58px; }
        .blk { position: absolute; top: 8px; height: 42px; border-radius: 9px; padding: 5px 9px; color: #fff; overflow: hidden; }
        .blk .t { display: flex; align-items: center; gap: 4px; font-size: 9.5px; font-weight: 800; line-height: 1.1; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .blk .m { display: flex; align-items: center; gap: 5px; font-size: 8px; color: rgba(255,255,255,0.88); margin-top: 3px; white-space: nowrap; overflow: hidden; }
        .blk .sdot { width: 6px; height: 6px; border-radius: 50%; box-shadow: 0 0 0 1px rgba(255,255,255,0.6); flex: 0 0 auto; }
        .emptyday { padding: 20px; text-align: center; font-size: 11px; color: #94A3B8; border: 1px dashed #CBD5E1; border-radius: 8px; margin-top: 10px; }
        .foot { text-align: center; font-size: 9px; color: #94A3B8; padding: 14px 0 4px; margin-top: 10px; border-top: 1px solid #E7ECF3; }
    </style>
</head>
<body>
    <x-pdf-header serif :navy="$navy" :gold="$gold"
        eyebrow="Elite Business Hub · Agenda Timeline"
        :title="$event->name"
        :subtitle="$single ? $single->label.' — '.($single->date?->format('l, j F Y') ?? '') : 'Full timeline · '.count($days).' '.\Illuminate\Support\Str::plural('day', count($days))"
        :chips="[
            ['n' => (string) count($days), 'l' => 'Days'],
            ['n' => (string) $totalSessions, 'l' => 'Sessions'],
            ['n' => (string) $totalHours, 'l' => 'Hours'],
            ['n' => (string) $roomTotal, 'l' => 'Rooms'],
        ]" />

    <div class="body">
        {{-- legends: session type + confirmation status --}}
        <div class="legendrow">
            <span class="cap">Type</span>
            @foreach ($legend as [$label, $hex])
                <span class="lgi"><span class="sw" style="background: {{ $hex }}"></span>{{ $label }}</span>
            @endforeach
        </div>
        <div class="legendrow">
            <span class="cap">Status</span>
            @foreach (\App\Models\EventAgendaSession::STATUS_META as [$slbl, $ssettled, $shex])
                <span class="lgi"><span class="dot" style="background: {{ $shex }}"></span>{{ $slbl }}</span>
            @endforeach
            <span class="dashnote">dashed outline = not yet confirmed</span>
        </div>

        @foreach ($days as $d)
            <div class="day">
                <div class="dayhead">
                    <span class="daytag">Day {{ $d['index'] }}</span>
                    <span class="dayname pf">{{ $d['date'] ?: $d['label'] }}</span>
                    <span class="daymeta">
                        @if ($d['timeline'])
                            {{ $d['timeline']['lanes']->sum(fn ($l) => $l['blocks']->count()) }} sessions · {{ $d['timeline']['lanes']->count() }} {{ \Illuminate\Support\Str::plural('room', $d['timeline']['lanes']->count()) }}
                        @else
                            No sessions
                        @endif
                    </span>
                </div>

                @if ($d['timeline'])
                    @php $tl = $d['timeline']; @endphp
                    {{-- hour ruler --}}
                    <div class="hoursrow">
                        @foreach ($tl['hours'] as $hour)
                            <span class="hourlbl" style="left: {{ $hour['left'] }}%">{{ $hour['label'] }}</span>
                        @endforeach
                    </div>

                    {{-- room lanes --}}
                    @foreach ($tl['lanes'] as $lane)
                        <div class="lane">
                            <div class="lanehead">
                                <div class="laneroom">{{ $lane['room'] }}</div>
                                <div class="lanemeta">{{ $lane['blocks']->count() }} {{ \Illuminate\Support\Str::plural('session', $lane['blocks']->count()) }}</div>
                            </div>
                            <div class="track">
                                @foreach ($tl['hours'] as $hour)
                                    <span class="gline" style="left: {{ $hour['left'] }}%"></span>
                                @endforeach
                                <div class="lanebody">
                                    @foreach ($lane['blocks'] as $b)
                                        @php $s = $b['session']; @endphp
                                        <div class="blk" style="left: {{ $b['left'] }}%; width: {{ $b['width'] }}%; background: {{ $b['hex'] }};{{ $s->isSettled() ? '' : ' outline: 2px dashed rgba(255,255,255,0.55); outline-offset: -3px;' }}">
                                            <div class="t">@if ($s->flagged)<span>🚩</span>@endif<span>{{ $s->title }}</span></div>
                                            <div class="m"><span class="sdot" style="background: {{ $s->statusHex() }}"></span>{{ substr($s->starts_at, 0, 5) }}–{{ substr($s->ends_at, 0, 5) }}</div>
                                        </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endforeach
                @else
                    <div class="emptyday">Nothing scheduled for this day.</div>
                @endif
            </div>
        @endforeach

        <div class="foot">Generated {{ now()->format('j M Y · H:i') }} · Elite Business Hub — Operations Command Center</div>
    </div>
</body>
</html>
