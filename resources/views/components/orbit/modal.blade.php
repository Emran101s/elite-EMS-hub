@props(['title' => null])
<div {{ $attributes->merge(['class' => 'o-modal']) }} role="dialog" aria-modal="true">
    @if ($title || isset($head))
        <div class="o-card__head">
            @isset($head){{ $head }}@else<h3 class="o-card__title">{{ $title }}</h3>@endisset
        </div>
    @endif
    <div class="o-card__pad">{{ $slot }}</div>
    @isset($foot)<div class="o-card__head" style="border-bottom:0;border-top:1px solid var(--rim)">{{ $foot }}</div>@endisset
</div>
