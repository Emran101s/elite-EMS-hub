<!doctype html>
{{--
    The PDF wrapper around the shared paper partial. Same markup, same compiled
    Tailwind, same fonts as the live preview — so the export IS the preview,
    paginated onto A4.
--}}
@php
    $tLabel = $contract->typeLabel();
    $data = $contract->data ?? [];
    $data['blocks'] = $contract->blocks();   // client falls back to the standard set
    $f = $data['financials'] ?? [];
    $est = $f['contract_value_cents'] ?? $f['estimated_total_cents'] ?? 0;
    // Matches the live editor's own $fmt exactly (contract-tab.blade.php) — the
    // cost-share and payment-schedule tables this feeds are shared between the
    // two surfaces, and a different formatter here would mean the preview and
    // the export print the same figure two different ways.
    $fmt = fn ($c) => $event->money((int) round($c ?? 0));
    $bilingual = $contract->isClient() || $contract->isBilingual();
    $fullySigned = $signatories->isNotEmpty() && $signatories->whereNull('signed_at')->isEmpty();
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->displayTitle() }}</title>
    {{-- The same faces the app loads, so type metrics match the preview exactly. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800&display=swap" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=amiri:400,700&display=swap" rel="stylesheet">
    <style>{!! $css !!}</style>
    <style>
        /* Professional A4 margins: ~20mm sides come from the centred paper column
           ((210mm − 169mm) / 2); 14mm top and 16mm bottom apply to EVERY page, so
           page 2+ content never starts at the paper's edge. Bottom slightly deeper
           than top — the classic optical balance for a bound document. */
        @page { size: A4; margin: 14mm 0 16mm; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        html, body { margin: 0; background: #fff; }
    </style>
</head>
<body>
    @include('event-contract.paper', [
        'tLabel' => $tLabel, 'type' => $contract->type, 'title' => $contract->title ?? '',
        'data' => $data, 'bilingual' => $bilingual, 'event' => $event,
        'fmt' => $fmt, 'est' => $est, 'f' => $f, 'signatories' => $signatories,
        'reference' => $contract->reference, 'status' => $contract->status,
        'fullySigned' => $fullySigned, 'forPdf' => true,
        'appendices' => $contract->appendices(),
    ])
</body>
</html>
