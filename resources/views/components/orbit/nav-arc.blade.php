@props([
    // inner/outer: [['label'=>'Tasks','icon'=>'task','href'=>'…','count'=>17,'active'=>false], …]
    'inner' => [],       // the 5 modules touched daily
    'outer' => [],       // structure — further out, so quieter
    'current' => null,   // key matched against each item's 'key'
    'core' => null,      // <x-slot:core> for the disc's contents
    'innerSpan' => 120,  // total degrees the inner ring fans across
    'outerSpan' => 96,
    'innerRadius' => 150,
    'outerRadius' => 190,
])
@php
    /**
     * Law 2 — distance is relevance. Node positions are POLAR, computed here from
     * a constant centre and radius, so the angles are identical on every event and
     * nothing is ever hand-placed. Solved back from the design: the arc box is
     * 236 x 520, the disc centres on the left edge at y = 235, the inner ring sits
     * at r = 150 in 30° steps and the outer at r = 190 in 32° steps.
     */
    $cx = 0.0;
    // Centre low enough that the widest ring's top node still clears the box —
    // half a node (38px) plus its label. Derived, so changing a radius can't
    // silently clip a module off the top of the arc.
    $reach = max((float) $innerRadius * sin(deg2rad((float) $innerSpan / 2)), (float) $outerRadius * sin(deg2rad((float) $outerSpan / 2)));
    $cy = round($reach + 48, 1);
    $arcHeight = round($cy * 2, 1);

    /**
     * Fan the ring symmetrically about the horizontal across a fixed angular
     * span, so adding a module re-spaces the ring instead of pushing the last
     * one off the arc. The defaults reproduce the design exactly: 5 inner nodes
     * across 120° give 30° steps, 4 outer across 96° give 32°.
     */
    $place = function (array $items, float $radius, float $span) use ($cx, $cy) {
        $n = count($items);
        if ($n === 0) {
            return [];
        }
        $step = $n > 1 ? $span / ($n - 1) : 0.0;
        $first = -$step * ($n - 1) / 2;

        return array_map(function ($item, $i) use ($cx, $cy, $radius, $step, $first) {
            $angle = deg2rad($first + $step * $i);
            $item['x'] = round($cx + $radius * cos($angle), 1);
            $item['y'] = round($cy + $radius * sin($angle), 1);

            return $item;
        }, $items, array_keys($items));
    };

    $innerNodes = $place($inner, (float) $innerRadius, (float) $innerSpan);
    $outerNodes = $place($outer, (float) $outerRadius, (float) $outerSpan);

    // The rings are drawn at the same radii the nodes sit on.
    $ringBox = ((float) $outerRadius + 70) * 2;
@endphp
<nav {{ $attributes->merge(['class' => 'o-arc']) }} aria-label="Modules" style="min-height:{{ $arcHeight }}px">
    <svg class="o-arc__rings" viewBox="0 0 {{ $ringBox }} {{ $ringBox }}" aria-hidden="true"
         style="width:{{ $ringBox }}px;height:{{ $ringBox }}px;left:{{ -$ringBox / 2 }}px;top:{{ $cy }}px;transform:translateY(-50%)">
        <circle cx="{{ $ringBox / 2 }}" cy="{{ $ringBox / 2 }}" r="{{ $innerRadius }}"/>
        <circle cx="{{ $ringBox / 2 }}" cy="{{ $ringBox / 2 }}" r="{{ $outerRadius }}" class="dash"/>
    </svg>

    <div class="o-arc__core" style="top:{{ $cy }}px">
        {{ $core ?? '' }}
        <div class="o-arc__dots"><i></i><i></i><i></i></div>
    </div>

    @foreach ([$innerNodes, $outerNodes] as $ring)
        @foreach ($ring as $node)
            @php $active = ($node['active'] ?? false) || (($node['key'] ?? null) !== null && $node['key'] === $current); @endphp
            <a class="o-arc__node" href="{{ $node['href'] ?? '#' }}"
               style="left:{{ $node['x'] }}px;top:{{ $node['y'] }}px"
               @if ($active) data-active="true" aria-current="page" @endif>
                <span class="o-arc__dot">
                    <x-orbit.icon :name="$node['icon'] ?? 'grid'" :size="17" />
                    @if (! empty($node['count']))
                        <span class="o-count">{{ $node['count'] }}</span>
                    @endif
                </span>
                <span class="o-arc__label">{{ $node['label'] ?? '' }}</span>
            </a>
        @endforeach
    @endforeach

    @isset($next)
        {{ $next }}
    @endisset
</nav>
