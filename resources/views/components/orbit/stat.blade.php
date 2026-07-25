@props([
    'label' => '',
    'value' => '',
    'unit' => null,
    'foot' => null,          // plain string; or pass a <x-slot:foot>
    'delta' => null,         // ['dir'=>'up'|'down','text'=>'12']
    'tone' => null,          // colour the value (critical/flare/vital…)
])
<div {{ $attributes->merge(['class' => 'o-stat']) }}>
    <div class="o-stat__label">{{ $label }}</div>
    <div class="o-stat__value">
        <span class="o-metric" @if ($tone) style="color:var(--{{ $tone }})" @endif>{{ $value }}</span>
        @if ($unit)<span class="o-stat__unit">{{ $unit }}</span>@endif
    </div>
    @if ($delta || $foot || isset($footSlot))
        <div class="o-stat__foot">
            @if ($delta)
                <span class="o-delta o-delta--{{ $delta['dir'] ?? 'up' }}">{{ ($delta['dir'] ?? 'up') === 'down' ? '▼' : '▲' }} {{ $delta['text'] ?? '' }}</span>
            @endif
            {{ $foot }}{{ $footSlot ?? '' }}
        </div>
    @endif
</div>
