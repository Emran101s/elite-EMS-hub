@props([
    'label' => 'Supplier',
    'suppliers' => [],
    'emptyLabel' => '— none —',
])

<x-eo.entity-select {{ $attributes }} :label="$label" :items="$suppliers" :empty-label="$emptyLabel" />
