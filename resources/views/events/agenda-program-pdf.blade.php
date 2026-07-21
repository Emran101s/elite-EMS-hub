<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800|inter:400,500,600,700,800" rel="stylesheet">
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4 portrait; margin: 14mm 12mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { background:#fff; margin:0; }
        /* Keep the intro card with the first day rather than stranding it on page 1. */
        .apg-head { break-after: avoid; }
    </style>
</head>
<body>
    <x-agenda-program
        :days="$days"
        :heading="$event->name"
        :subheading="($single
            ? $single->label.' — '.($single->date?->format('l, j F Y') ?? '')
            : 'Full programme · '.count($days).' days')
            .($audience === 'public' ? ' · Delegate programme' : '')" />
</body>
</html>
