@props(['type', 'seats' => 0, 'w' => 96, 'h' => 96])

@php
    // Chair marks (top-down): small squares. Positions computed per style.
    $chair = 10; // px
    $chairs = [];

    $roundChairs = function (int $n, float $rx, float $ry) use ($w, $h, $chair) {
        $out = [];
        for ($i = 0; $i < $n; $i++) {
            $a = 2 * M_PI * $i / max($n, 1) - M_PI / 2;
            $out[] = [$w / 2 + $rx * cos($a) - $chair / 2, $h / 2 + $ry * sin($a) - $chair / 2];
        }

        return $out;
    };
    $rowChairs = function (int $n, float $y, float $x0, float $x1) use ($chair) {
        $out = [];
        $step = $n > 1 ? ($x1 - $x0) / ($n - 1) : 0;
        for ($i = 0; $i < $n; $i++) {
            $out[] = [$x0 + $i * $step - $chair / 2, $y - $chair / 2];
        }

        return $out;
    };

    $table = null; // [x,y,w,h,radius]
    switch ($type) {
        case 'round':
        case 'banquet':
            $r = min($w, $h) / 2 - 18;
            $table = [$w / 2 - $r, $h / 2 - $r, $r * 2, $r * 2, '50%'];
            $chairs = $roundChairs($seats, $r + 8, $r + 8);
            break;
        case 'crescent':
            $table = [10, $h - 26, $w - 20, 16, '0 0 40px 40px'];
            $chairs = $rowChairs($seats, $h - 34, 20, $w - 20);
            break;
        case 'boardroom':
        case 'table':
            $table = [14, $h / 2 - 14, $w - 28, 28, '10px'];
            if ($seats > 0) {
                $top = $rowChairs((int) ceil($seats / 2), $h / 2 - 24, 24, $w - 24);
                $bot = $rowChairs((int) floor($seats / 2), $h / 2 + 16, 24, $w - 24);
                $chairs = array_merge($top, $bot);
            }
            break;
        case 'ushape':
            // three bars forming a U
            $table = [22, 14, 16, $h - 28, '8px']; // left
            $chairs = array_merge(
                $rowChairs((int) round($seats * 0.35), $h / 2, 44, 44), // left inner
                $rowChairs((int) round($seats * 0.3), 24, 60, $w - 24),  // top inner
                $rowChairs((int) round($seats * 0.35), $h / 2, $w - 22, $w - 22),
            );
            break;
        case 'classroom':
            // grid of desks
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
        case 'chair':
            $chairs = [[$w / 2 - $chair / 2, $h / 2 - $chair / 2]];
            break;
    }
@endphp

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
        <div style="position:absolute; left:{{ round($cx, 1) }}px; top:{{ round($cy, 1) }}px; width:{{ $chair }}px; height:{{ $chair }}px; border-radius:3px; background:#0B1F3A;"></div>
    @endforeach
</div>
