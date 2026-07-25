@props([
    'icon' => null,
    'name' => '',
    'meta' => [],        // ['12 sessions', 'St. Regis Amman', …]
    'amount' => null,    // right-aligned money/number
    'href' => null,
])
@php $tag = $href ? 'a' : 'div'; @endphp
<{{ $tag }} @if ($href) href="{{ $href }}" @endif {{ $attributes->merge(['class' => 'o-row']) }}>
    <span class="o-row__icon">@if ($icon)<x-orbit.icon :name="$icon" :size="17" />@endif</span>
    <div class="min-w-0">
        <div class="o-row__name">{{ $name }}</div>
        @if ($meta)
            <div class="o-row__meta">
                @foreach ($meta as $m)<span>{{ $m }}</span>@endforeach
            </div>
        @endif
    </div>
    <div>{{ $slot }}</div>
    @if ($amount !== null)<div class="o-num">{{ $amount }}</div>@endif
</{{ $tag }}>
