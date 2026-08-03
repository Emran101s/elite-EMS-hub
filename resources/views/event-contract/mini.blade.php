{{--
    A live miniature of a document's REAL paper — the same shared partial the
    preview and the PDF render, scaled down and clipped to its opening stretch.
    Expects: $c (contract with signatories loaded), $event,
             $scale (0..1 of the 640px paper), $window (clip height in px)
--}}
@php
    $miniData = $c->data ?? [];
    $miniData['blocks'] = $c->blocks();
    $miniF = $miniData['financials'] ?? [];
    $miniCur = $c->currencyCode();
    $miniSigs = $c->signatories;
@endphp
<div class="pointer-events-none select-none overflow-hidden bg-white"
     style="height: {{ $window }}px; width: {{ round(640 * $scale) }}px;" aria-hidden="true">
    <div class="origin-top-left" style="transform: scale({{ $scale }}); width: 640px;">
        @include('event-contract.paper', [
            'tLabel' => $c->typeLabel(),
            'type' => $c->type,
            'title' => $c->title ?? '',
            'data' => $miniData,
            'bilingual' => $c->isClient() || $c->isBilingual(),
            'event' => $event,
            'fmt' => fn ($x) => $miniCur.' '.number_format(($x ?? 0) / 100),
            'est' => $miniF['contract_value_cents'] ?? $miniF['estimated_total_cents'] ?? 0,
            'f' => $miniF,
            'signatories' => $miniSigs,
            'reference' => $c->reference,
            'status' => $c->status,
            'fullySigned' => $miniSigs->isNotEmpty() && $miniSigs->whereNull('signed_at')->isEmpty(),
            'forPdf' => true,
        ])
    </div>
</div>
