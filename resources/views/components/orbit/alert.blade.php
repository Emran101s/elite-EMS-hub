@props([
    'tone' => 'ion',
    'title' => '',
    'sub' => null,
    'time' => null,
    'icon' => null,      // renders an icon tile instead of the dot
])
@php $t = $tone instanceof \App\Support\Tone ? $tone : (\App\Support\Tone::tryFrom($tone) ?? \App\Support\Tone::Ion); @endphp
<div {{ $attributes->merge(['class' => 'o-alert']) }}>
    @if ($icon)
        <span class="o-alert__ico" style="background:{{ $t->tint() }};color:{{ $t->var() }}">
            <x-orbit.icon :name="$icon" :size="14" />
        </span>
    @else
        <span class="o-alert__dot" style="background:{{ $t->lit() }}"></span>
    @endif
    <div>
        <div class="o-alert__title">{{ $title }}</div>
        @if ($sub)<div class="o-alert__sub">{{ $sub }}</div>@endif
        {{ $slot }}
    </div>
    @if ($time)<div class="o-alert__time">{{ $time }}</div>@endif
</div>
