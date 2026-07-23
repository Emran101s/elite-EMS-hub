<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800|inter:400,500,600,700,800" rel="stylesheet">
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4 portrait; margin: 13mm 12mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { background:#fff; margin:0; }
        .pgleg { display:flex; flex-wrap:wrap; gap:14px; padding:10px 0 2px; }
        .pgleg span { font-family:'Inter',system-ui,sans-serif; font-size:10px; font-weight:600; color:#5B667A; display:flex; align-items:center; gap:6px; }
        .pgdot { width:9px; height:9px; border-radius:3px; display:inline-block; }
    </style>
</head>
<body>
    @php $pgSessions = $event->agendaDays->sum(fn ($d) => $d->sessions->count()); @endphp
    <div style="border-radius:12px; overflow:hidden; margin-bottom:12px;">
        <x-pdf-header serif navy="#0B1F3A" gold="#D4AF37"
            eyebrow="Elite Business Hub · Programme"
            :title="$event->name"
            :subtitle="($single
                ? $single->label.' — '.($single->date?->format('l, j F Y') ?? '')
                : 'Full programme · '.count($days).' '.\Illuminate\Support\Str::plural('day', count($days)))
                .($audience === 'public' ? ' · Delegate programme' : '')"
            :chips="[
                ['n' => (string) count($days), 'l' => 'Days'],
                ['n' => (string) $pgSessions, 'l' => 'Sessions'],
            ]" />
    </div>

    {{-- programme legend (was inside the component's own header) --}}
    <div class="pgleg">
        <span><i class="pgdot" style="background:#B08D2B"></i> Main Stage</span>
        <span><i class="pgdot" style="background:#4E6D9C"></i> Plenary</span>
        <span><i class="pgdot" style="background:#8A94A6"></i> Track</span>
        <span><i class="pgdot" style="background:#5FA88C"></i> Networking</span>
        <span><i class="pgdot" style="background:#B06B6B"></i> Ceremony</span>
    </div>

    <x-agenda-program :days="$days" :legend="false" />
</body>
</html>
