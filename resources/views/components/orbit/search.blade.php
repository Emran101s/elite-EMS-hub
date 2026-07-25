@props(['placeholder' => 'Search events, tasks, suppliers…', 'kbd' => '⌘K'])
<div {{ $attributes->merge(['class' => 'o-search']) }} role="button" tabindex="0">
    <x-orbit.icon name="search" :size="16" />
    <span style="flex:1">{{ $placeholder }}</span>
    @if ($kbd)<span class="o-kbd">{{ $kbd }}</span>@endif
</div>
