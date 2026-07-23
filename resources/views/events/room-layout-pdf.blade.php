<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        use Illuminate\Support\Str;

        $navy = $theme['primary'] ?? '#0B1F3A';
        $gold = $theme['accent'] ?? '#D4AF37';

        // ── Drawing sheet geometry ──────────────────────────────────────────────
        // A4 landscape @96dpi ≈ 1123×794. Masthead + footer are fixed bands; the
        // plan and inspector share the body. The whole 960×560 builder space is
        // mapped UNIFORMLY into an inset box so proportions (and circles) hold and
        // there's a clean margin around the floor for dimension lines.
        $CW = 772;                 // plan drawing width (px)
        $CH = 556;                 // plan drawing height (px) — fills the sheet
        $PAD = 32;                 // dimension-line margin inside the plan

        $roomW = is_numeric($room->width_m) && (float) $room->width_m > 0 ? (float) $room->width_m : null;
        $roomL = is_numeric($room->length_m) && (float) $room->length_m > 0 ? (float) $room->length_m : null;
        $scale960 = $roomW && $roomL ? min(960 / $roomW, 560 / $roomL) : null;

        $k = min(($CW - 2 * $PAD) / 960, ($CH - 2 * $PAD) / 560);   // uniform 960-space → paper
        $mapOffX = $PAD + (($CW - 2 * $PAD) - 960 * $k) / 2;
        $mapOffY = $PAD + (($CH - 2 * $PAD) - 560 * $k) / 2;
        $pk = ($scale960 ?? 12) * $k;                                // px per metre on paper

        $offX960 = $scale960 ? (960 - $roomW * $scale960) / 2 : 0;
        $offY960 = $scale960 ? (560 - $roomL * $scale960) / 2 : 0;
        $venX = $mapOffX + $offX960 * $k;
        $venY = $mapOffY + $offY960 * $k;
        $venW = $scale960 ? $roomW * $pk : $CW - 2 * $PAD;
        $venH = $scale960 ? $roomL * $pk : $CH - 2 * $PAD;
        $gridPx = $pk;

        $fmtM = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
        $presets = \App\Models\EventRoom::LAYOUT_PRESETS;

        // ── Inspector figures ──
        $area = $roomW && $roomL ? $roomW * $roomL : null;
        $seatTotal = $room->seatCount();
        $tablesCount = collect($elements)->filter(fn ($e) => in_array($e['type'] ?? '', ['table', 'round', 'boardroom', 'banquet', 'seatblock'], true))->count();
        $pieces = count($elements);
        $ratio = $scale960 ? max(1, (int) round(3779.5 / $pk / 5) * 5) : null;   // 96dpi → 1:N (rounded to 5)

        $arrLabels = ['theater' => 'Theatre rows', 'classroom' => 'Classroom', 'banquet' => 'Banquet rounds', 'utables' => 'U-shape (tables)', 'ushape' => 'U-shape', 'perimeter' => 'Perimeter', 'grid' => 'Grid seating', 'circle' => 'Circle', 'boardroom' => 'Boardroom'];
        $breakdown = [];
        foreach ($elements as $e) {
            $lbl = ($e['type'] ?? '') === 'seatblock'
                ? ($arrLabels[$e['arr'] ?? 'theater'] ?? Str::title($e['arr'] ?? 'Seating'))
                : ($presets[$e['type']][0] ?? Str::title($e['type'] ?? 'Item'));
            $breakdown[$lbl] ??= ['count' => 0, 'seats' => 0];
            $breakdown[$lbl]['count']++;
            $breakdown[$lbl]['seats'] += (int) ($e['seats'] ?? 0);
        }
        $equip = $room->equipmentLines();
    @endphp
    <style>
        @page { margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; }

        /* ── body grid ── */
        .body { margin-top: 96px; height: 668px; padding: 16px 22px 0; }
        .cols { display: table; width: 100%; table-layout: fixed; }
        .cols > div { display: table-cell; vertical-align: top; }
        .gut { width: 20px; }
        .side { width: 264px; }

        /* ── plan card ── */
        .plan { position: relative; border: 1.5px solid {{ $navy }}; border-radius: 7px; background: #fff; padding: 8px; }
        .plan .tt { display: table; width: 100%; padding: 1px 4px 8px; }
        .plan .tt .a, .plan .tt .b { display: table-cell; }
        .plan .tt .b { text-align: right; }
        .plan .tt .a { font-size: 10px; font-weight: bold; letter-spacing: 2px; color: {{ $navy }}; text-transform: uppercase; }
        .plan .tt .b { font-size: 8.5px; font-weight: bold; letter-spacing: 1px; color: #64748B; }
        .plan .tt .b b { color: {{ $gold }}; }
        .canvas { position: relative; width: {{ $CW }}px; height: {{ $CH }}px; background: #FAFBFE; border: 1px solid #E7ECF3; border-radius: 4px; }
        .floor { position: absolute; border: 1.5px solid {{ $navy }}; background: #fff; }
        .gl-v { position: absolute; top: 0; width: 1px; background: #EAEFF6; }
        .gl-h { position: absolute; left: 0; height: 1px; background: #EAEFF6; }

        /* dimension lines (architectural tick style) */
        .dim-l { position: absolute; height: 1px; background: {{ $navy }}; }
        .dim-lt { position: absolute; width: 1px; background: {{ $navy }}; }
        .dim-tick-h { position: absolute; width: 1px; height: 7px; background: {{ $navy }}; }
        .dim-tick-v { position: absolute; height: 1px; width: 7px; background: {{ $navy }}; }
        .dim-cap { position: absolute; font-size: 8px; font-weight: bold; color: {{ $navy }}; background: #FAFBFE; padding: 0 4px; }
        .front { position: absolute; font-size: 6.5px; font-weight: bold; letter-spacing: 2px; color: #94A3B8; text-transform: uppercase; }
        .gnote { position: absolute; font-size: 6.5px; font-weight: bold; color: #94A3B8; background: #F1F5F9; padding: 1px 4px; border-radius: 2px; }
        .nodim { position: absolute; top: 50%; left: 50%; transform: translate(-50%,-50%); font-size: 10px; color: #94A3B8; }
        .name-pill { position: absolute; font-size: 6px; font-weight: bold; color: #334155; background: #fff; border: 1px solid #E2E8F0; border-radius: 3px; padding: 0 2px; white-space: nowrap; }

        /* blueprint corner ticks */
        .cm { position: absolute; }
        .cm .h { position: absolute; width: 11px; height: 2px; background: {{ $gold }}; }
        .cm .v { position: absolute; width: 2px; height: 11px; background: {{ $gold }}; }

        /* below-plan strip: scale bar + orientation */
        .strip { display: table; width: 100%; margin-top: 9px; }
        .strip .sa, .strip .sb { display: table-cell; vertical-align: middle; }
        .strip .sb { text-align: right; font-size: 7.5px; color: #94A3B8; letter-spacing: 1px; }
        .sbar { border-collapse: collapse; }
        .sbar td { height: 7px; border: 0.8px solid {{ $navy }}; padding: 0; }
        .sbar .fill { background: {{ $navy }}; }
        .sbar-lbls { font-size: 7px; color: #64748B; margin-top: 2px; }

        /* ── sidebar ── */
        .sec { border: 1px solid #E4E9F1; border-radius: 7px; margin-bottom: 10px; overflow: hidden; }
        .sec .cap { display: table; width: 100%; background: {{ $navy }}; padding: 6px 11px; }
        .sec .cap .ct, .sec .cap .cx { display: table-cell; vertical-align: middle; }
        .sec .cap .ct { color: {{ $gold }}; font-size: 7.5px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; }
        .sec .cap .cx { text-align: right; color: rgba(255,255,255,0.65); font-size: 7px; letter-spacing: 0.5px; }

        /* dimension hero + stat tiles */
        .dims { text-align: center; padding: 11px 8px 9px; border-bottom: 1px solid #EEF2F8; }
        .dims .dn { font-size: 21px; font-weight: bold; color: {{ $navy }}; letter-spacing: 0.5px; }
        .dims .dl { font-size: 7px; color: #94A3B8; text-transform: uppercase; letter-spacing: 1.5px; margin-top: 3px; }
        .tiles { display: table; width: 100%; table-layout: fixed; }
        .tiles .row { display: table-row; }
        .tiles .t { display: table-cell; width: 50%; padding: 9px 6px; text-align: center; border-right: 1px solid #EEF2F8; border-bottom: 1px solid #EEF2F8; }
        .tiles .t.re { border-right: none; }
        .tiles .t.be { border-bottom: none; }
        .tiles .tn { font-size: 17px; font-weight: bold; color: {{ $navy }}; }
        .tiles .tn small { font-size: 8px; font-weight: normal; color: #94A3B8; }
        .tiles .tl { font-size: 6.5px; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8; margin-top: 3px; }
        .util { padding: 9px 11px; border-top: 1px solid #EEF2F8; }
        .util .ur { display: table; width: 100%; }
        .util .ur .a, .util .ur .b { display: table-cell; font-size: 8px; }
        .util .ur .a { color: #64748B; text-transform: uppercase; letter-spacing: 0.5px; }
        .util .ur .b { text-align: right; font-weight: bold; color: {{ $navy }}; }
        .ubar { height: 7px; border-radius: 4px; background: #EDF1F7; margin-top: 5px; overflow: hidden; }
        .ubar > div { height: 7px; background: {{ $gold }}; }

        /* breakdown mini bar-chart */
        .bdrow { padding: 6px 11px; border-bottom: 1px solid #F4F7FB; }
        .bdrow.le { border-bottom: none; }
        .bdtop { display: table; width: 100%; }
        .bdtop .a, .bdtop .b { display: table-cell; vertical-align: bottom; }
        .bdtop .a { font-size: 8.5px; font-weight: bold; color: #334155; }
        .bdtop .b { text-align: right; font-size: 8px; font-weight: bold; color: {{ $navy }}; white-space: nowrap; }
        .bdbar { height: 4px; border-radius: 3px; background: #EDF1F7; margin-top: 4px; overflow: hidden; }
        .bdbar > div { height: 4px; background: {{ $navy }}; }

        /* legend */
        .lg { display: table; width: 100%; padding: 7px 11px; }
        .lg .li { display: table-row; }
        .lg .sym, .lg .txt { display: table-cell; vertical-align: middle; padding: 2.5px 0; }
        .lg .sym { width: 24px; }
        .lg .txt { font-size: 8.5px; color: #334155; }

        /* title block */
        .tb { width: 100%; border-collapse: collapse; }
        .tb td { font-size: 7.5px; padding: 5px 9px; border: 1px solid #E4E9F1; }
        .tb .k { color: #94A3B8; text-transform: uppercase; letter-spacing: 0.5px; width: 40px; }
        .tb .v { font-weight: bold; color: {{ $navy }}; }
    </style>
</head>
<body>
    {{-- ═══ masthead ═══ --}}
    <x-pdf-header fixed :navy="$navy" :gold="$gold"
        eyebrow="Elite Business Hub · Floor Plan"
        :title="$room->name"
        :subtitle="$event->name.' · '.str($room->type)->replace('_', ' ')->title()"
        :chips="[
            ['n' => $roomW && $roomL ? $fmtM($roomW).'×'.$fmtM($roomL) : '—', 'l' => 'Metres'],
            ['n' => $area ? $fmtM($area) : '—', 'l' => 'm² Floor'],
            ['n' => (string) $seatTotal, 'l' => 'Seats'],
        ]" />

    {{-- ═══ body ═══ --}}
    <div class="body">
        <div class="cols">
            {{-- ── plan ── --}}
            <div>
                <div class="plan">
                    {{-- blueprint corner ticks --}}
                    <div class="cm" style="top:-1px; left:-1px;"><div class="h" style="top:0;left:0;"></div><div class="v" style="top:0;left:0;"></div></div>
                    <div class="cm" style="top:-1px; right:-1px;"><div class="h" style="top:0;right:0;"></div><div class="v" style="top:0;right:0;"></div></div>
                    <div class="cm" style="bottom:-1px; left:-1px;"><div class="h" style="bottom:0;left:0;"></div><div class="v" style="bottom:0;left:0;"></div></div>
                    <div class="cm" style="bottom:-1px; right:-1px;"><div class="h" style="bottom:0;right:0;"></div><div class="v" style="bottom:0;right:0;"></div></div>

                    <div class="tt">
                        <div class="a">Floor Plan</div>
                        <div class="b">@if ($ratio)SCALE <b>1 : {{ $ratio }}</b> &nbsp;·&nbsp; @endif TRUE TO SCALE</div>
                    </div>

                    <div class="canvas">
                        @if ($scale960)
                            {{-- venue floor + 1 m grid --}}
                            <div class="floor" style="left:{{ round($venX) }}px; top:{{ round($venY) }}px; width:{{ round($venW) }}px; height:{{ round($venH) }}px;">
                                @for ($gx = $gridPx; $gx < $venW - 0.5; $gx += $gridPx)
                                    <div class="gl-v" style="left:{{ round($gx) }}px; height:{{ round($venH) }}px;"></div>
                                @endfor
                                @for ($gy = $gridPx; $gy < $venH - 0.5; $gy += $gridPx)
                                    <div class="gl-h" style="top:{{ round($gy) }}px; width:{{ round($venW) }}px;"></div>
                                @endfor
                            </div>

                            {{-- width dimension line (above floor) --}}
                            @php $dy = round($venY) - 15; @endphp
                            <div class="dim-l" style="left:{{ round($venX) }}px; top:{{ $dy + 7 }}px; width:{{ round($venW) }}px;"></div>
                            <div class="dim-tick-h" style="left:{{ round($venX) }}px; top:{{ $dy + 4 }}px;"></div>
                            <div class="dim-tick-h" style="left:{{ round($venX + $venW) }}px; top:{{ $dy + 4 }}px;"></div>
                            <div class="dim-cap" style="left:{{ round($venX + $venW / 2 - 22) }}px; top:{{ $dy + 1 }}px;">{{ $fmtM($roomW) }} m</div>

                            {{-- length dimension line (left of floor) --}}
                            @php $dx = round($venX) - 15; @endphp
                            <div class="dim-lt" style="left:{{ $dx + 7 }}px; top:{{ round($venY) }}px; height:{{ round($venH) }}px;"></div>
                            <div class="dim-tick-v" style="left:{{ $dx + 4 }}px; top:{{ round($venY) }}px;"></div>
                            <div class="dim-tick-v" style="left:{{ $dx + 4 }}px; top:{{ round($venY + $venH) }}px;"></div>
                            <div class="dim-cap" style="left:{{ max(1, $dx - 18) }}px; top:{{ round($venY + $venH / 2 - 6) }}px; background:#FAFBFE;">{{ $fmtM($roomL) }} m</div>

                            <div class="front" style="left:{{ round($venX) }}px; top:{{ round($venY) + 3 }}px; width:{{ round($venW) }}px; text-align:center;">▲ &nbsp;Front of room&nbsp; ▲</div>
                            <div class="gnote" style="right:6px; bottom:6px;">1 grid = 1 m</div>
                        @else
                            <div class="nodim">Room dimensions not set — plan shown unscaled. Add width &amp; length to draw to scale.</div>
                        @endif

                        {{-- placed furniture --}}
                        @foreach ($elements as $el)
                            @php
                                $cx = $mapOffX + ($el['x'] ?? 480) * $k;
                                $cy = $mapOffY + ($el['y'] ?? 280) * $k;
                                $rot = $el['rot'] ?? 0;
                            @endphp
                            @if (($el['type'] ?? '') === 'seatblock')
                                @php $geo = \App\Models\EventRoom::seatChairs($el, $pk); @endphp
                                <div style="position:absolute; left:{{ round($cx - $geo['w'] / 2) }}px; top:{{ round($cy - $geo['h'] / 2) }}px; width:{{ $geo['w'] }}px; height:{{ $geo['h'] }}px; transform: rotate({{ $rot }}deg); transform-origin:center;">
                                    @foreach ($geo['desks'] as [$dx2, $dy2, $dw, $dh])
                                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $dx2 - $dw / 2 }}px; top:{{ $geo['h'] / 2 + $dy2 - $dh / 2 }}px; width:{{ $dw }}px; height:{{ $dh }}px; background:#E8EDF4; border:0.5px solid #C6D1E0;"></div>
                                    @endforeach
                                    @foreach ($geo['tables'] as [$tx, $ty, $td])
                                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $tx - $td / 2 }}px; top:{{ $geo['h'] / 2 + $ty - $td / 2 }}px; width:{{ $td }}px; height:{{ $td }}px; border:1px solid #8DA0BC; border-radius:50%; background:#EEF3FA;"></div>
                                    @endforeach
                                    @foreach ($geo['rects'] ?? [] as [$rx, $ry, $rw, $rh])
                                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $rx - $rw / 2 }}px; top:{{ $geo['h'] / 2 + $ry - $rh / 2 }}px; width:{{ $rw }}px; height:{{ $rh }}px; background:#E8EDF4; border:1px solid #C6D1E0;"></div>
                                    @endforeach
                                    @php $cf = max(2.2, round($geo['chairPx'] * 0.42, 1)); @endphp
                                    @foreach ($geo['chairs'] as [$cX, $cY, $cn])
                                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $cX - $geo['chairPx'] / 2 }}px; top:{{ $geo['h'] / 2 + $cY - $geo['chairPx'] / 2 }}px; width:{{ $geo['chairPx'] }}px; height:{{ $geo['chairPx'] }}px; background:{{ $navy }}; color:#fff; font-size:{{ $cf }}px; line-height:{{ $geo['chairPx'] }}px; text-align:center; overflow:hidden;">{{ $cn }}</div>
                                    @endforeach
                                    @foreach ($geo['labels'] ?? [] as [$lx, $ly, $lt])
                                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $lx - 12 }}px; top:{{ $geo['h'] / 2 + $ly - 4 }}px; width:11px; text-align:right; font-size:5px; font-weight:bold; color:#64748B;">{{ $lt }}</div>
                                    @endforeach
                                </div>
                                @if (! empty($el['name']))
                                    <div class="name-pill" style="left:{{ round($cx - $geo['w'] / 2) }}px; top:{{ round($cy - $geo['h'] / 2 - 9) }}px;">{{ $el['name'] }}</div>
                                @endif
                            @else
                                @php $ew = ($el['w'] ?? 96) * $k; $eh = ($el['h'] ?? 96) * $k; @endphp
                                <div style="position:absolute; left:{{ round($cx - $ew / 2) }}px; top:{{ round($cy - $eh / 2) }}px; transform: rotate({{ $rot }}deg); transform-origin:center;">
                                    <x-layout-element :type="$el['type']" :seats="$el['seats'] ?? 0" :w="round($ew)" :h="round($eh)" :scale="$pk" />
                                </div>
                                @if (! empty($el['name']))
                                    <div class="name-pill" style="left:{{ round($cx - $ew / 2) }}px; top:{{ round($cy - $eh / 2 - 9) }}px;">{{ $el['name'] }}</div>
                                @endif
                            @endif
                        @endforeach
                    </div>

                    {{-- scale bar + orientation --}}
                    <div class="strip">
                        <div class="sa">
                            @if ($scale960)
                                <table class="sbar"><tr>
                                    @for ($i = 0; $i < 5; $i++)
                                        <td class="{{ $i % 2 ? 'fill' : '' }}" style="width:{{ round($pk) }}px;"></td>
                                    @endfor
                                </tr></table>
                                <div class="sbar-lbls">0&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;5 metres</div>
                            @endif
                        </div>
                        <div class="sb">Drawing not for construction · indicative layout</div>
                    </div>
                </div>
            </div>

            <div class="gut"></div>

            {{-- ── inspector sidebar ── --}}
            @php
                $capUsed = $room->capacity && $seatTotal ? min(100, (int) round($seatTotal / $room->capacity * 100)) : null;
                $maxSeats = max(1, collect($breakdown)->max('seats') ?? 1);
            @endphp
            <div class="side">
                <div class="sec">
                    <div class="cap"><div class="ct">Floor Inspector</div><div class="cx">Room summary</div></div>
                    <div class="dims">
                        <div class="dn">{{ $roomW && $roomL ? $fmtM($roomW).' × '.$fmtM($roomL).' m' : 'Not set' }}</div>
                        <div class="dl">{{ $area ? 'Footprint · '.$fmtM($area).' m²' : 'Footprint' }}</div>
                    </div>
                    <div class="tiles">
                        <div class="row">
                            <div class="t"><div class="tn">{{ $seatTotal }}</div><div class="tl">Seats laid out</div></div>
                            <div class="t re"><div class="tn">{{ $area && $seatTotal ? number_format($seatTotal / $area, 2) : '—' }}</div><div class="tl">Seats / m²</div></div>
                        </div>
                        <div class="row">
                            <div class="t be"><div class="tn">{{ $tablesCount }}</div><div class="tl">Tables &amp; blocks</div></div>
                            <div class="t re be"><div class="tn">{{ $pieces }}</div><div class="tl">Pieces on plan</div></div>
                        </div>
                    </div>
                    @if ($capUsed !== null)
                        <div class="util">
                            <div class="ur"><div class="a">Capacity used</div><div class="b">{{ $seatTotal }} / {{ number_format($room->capacity) }} · {{ $capUsed }}%</div></div>
                            <div class="ubar"><div style="width: {{ $capUsed }}%"></div></div>
                        </div>
                    @endif
                </div>

                @if (! empty($breakdown))
                    <div class="sec">
                        <div class="cap"><div class="ct">Layout Breakdown</div><div class="cx">{{ $seatTotal }} seats</div></div>
                        @foreach ($breakdown as $lbl => $b)
                            <div class="bdrow {{ $loop->last ? 'le' : '' }}">
                                <div class="bdtop"><div class="a">{{ $b['count'] }}× {{ $lbl }}</div><div class="b">{{ $b['seats'] > 0 ? $b['seats'].' seats' : '—' }}</div></div>
                                @if ($b['seats'] > 0)
                                    <div class="bdbar"><div style="width: {{ max(4, (int) round($b['seats'] / $maxSeats * 100)) }}%"></div></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                @endif

                <div class="sec">
                    <div class="cap"><div class="ct">Legend</div><div class="cx">Drawn to scale</div></div>
                    <div class="lg">
                        <div class="li"><div class="sym"><div style="width:11px; height:11px; background:{{ $navy }};"></div></div><div class="txt">Chair — 0.6 × 0.6 m</div></div>
                        <div class="li"><div class="sym"><div style="width:16px; height:9px; background:#EEF3FA; border:1px solid #8DA0BC;"></div></div><div class="txt">Table (e.g. 1.8 × 0.6 m)</div></div>
                        <div class="li"><div class="sym"><div style="width:12px; height:12px; border-radius:50%; background:#EEF3FA; border:1px solid #8DA0BC;"></div></div><div class="txt">Round table</div></div>
                        <div class="li"><div class="sym"><div style="width:16px; height:8px; background:{{ $navy }};"></div></div><div class="txt">Stage / podium</div></div>
                        <div class="li"><div class="sym"><div style="width:12px; height:12px; background:#334155;"></div></div><div class="txt">Translation booth</div></div>
                    </div>
                </div>

                @if ($equip->isNotEmpty())
                    <div class="sec">
                        <div class="cap"><div class="ct">Equipment</div><div class="cx">See AV sheet</div></div>
                        <div class="util" style="border-top:none;">
                            <div class="ur"><div class="a">{{ $equip->count() }} {{ Str::plural('line', $equip->count()) }} requested</div><div class="b">{{ $equip->sum('qty') }} units</div></div>
                        </div>
                    </div>
                @endif

                <table class="tb">
                    <tr><td class="k">Scale</td><td class="v">{{ $ratio ? '1 : '.$ratio : '—' }}</td><td class="k">Units</td><td class="v">Metres</td></tr>
                    <tr><td class="k">Date</td><td class="v">{{ now()->format('d M Y') }}</td><td class="k">Dwg</td><td class="v">FP-{{ $room->id }}</td></tr>
                    <tr><td class="k">Room</td><td class="v" colspan="3">{{ Str::limit($room->name, 40) }}</td></tr>
                </table>
            </div>
        </div>
    </div>

    {{-- ═══ footer ═══ --}}
    <x-pdf-footer fixed :navy="$navy" :gold="$gold" :sheet="'FP-'.$room->id" />
</body>
</html>
