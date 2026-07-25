@props([
    'value' => 0,        // 0..100
    'tone' => 'ion',     // vital | ion | plasma | flare | critical | solar
    'size' => 88,
    'stroke' => 6,
    'label' => null,
    'showNum' => true,
])
@php
    $r = ($size / 2) - $stroke;
    $c = 2 * M_PI * $r;
    $pct = max(0, min(100, (float) $value));
    $off = $c - ($c * $pct / 100);
@endphp
<div {{ $attributes->merge(['class' => 'o-ring']) }} data-tone="{{ $tone }}">
    <svg width="{{ $size }}" height="{{ $size }}" viewBox="0 0 {{ $size }} {{ $size }}">
        <circle class="o-ring__track" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" stroke-width="{{ $stroke }}"/>
        <circle class="o-ring__value" cx="{{ $size / 2 }}" cy="{{ $size / 2 }}" r="{{ $r }}" stroke-width="{{ $stroke }}"
                stroke-dasharray="{{ round($c, 1) }}" stroke-dashoffset="{{ round($off, 1) }}"/>
    </svg>
    @if ($showNum || $label)
        <div class="o-ring__center">
            @if ($showNum)<div class="o-ring__num" style="color:var(--{{ $tone }})">{{ $value }}%</div>@endif
            @if ($label)<div class="o-ring__label">{{ $label }}</div>@endif
        </div>
    @endif
</div>
