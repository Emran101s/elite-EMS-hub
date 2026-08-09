@props([
    'padding' => true,
])

{{-- Active queue / selected master item — deep navy Soft Command surface. --}}
<div {{ $attributes->class([
    'eo-selected-dark',
    'p-4 sm:p-5' => $padding,
]) }}>
    {{ $slot }}
</div>
