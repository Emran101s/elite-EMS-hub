@props([
    'gravity' => null,   // hero | primary | secondary | ambient (only "hero" & "ambient" style differently)
    'accent' => null,    // gold | vital | ion | plasma | flare | critical  (left accent bar)
    'title' => null,     // renders a __head; or pass a <x-slot:head>
    'hover' => false,
    'pad' => true,       // wrap slot in __pad
])
@php
    $classes = 'o-card'.($hover ? ' o-card--hover' : '');
@endphp
<div {{ $attributes->merge(['class' => $classes]) }}@if ($gravity) data-gravity="{{ $gravity }}"@endif @if ($accent) data-accent="{{ $accent }}"@endif>
    @if (isset($head) || $title)
        <div class="o-card__head">
            @isset($head){{ $head }}@else<h3 class="o-card__title">{{ $title }}</h3>@endisset
        </div>
    @endif
    @if ($pad)<div class="o-card__pad">{{ $slot }}</div>@else{{ $slot }}@endif
</div>
