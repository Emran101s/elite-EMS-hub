@props([
    // segments: [['value'=>40,'tone'=>'ion','label'=>'Spent','display'=>'JD 40K'], ...]
    'segments' => [],
    'total' => null,     // pass to hold the bar to a known whole (e.g. the budget)
    'tall' => false,
    'legend' => false,
])
@php
    $sum = (float) collect($segments)->sum(fn ($s) => $s['value'] ?? 0);
    $whole = max(0.0001, (float) ($total ?? $sum));
    // Segments are fills, so every one of them uses the -lit token, never the read colour.
    $fill = fn ($s) => (\App\Support\Tone::tryFrom($s['tone'] ?? 'ion') ?? \App\Support\Tone::Ion)->lit();
@endphp
<div {{ $attributes->merge(['class' => 'o-meter'.($tall ? ' o-meter--tall' : '')]) }}>
    @foreach ($segments as $s)
        <span style="width:{{ round(($s['value'] ?? 0) / $whole * 100, 2) }}%;background:{{ $fill($s) }}"></span>
    @endforeach
</div>
@if ($legend)
    <ul class="o-legend">
        @foreach ($segments as $s)
            <li><i style="background:{{ $fill($s) }}"></i>{{ $s['label'] ?? '' }}<b>{{ $s['display'] ?? $s['value'] }}</b></li>
        @endforeach
    </ul>
@endif
