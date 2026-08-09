@props([
    'padding' => true,
])

{{-- Soft Command light workspace card — large radius, soft shadow, no hard border. --}}
<div {{ $attributes->class([
    'eo-soft-card',
    'p-5 sm:p-6' => $padding,
]) }}>
    {{ $slot }}
</div>
