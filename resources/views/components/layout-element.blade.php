@props(['type', 'seats' => 0, 'w' => 96, 'h' => 96, 'scale' => null])

@php
    // When $scale (px per metre) is passed, the element's w/h ARE its real
    // footprint, so furniture is drawn to fill the box — a 60cm chair covers
    // exactly 60cm of the 1m grid. Without a scale (palette icons) we fall back
    // to a fixed, pretty representation.
    $chairPx = $scale ? max(5.0, round(0.6 * $scale)) : 10;   // a chair is 0.6m
    $chairR = $scale ? max(1.0, round(0.06 * $scale)) : 3;

    $chairs = [];
    $roundChairs = function (int $n, float $rx, float $ry) use ($w, $h, $chairPx) {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = 2 * M_PI * $i / max($n, 1) - M_PI / 2;
            $out[] = [$w / 2 + $rx * cos($a) - $chairPx / 2, $h / 2 + $ry * sin($a) - $chairPx / 2];
        }

        return $out;
    };
    $rowChairs = function (int $n, float $y, float $x0, float $x1) use ($chairPx) {
        $out = [];
        $step = $n > 1 ? ($x1 - $x0) / ($n - 1) : 0;
        for ($i = 0; $i < $n; $i++) {
            $out[] = [$x0 + $i * $step - $chairPx / 2, $y - $chairPx / 2];
        }

        return $out;
    };

    $staging = in_array($type, ['stage', 'screen', 'podium', 'entrance', 'booth'], true);
    $accurate = $scale !== null;
    $table = null; // [x,y,w,h,radius]

    switch ($type) {
        case 'chair':
            // The whole box IS the chair footprint. Nothing else to draw.
            break;

        case 'table':
            // Rectangular tabletop fills the real box (e.g. 180×60cm).
            $table = [0, 0, $w, $h, $scale ? max(2, round(0.03 * $scale)).'px' : '6px'];
            if ($seats > 0) {
                $off = $chairPx * 0.75;
                $chairs = array_merge(
                    $rowChairs((int) ceil($seats / 2), -$off, $chairPx, $w - $chairPx),
                    $rowChairs((int) floor($seats / 2), $h + $off, $chairPx, $w - $chairPx),
                );
            }
            break;

        case 'round':
        case 'banquet':
            // Circular tabletop fills min(w,h); chairs ring it at real size.
            $d = min($w, $h);
            $r = $d / 2;
            $table = [$w / 2 - $r, $h / 2 - $r, $d, $d, '50%'];
            $ring = $r + $chairPx * 0.7;
            $chairs = $roundChairs($seats, $ring, $ring);
            break;

        case 'crescent':
            $table = [10, $h - 26, $w - 20, 16, '0 0 40px 40px'];
            $chairs = $rowChairs($seats, $h - 34, 20, $w - 20);
            break;
        case 'boardroom':
            $table = [14, $h / 2 - 14, $w - 28, 28, '10px'];
            if ($seats > 0) {
                $chairs = array_merge(
                    $rowChairs((int) ceil($seats / 2), $h / 2 - 24, 24, $w - 24),
                    $rowChairs((int) floor($seats / 2), $h / 2 + 16, 24, $w - 24),
                );
            }
            break;
        case 'ushape':
            $table = [22, 14, 16, $h - 28, '8px'];
            $chairs = array_merge(
                $rowChairs((int) round($seats * 0.35), $h / 2, 44, 44),
                $rowChairs((int) round($seats * 0.3), 24, 60, $w - 24),
                $rowChairs((int) round($seats * 0.35), $h / 2, $w - 22, $w - 22),
            );
            break;
        case 'classroom':
            for ($row = 0; $row < 3; $row++) {
                for ($col = 0; $col < 4; $col++) {
                    $chairs[] = [16 + $col * (($w - 32) / 3.5), 20 + $row * (($h - 40) / 2.5)];
                }
            }
            break;
        case 'theater':
            for ($row = 0; $row < 4; $row++) {
                foreach ($rowChairs(6, 18 + $row * (($h - 30) / 3.2), 16, $w - 16) as $c) {
                    $chairs[] = $c;
                }
            }
            break;
    }

    $stageStyles = [
        'stage' => ['bg' => '#0B1F3A', 'fg' => '#D4AF37', 'rad' => '10px', 'label' => 'STAGE'],
        'screen' => ['bg' => '#1E293B', 'fg' => '#E2E8F0', 'rad' => '4px', 'label' => 'SCREEN'],
        'podium' => ['bg' => '#0B1F3A', 'fg' => '#D4AF37', 'rad' => '6px 6px 3px 3px', 'label' => '◆'],
        'entrance' => ['bg' => '#EEF2F8', 'fg' => '#0B1F3A', 'rad' => '4px', 'label' => 'ENTRY'],
        'booth' => ['bg' => '#334155', 'fg' => '#E2E8F0', 'rad' => '4px', 'label' => 'TRANS. BOOTH'],
    ];
