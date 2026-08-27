@props([
    'label' => 'Venue',
    'venues' => [],
    'emptyLabel' => '— none —',
])

{{-- Venues carry a city worth showing alongside the name — Accommodation's
     existing hand-written select already does "Name · City"; this is that,
     as the default rather than something each caller has to opt into. --}}
<x-eo.entity-select {{ $attributes }} :label="$label" :items="$venues" subtitle-key="city" :empty-label="$emptyLabel" />
