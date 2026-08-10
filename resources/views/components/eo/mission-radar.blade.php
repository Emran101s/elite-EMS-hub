@props([
    'label' => 'Mission Radar',
    'size' => 'md', // sm | md | lg
    'variant' => 'widget', // widget | hero
    'blips' => null, // [['tone'=>'ok|warn|risk|live','x'=>%,'y'=>%], ...]
    'missions' => null, // hero nodes: [['tone','x','y','label','href','featured','readiness'], ...]
    'stats' => null, // [['value','label','tone'?], ...]
    'story' => null, // short storytelling line under the field
])

@php
    $blips ??= [
        ['tone' => 'ok', 'x' => 32, 'y' => 38],
        ['tone' => 'warn', 'x' => 68, 'y' => 42],
        ['tone' => 'live', 'x' => 54, 'y' => 28],
        ['tone' => 'risk', 'x' => 40, 'y' => 66],
        ['tone' => 'ok', 'x' => 72, 'y' => 62],
    ];

    $toneColor = fn (string $tone) => match ($tone) {
        'ok' => 'var(--color-eo-ok)',
        'warn' => 'var(--color-eo-warn)',
        'risk' => 'var(--color-eo-risk)',
        default => 'var(--color-eo-teal)',
    };

    $isHero = $variant === 'hero';
    $max = match ($size) {
        'sm' => 'max-w-[180px]',
        'lg' => 'max-w-[340px]',
        default => 'max-w-[260px]',
    };

    /**
     * Keep every blip inside the dish.
     *
     * Callers compute coordinates from real data — an orbit angle, a days-out
     * radius — and nothing upstream knows how wide the label beside the dot
     * will be. The field is a circle with overflow:hidden, so a node at 4%
     * took its label off the edge and printed "…er LLC Expo". Clamped here,
     * once, rather than in each of the three callers: a radar that crops the
     * name of the event it is warning you about is worse than no radar.
     */
    $clamp = fn ($v, $lo = 12, $hi = 88) => max($lo, min($hi, (float) $v));

    $nodes = collect($missions ?? $blips)->map(fn ($b) => [
        'tone' => $b['tone'] ?? 'live',
        'x' => $clamp($b['x'] ?? 50),
        'y' => $clamp($b['y'] ?? 50),
        // Labels lean toward the middle: on the right half the text sits to
        // the LEFT of its dot, so it always grows inward, never off the rim.
        'flip' => $clamp($b['x'] ?? 50) > 55,
        'label' => isset($b['label']) ? \Illuminate\Support\Str::limit($b['label'], 22) : null,
        'href' => $b['href'] ?? null,
        'featured' => $b['featured'] ?? false,
        'readiness' => $b['readiness'] ?? null,
    ])->all();
@endphp

@if ($isHero)
    {{-- Hero Mission Radar — Orbit identity element (concept board). --}}
    <div {{ $attributes->class(['eo-radar-hero']) }} role="img" aria-label="{{ $label }}">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                <p class="text-[11px] font-bold uppercase tracking-[0.16em] text-eo-teal-lit/90">{{ $label }}</p>
                <p class="mt-1 text-[18px] font-semibold tracking-tight text-white">Event orbit</p>
                @if ($story)
                    <p class="mt-1 max-w-md text-[12.5px] leading-relaxed text-white/50">{{ $story }}</p>
                @endif
            </div>
            <span class="eo-journey-chip !bg-white/10 !text-eo-teal-lit">Elite Orbit</span>
        </div>

        <div class="eo-radar-hero-field">
            <span class="eo-radar-cross" aria-hidden="true"></span>
            <span class="eo-radar-ring" aria-hidden="true"></span>
            <span class="eo-radar-ring eo-radar-ring-2" aria-hidden="true"></span>
            <span class="eo-radar-ring eo-radar-ring-3" aria-hidden="true"></span>
            <span class="eo-radar-sweep" aria-hidden="true"></span>
            <span class="eo-radar-core" aria-hidden="true"></span>

            @foreach ($nodes as $node)
                @php
                    $tone = $node['tone'] ?? 'live';
                    $featured = ! empty($node['featured']);
                    $href = $node['href'] ?? null;
                    $tag = $href ? 'a' : 'span';
                @endphp
                <{{ $tag }}
                    @if ($href) href="{{ $href }}" @endif
                    @class(['eo-radar-node', 'is-featured' => $featured, 'is-flipped' => ! empty($node['flip'])])
                    style="left: {{ $node['x'] }}%; top: {{ $node['y'] }}%; color: {{ $toneColor($tone) }}"
                    @if (! empty($node['label'])) title="{{ $node['label'] }}{{ isset($node['readiness']) ? ' · '.$node['readiness'].'% ready' : '' }}" @endif
                >
                    <span class="eo-radar-node-dot" style="background: currentColor"></span>
                    @if (! empty($node['label']))
                        <span class="eo-radar-node-label">
                            {{ $node['label'] }}
                            @isset($node['readiness'])
                                <span class="opacity-70">· {{ (int) $node['readiness'] }}%</span>
                            @endisset
                        </span>
                    @endif
                </{{ $tag }}>
            @endforeach
        </div>

        @if (! empty($stats))
            <div class="mt-5 grid grid-cols-2 gap-2 sm:grid-cols-4">
                @foreach ($stats as $stat)
                    <div class="eo-radar-stat">
                        <p class="eo-radar-stat-value" @if (! empty($stat['tone'])) style="color: {{ $toneColor($stat['tone']) }}" @endif>
                            {{ $stat['value'] }}
                        </p>
                        <p class="eo-radar-stat-label">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
@else
    {{-- Compact widget radar (gallery / tight slots). --}}
    <div {{ $attributes->class(['eo-radar', $max]) }} role="img" aria-label="{{ $label }}">
        <span class="eo-radar-cross" aria-hidden="true"></span>
        <span class="eo-radar-ring" aria-hidden="true"></span>
        <span class="eo-radar-ring eo-radar-ring-2" aria-hidden="true"></span>
        <span class="eo-radar-ring eo-radar-ring-3" aria-hidden="true"></span>
        <span class="eo-radar-sweep" aria-hidden="true"></span>
        <span class="eo-radar-core" aria-hidden="true"></span>

        @foreach ($nodes as $node)
            @php
                $tone = $node['tone'] ?? 'live';
                $class = match ($tone) {
                    'ok' => 'eo-radar-blip-ok',
                    'warn' => 'eo-radar-blip-warn',
                    'risk' => 'eo-radar-blip-risk',
                    default => 'eo-radar-blip-live',
                };
            @endphp
            <span
                class="eo-radar-blip {{ $class }}"
                style="left: {{ $node['x'] }}%; top: {{ $node['y'] }}%"
                aria-hidden="true"
            ></span>
        @endforeach

        <span class="eo-radar-label">{{ $label }}</span>
    </div>
@endif
