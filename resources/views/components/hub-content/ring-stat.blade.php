@props(['pct', 'tone' => 'var(--color-gold-500)', 'size' => 64])

<div class="flex items-center gap-3">
    <div class="ccx-ring shrink-0" style="width: {{ $size }}px; height: {{ $size }}px; --ccx-ring: {{ $tone }}; --ccx-ring-pct: {{ $pct }}%">
        <span class="ccx-ring-value" style="font-size: {{ round($size * 0.21) }}px">{{ $pct }}%</span>
    </div>
    @if ($slot->isNotEmpty())
        <div class="min-w-0 flex-1">{{ $slot }}</div>
    @endif
</div>
