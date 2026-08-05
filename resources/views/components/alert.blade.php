@props([
    'tone' => 'ok', // ok | warn | risk | info
])

@php
    $tones = [
        'ok' => 'bg-track/10 text-emerald-700 ring-track/30',
        'warn' => 'bg-warn/10 text-amber-800 ring-warn/30',
        'risk' => 'bg-risk/10 text-red-700 ring-risk/30',
        'info' => 'bg-navy-50 text-navy-700 ring-line',
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
