@props([
    'tone' => 'ok', // ok | warn | risk | info
])

@php
    $tones = [
        'ok' => 'bg-track/10 text-emerald-700 ring-track/30',
        'warn' => 'bg-warning-soft text-warning-ink ring-warning/30',
        'risk' => 'bg-danger-soft text-danger-ink ring-danger/30',
        'info' => 'bg-page text-ink ring-line',
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
