@props(['percent', 'group' => 'track', 'size' => 'h-14 w-14', 'label' => true, 'dark' => false, 'textSize' => 'text-[0.65rem]'])

@php
    // r = 15.9155 → circumference ≈ 100, so dasharray maps 1:1 to percent.
    // A null percent means the event is not scored yet (draft/proposal): the
    // track is drawn empty and the label reads "—", never a misleading 0%.
    $unscored = $percent === null || $group === 'neutral';
    $pct = $unscored ? 0 : (int) $percent;

    $stroke = match ($group) {
        'risk' => 'stroke-risk',
        'warn' => 'stroke-warn',
        'neutral' => 'stroke-navy-200',
        default => 'stroke-track',
    };
    $text = $dark ? 'text-navy-900' : match ($group) {
        'risk' => 'text-risk',
        'warn' => 'text-amber-600',
        'neutral' => 'text-muted',
        default => 'text-emerald-600',
    };
@endphp

<span {{ $attributes->merge(['class' => "relative inline-flex items-center justify-center {$size}"]) }}>
    <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90">
        <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" class="stroke-navy-50" />
        @unless ($unscored)
            <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="3.5" stroke-linecap="round"
                    stroke-dasharray="{{ $pct }} {{ 100 - $pct }}" class="{{ $stroke }}" />
        @endunless
    </svg>
    @if ($label)
        <span class="absolute font-bold {{ $textSize }} {{ $text }}">{{ $unscored ? '—' : $pct.'%' }}</span>
    @endif
</span>
