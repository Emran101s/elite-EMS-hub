@props([
    // inner/outer: [['label'=>'Tasks','icon'=>'task','href'=>'…','count'=>17,'active'=>false], …]
    'inner' => [],       // the 5 modules touched daily
    'outer' => [],       // structure — further out, so quieter
    'current' => null,   // key matched against each item's 'key'
    'core' => null,      // <x-slot:core> for the disc's contents
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
    $cy = 235.0;

    $place = function (array $items, float $radius, float $step) use ($cx, $cy) {
        $n = count($items);
        if ($n === 0) {
            return [];
        }
        // Fan symmetrically about the horizontal: 5 items → -60,-30,0,30,60.
        $first = -$step * ($n - 1) / 2;

        return array_map(function ($item, $i) use ($cx, $cy, $radius, $step, $first) {
            $angle = deg2rad($first + $step * $i);
            $item['x'] = round($cx + $radius * cos($angle), 1);
            $item['y'] = round($cy + $radius * sin($angle), 1);

            return $item;
        }, $items, array_keys($items));
    };

    $innerNodes = $place($inner, 150.0, 30.0);
    $outerNodes = $place($outer, 190.0, 32.0);
@endphp
<nav {{ $attributes->merge(['class' => 'o-arc']) }} aria-label="Modules">
    <svg class="o-arc__rings" viewBox="0 0 520 520" aria-hidden="true">
        <circle cx="260" cy="260" r="150"/>
        <circle cx="260" cy="260" r="190" class="dash"/>
    </svg>

    <div class="o-arc__core">
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
