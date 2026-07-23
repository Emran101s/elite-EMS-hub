<!doctype html>
{{--
    The PDF wrapper around the shared dossier paper. Same markup, same compiled
    Tailwind, same fonts as the live preview — the export IS the preview,
    paginated onto A4 with professional margins.
--}}
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $event->name }} — Event Brief</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800&display=swap" rel="stylesheet">
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 14mm 0 16mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { margin: 0; background: #fff; }
    </style>
</head>
<body>
    @include('event-brief.paper', [
        'event' => $event,
        'data' => $brief->data,
        'version' => $brief->version,
        'status' => $brief->status,
        'sections' => \App\Models\EventBrief::SECTIONS,
        'infoFields' => \App\Models\EventBrief::INFO_FIELDS,
        'twocolHeads' => \App\Models\EventBrief::TWOCOL_HEADS,
        'forPdf' => true,
    ])
</body>
</html>