@endphp

@if ($staging)
    @php $s = $stageStyles[$type]; @endphp
    <div style="width:{{ $w }}px; height:{{ $h }}px; border-radius:{{ $s['rad'] }}; background:{{ $s['bg'] }}; {{ $type === 'entrance' ? 'border:1.5px dashed #94A3B8;' : '' }} color:{{ $s['fg'] }}; display:flex; align-items:center; justify-content:center; font-size:{{ $type === 'podium' ? 12 : 9 }}px; font-weight:bold; letter-spacing:2px; text-align:center; overflow:hidden;">
        {{ $s['label'] }}
    </div>
@elseif ($type === 'chair')
    {{-- A chair, drawn at its true footprint: seat fills the box, a back bar on one edge. --}}
    <div style="position:relative; width:{{ $w }}px; height:{{ $h }}px;">
        <div style="position:absolute; inset:{{ $accurate ? max(1, round($w * 0.08)) : 3 }}px; border-radius:{{ $accurate ? max(2, round($w * 0.16)).'px' : '4px' }}; background:#334155;"></div>
        <div style="position:absolute; left:{{ $accurate ? max(1, round($w * 0.08)) : 3 }}px; right:{{ $accurate ? max(1, round($w * 0.08)) : 3 }}px; top:{{ $accurate ? max(1, round($h * 0.08)) : 3 }}px; height:{{ $accurate ? max(2, round($h * 0.22)) : 4 }}px; border-radius:{{ $accurate ? max(2, round($w * 0.12)).'px' : '3px' }}; background:#0B1F3A;"></div>
    </div>
@else
    <div style="position:relative; width:{{ $w }}px; height:{{ $h }}px;">
        @if ($table)
            @php [$tx, $ty, $tw, $th, $trad] = $table; @endphp
            <div style="position:absolute; left:{{ $tx }}px; top:{{ $ty }}px; width:{{ $tw }}px; height:{{ $th }}px; border-radius:{{ $trad }}; background:#EEF2F8; border:1.5px solid #94A3B8;"></div>
            @if ($type === 'ushape')
                <div style="position:absolute; left:44px; top:14px; width:{{ $w - 66 }}px; height:16px; border-radius:8px; background:#EEF2F8; border:1.5px solid #94A3B8;"></div>
                <div style="position:absolute; left:{{ $w - 38 }}px; top:14px; width:16px; height:{{ $h - 28 }}px; border-radius:8px; background:#EEF2F8; border:1.5px solid #94A3B8;"></div>
            @endif
        @endif

        @foreach ($chairs as [$cx, $cy])
            <div style="position:absolute; left:{{ round($cx, 1) }}px; top:{{ round($cy, 1) }}px; width:{{ $chairPx }}px; height:{{ $chairPx }}px; border-radius:{{ $chairR }}px; background:#0B1F3A;"></div>
        @endforeach
    </div>
@endif
