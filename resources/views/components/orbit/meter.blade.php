@props([
    // segments: [['value'=>40,'tone'=>'ion','label'=>'Spent','display'=>'JD 40K'], ...]
    'segments' => [],
    'tall' => false,
    'legend' => false,
])
@php
    $total = max(1, collect($segments)->sum('value'));
@endphp
<div {{ $attributes->merge(['class' => 'o-meter'.($tall ? ' o-meter--tall' : '')]) }}>
    @foreach ($segments as $s)
        <span style="width:{{ round(($s['value'] ?? 0) / $total * 100, 2) }}%;background:var(--{{ $s['tone'] ?? 'ion' }})"></span>
    @endforeach
</div>
@if ($legend)
    <ul class="o-legend">
        @foreach ($segments as $s)
            <li><i style="background:var(--{{ $s['tone'] ?? 'ion' }})"></i>{{ $s['label'] ?? '' }}<b>{{ $s['display'] ?? $s['value'] }}</b></li>
        @endforeach
    </ul>
@endif
