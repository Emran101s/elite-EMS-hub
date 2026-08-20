@props([
    'tone' => 'ok', // ok | warn | risk | info
])

@php
    $tones = [
        'ok' => 'bg-track/10 text-emerald-700 ring-track/30',
        'warn' => 'bg-eo-warn-soft text-eo-warn-ink ring-eo-warn/30',
        'risk' => 'bg-eo-risk-soft text-eo-risk-ink ring-eo-risk/30',
        'info' => 'bg-eo-bg text-eo-text ring-eo-line',
    ];
@endphp

{{--
    One flash / inline notice. Layout session flashes and in-page banners use
    the same tones so a blocked action never looks like a save.
--}}
<div {{ $attributes->merge(['class' => 'rounded-xl px-4 py-3 text-sm font-medium ring-1 '.($tones[$tone] ?? $tones['info'])]) }}
     role="status">
    {{ $slot }}
</div>
