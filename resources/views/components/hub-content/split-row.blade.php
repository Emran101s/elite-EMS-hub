@props(['active' => false])

<div {{ $attributes->class(['ehc-row px-4 py-2.5', 'is-selected' => $active]) }}>
    {{ $slot }}
</div>
