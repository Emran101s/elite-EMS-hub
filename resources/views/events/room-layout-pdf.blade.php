{{--
    A floor plan the way a venue expects to receive one.

    Sheet 1 is the drawing, and it is not a re-drawing: the plan inside the
    frame is the builder's own 960×560 coordinate space, its own element
    component and its own metre scale, wrapped in a single transform that fits
    it to paper. A table rotated 37° on screen is rotated 37° here, and nothing
    has to be kept in step by hand.

    Sheet 2 is the schedule — the hire, its days, and every piece of equipment
    with its rate, quantity and run. A plan sent to a venue without its
    equipment list is half a brief, and the half they ring back about.
--}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        use Illuminate\Support\Str;

        $navy = $theme['primary'] ?? '#0B1F3A';
        $gold = $theme['accent'] ?? '#D4AF37';

        $fmtM = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');

        // ── the builder's geometry, verbatim ────────────────────────────────
        $roomW = is_numeric($room->width_m) && (float) $room->width_m > 0 ? (float) $room->width_m : null;
        $roomL = is_numeric($room->length_m) && (float) $room->length_m > 0 ? (float) $room->length_m : null;
        $scale = $roomW && $roomL ? min(960 / $roomW, 560 / $roomL) : null;   // px per metre
        $venW = $scale ? round($roomW * $scale, 2) : 960;
        $venH = $scale ? round($roomL * $scale, 2) : 560;
        $offX = $scale ? round((960 - $venW) / 2, 2) : 0;
        $offY = $scale ? round((560 - $venH) / 2, 2) : 0;

        // A metre grid at a density that survives print — 1 m lines turn to
        // mush in a hall forty metres across.
        $gridM = $scale && $scale < 14 ? ($scale < 8 ? 5 : 2) : 1;
        $gridPx = $scale ? $scale * $gridM : 40;

        // ── fitting that space onto the sheet ──────────────────────────────
        // A4 landscape at 96 dpi. The plan is scaled as one piece, so every
        // proportion inside it survives; DIM is the margin the dimension lines
        // and their captions live in.
        $CANVAS_W = 1054; $CANVAS_H = 585; $DIM = 34;
        $k = round(min(($CANVAS_W - 2 * $DIM) / 960, ($CANVAS_H - 2 * $DIM) / 560), 4);
        $planW = 960 * $k; $planH = 560 * $k;
        $planX = round(($CANVAS_W - $planW) / 2, 2);
        $planY = round(($CANVAS_H - $planH) / 2, 2);
        // The venue rectangle in paper coordinates, for the dimension lines.
        $vx = round($planX + $offX * $k, 2);  $vy = round($planY + $offY * $k, 2);
        $vw = round($venW * $k, 2);           $vh = round($venH * $k, 2);

        // ── figures ────────────────────────────────────────────────────────
        $area = $roomW && $roomL ? $roomW * $roomL : null;
        $seatTotal = $room->seatCount();
        $pieces = count($elements);
        // 96 dpi → 1 : N, rounded to something a scale rule actually carries.
        $ratio = $scale ? max(1, (int) round(3779.5 / ($scale * $k) / 5) * 5) : null;
        $days = $room->chargedDays();

        $presets = \App\Models\EventRoom::LAYOUT_PRESETS;
        $arrLabels = ['theater' => 'Theatre rows', 'classroom' => 'Classroom', 'banquet' => 'Banquet rounds',
            'utables' => 'U-shape (tables)', 'ushape' => 'U-shape', 'perimeter' => 'Perimeter',
            'grid' => 'Grid seating', 'circle' => 'Circle', 'boardroom' => 'Boardroom'];

        $breakdown = [];
        foreach ($elements as $e) {
            $lbl = ($e['type'] ?? '') === 'seatblock'
                ? ($arrLabels[$e['arr'] ?? 'theater'] ?? Str::title($e['arr'] ?? 'Seating'))
                : ($presets[$e['type']][0] ?? Str::title($e['type'] ?? 'Item'));
            $breakdown[$lbl] ??= ['count' => 0, 'seats' => 0];
            $breakdown[$lbl]['count']++;
            $breakdown[$lbl]['seats'] += (int) ($e['seats'] ?? 0);
        }

        $reqs = collect($room->requirements ?? []);
        $reqTotal = $room->requirementsTotalCents();
        $equip = $room->equipmentLines();
        $sheetRef = 'FP-'.str_pad((string) $room->id, 3, '0', STR_PAD_LEFT);
    @endphp

    {{-- The builder's element component styles itself from the app's tokens,
         so the app's own CSS has to come with it or every piece renders grey. --}}
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4 landscape; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; width: 1122px; background: #fff; color: #0F172A;
               font-family: ui-sans-serif, system-ui, 'Helvetica Neue', Arial, sans-serif;
               -webkit-print-color-adjust: exact; print-color-adjust: exact; }

        .sheet { position: relative; width: 1122px; height: 793px; overflow: hidden; page-break-after: always; }
        .sheet:last-child { page-break-after: auto; }

        /* ── masthead ── */
        .mh { height: 64px; background: {{ $navy }}; color: #fff; padding: 0 24px; display: flex; align-items: center; gap: 16px; }
        .mh .eb { font-size: 7.5px; font-weight: 800; letter-spacing: 2.6px; text-transform: uppercase; color: {{ $gold }}; }
        .mh h1 { margin: 2px 0 0; font-size: 19px; font-weight: 800; letter-spacing: -0.2px; line-height: 1; }
        .mh .sub { margin-top: 3px; font-size: 9px; color: rgba(255,255,255,0.62); letter-spacing: 0.3px; }
        .mh .chips { margin-left: auto; display: flex; gap: 9px; }
        .mh .chip { min-width: 74px; padding: 5px 10px; border: 1px solid rgba(255,255,255,0.16);
                    border-radius: 7px; background: rgba(255,255,255,0.05); text-align: center; }
        .mh .chip b { display: block; color: #fff; font-size: 14px; font-weight: 800; line-height: 1.15; white-space: nowrap; }
        .mh .chip span { display: block; margin-top: 1px; font-size: 6.5px; font-weight: 700; letter-spacing: 1.3px;
                         text-transform: uppercase; color: rgba(255,255,255,0.5); }

        /* ── drawing frame ── */
        .frame { position: relative; margin: 14px 24px 0; height: 625px; border: 1.5px solid {{ $navy }};
                 border-radius: 6px; background: #fff; padding: 9px; }
        .frame .cap { display: flex; align-items: baseline; padding: 0 3px 7px; }
        .frame .cap .t { font-size: 9px; font-weight: 800; letter-spacing: 2.4px; text-transform: uppercase; color: {{ $navy }}; }
        .frame .cap .r { margin-left: auto; font-size: 8px; font-weight: 700; letter-spacing: 1.2px; color: #7C8AA0; }
        .frame .cap .r b { color: {{ $navy }}; }
        .corner { position: absolute; width: 13px; height: 13px; border: 0 solid {{ $gold }}; }

        .canvas { position: relative; width: {{ $CANVAS_W }}px; height: {{ $CANVAS_H }}px;
                  background: #FBFCFE; border: 1px solid #E7ECF3; border-radius: 3px; overflow: hidden; }
        .floor { position: absolute; border: 1.6px solid {{ $navy }}; background: #fff; border-radius: 2px; }

        /* the plan itself — builder coordinates, scaled as one piece */
        .plan { position: absolute; width: 960px; height: 560px; transform-origin: 0 0; }
        .piece { position: absolute; transform: translate(-50%, -50%); }
        .nameTag { position: absolute; left: 50%; bottom: 100%; transform: translateX(-50%); margin-bottom: 2px;
                   white-space: nowrap; border-radius: 3px; background: rgba(255,255,255,0.94); border: 1px solid #E2E8F0;
                   padding: 0 4px; font-size: 8px; font-weight: 700; color: #334155; }
        .seatTag { position: absolute; left: 50%; top: 100%; transform: translateX(-50%); margin-top: 2px;
                   white-space: nowrap; border-radius: 99px; background: {{ $navy }}; color: #fff;
                   padding: 1px 6px; font-size: 8px; font-weight: 700; }
        .seatNum { position: absolute; left: 50%; top: 50%; transform: translate(-50%, -50%);
                   font-size: 9px; font-weight: 800; color: {{ $navy }}; }

        /* dimension lines, architect's tick convention */
        .dh { position: absolute; height: 1px; background: {{ $navy }}; }
        .dv { position: absolute; width: 1px; background: {{ $navy }}; }
        .tk { position: absolute; background: {{ $navy }}; }
        .dcap { position: absolute; font-size: 8.5px; font-weight: 800; color: {{ $navy }};
                background: #FBFCFE; padding: 0 5px; letter-spacing: 0.3px; white-space: nowrap; }
        .note { position: absolute; font-size: 7px; font-weight: 700; letter-spacing: 1.1px;
                text-transform: uppercase; color: #94A3B8; }
        .nodim { position: absolute; left: 50%; top: 50%; transform: translate(-50%,-50%);
                 font-size: 11px; color: #94A3B8; text-align: center; line-height: 1.6; }

        /* ── title block: the strip a drawing is identified by ── */
        .tblock { position: absolute; left: 24px; right: 24px; bottom: 18px; height: 52px;
                  display: flex; border: 1px solid {{ $navy }}; border-radius: 5px; overflow: hidden; }
        .tblock .cell { padding: 7px 11px; border-right: 1px solid #DCE3ED; min-width: 0; }
        .tblock .cell:last-child { border-right: 0; }
        .tblock .k { font-size: 6.5px; font-weight: 800; letter-spacing: 1.4px; text-transform: uppercase; color: #94A3B8; }
        .tblock .v { margin-top: 3px; font-size: 11px; font-weight: 800; color: {{ $navy }};
                     white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .tblock .dark { background: {{ $navy }}; }
        .tblock .dark .k { color: rgba(255,255,255,0.55); }
        .tblock .dark .v { color: {{ $gold }}; }

        /* ── sheet 2 ── */
        .body2 { padding: 16px 24px 0; display: flex; gap: 16px; height: 700px; }
        .sec { border: 1px solid #E4E9F1; border-radius: 7px; overflow: hidden; margin-bottom: 12px; }
        .sec > h3 { margin: 0; background: {{ $navy }}; color: {{ $gold }}; padding: 7px 12px;
                    font-size: 7.5px; font-weight: 800; letter-spacing: 2px; text-transform: uppercase;
                    display: flex; align-items: center; }
        .sec > h3 em { margin-left: auto; font-style: normal; font-size: 7px; font-weight: 600;
                       letter-spacing: 0.6px; color: rgba(255,255,255,0.6); text-transform: none; }
        table.sch { width: 100%; border-collapse: collapse; }
        table.sch th { font-size: 6.5px; font-weight: 800; letter-spacing: 1.2px; text-transform: uppercase;
                       color: #94A3B8; text-align: left; padding: 7px 12px; border-bottom: 1px solid #E9EDF4; background: #F8FAFC; }
        table.sch td { font-size: 10px; padding: 7px 12px; border-bottom: 1px solid #F1F5F9; color: #334155; vertical-align: top; }
        table.sch tr:last-child td { border-bottom: 0; }
        table.sch .n { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
        table.sch .item { font-weight: 700; color: {{ $navy }}; }
        table.sch .idx { color: #B4BECD; font-variant-numeric: tabular-nums; width: 26px; }
        table.sch .tot { font-weight: 800; color: {{ $navy }}; }
        table.sch tr.sum td { background: #F8FAFC; border-top: 1.5px solid {{ $navy }}; border-bottom: 0;
                              font-weight: 800; color: {{ $navy }}; font-size: 11px; }
        .empty { padding: 22px 12px; text-align: center; font-size: 10px; color: #94A3B8; }
        .pill { display: inline-block; border-radius: 99px; padding: 1px 7px; font-size: 7.5px; font-weight: 800;
                letter-spacing: 0.4px; text-transform: uppercase; }
        .rail { width: 296px; flex: none; }
        .kv { display: flex; align-items: baseline; padding: 7px 12px; border-bottom: 1px solid #F1F5F9; font-size: 10px; }
        .kv:last-child { border-bottom: 0; }
        .kv .a { color: #64748B; }
        .kv .b { margin-left: auto; padding-left: 8px; font-weight: 800; color: {{ $navy }}; font-variant-numeric: tabular-nums; white-space: nowrap; }
        .kv.big { padding: 10px 12px; background: #F8FAFC; border-top: 1.5px solid {{ $navy }}; }
        .kv.big .a { font-weight: 800; color: {{ $navy }}; text-transform: uppercase; font-size: 8px; letter-spacing: 1.2px; }
        .kv.big .b { font-size: 15px; }
        .prose { padding: 9px 12px; font-size: 9.5px; color: #475569; line-height: 1.55; }
        .prose b { color: {{ $navy }}; }
        .lgd { padding: 9px 12px; }
        .lgd div { display: flex; align-items: center; gap: 9px; padding: 3px 0; font-size: 9.5px; color: #334155; }
        .lgd s { flex: none; text-decoration: none; }
        .bar { height: 5px; border-radius: 3px; background: #EDF1F7; overflow: hidden; margin-top: 4px; }
        .bar > i { display: block; height: 5px; background: {{ $navy }}; }
        .foot2 { position: absolute; left: 24px; right: 24px; bottom: 16px; display: flex;
                 border-top: 1px solid #E4E9F1; padding-top: 8px; font-size: 8px; color: #94A3B8; letter-spacing: 0.4px; }
    </style>
</head>
<body>

{{-- ═══════════════════ SHEET 1 · the drawing ═══════════════════ --}}
<div class="sheet">
    <div class="mh">
        <div style="min-width:0;">
            <div class="eb">Elite Business Hub · Floor Plan</div>
            <h1>{{ $room->name }}</h1>
            <div class="sub">{{ $event->name }} · {{ str($room->type)->replace('_', ' ')->title() }}
                @if ($event->starts_on) · {{ $event->starts_on->format('d M Y') }}@endif
                · held {{ $days }} {{ str('day')->plural($days) }}</div>
        </div>
        <div class="chips">
            <div class="chip"><b>{{ $roomW && $roomL ? $fmtM($roomW).'×'.$fmtM($roomL) : '—' }}</b><span>Metres</span></div>
            <div class="chip"><b>{{ $area ? $fmtM($area) : '—' }}</b><span>m² Floor</span></div>
            <div class="chip"><b>{{ $seatTotal }}</b><span>Seats</span></div>
            <div class="chip"><b>{{ $pieces }}</b><span>Pieces</span></div>
        </div>
    </div>

    <div class="frame">
        <div class="corner" style="top:-1px;left:-1px;border-top-width:2px;border-left-width:2px;"></div>
        <div class="corner" style="top:-1px;right:-1px;border-top-width:2px;border-right-width:2px;"></div>
        <div class="corner" style="bottom:-1px;left:-1px;border-bottom-width:2px;border-left-width:2px;"></div>
        <div class="corner" style="bottom:-1px;right:-1px;border-bottom-width:2px;border-right-width:2px;"></div>

        <div class="cap">
            <div class="t">General Arrangement</div>
            <div class="r">
                @if ($ratio)SCALE <b>1 : {{ $ratio }}</b> · @endif
                {{ $scale ? 'DRAWN TO SCALE · METRES' : 'NOT TO SCALE — DIMENSIONS NOT SET' }}
            </div>
        </div>

        <div class="canvas">
            @if ($scale)
                {{-- the floor, with its metre grid --}}
                <div class="floor" style="left:{{ $vx }}px; top:{{ $vy }}px; width:{{ $vw }}px; height:{{ $vh }}px;
                     background-image: linear-gradient(#E8EEF6 1px, transparent 1px),
                                       linear-gradient(90deg, #E8EEF6 1px, transparent 1px);
                     background-size: 100% {{ round($gridPx * $k, 2) }}px, {{ round($gridPx * $k, 2) }}px 100%;"></div>

                {{-- width, above the floor --}}
                @php $dy = $vy - 17; @endphp
                <div class="dh" style="left:{{ $vx }}px; top:{{ $dy + 7 }}px; width:{{ $vw }}px;"></div>
                <div class="tk" style="left:{{ $vx }}px; top:{{ $dy + 3 }}px; width:1px; height:9px;"></div>
                <div class="tk" style="left:{{ $vx + $vw - 1 }}px; top:{{ $dy + 3 }}px; width:1px; height:9px;"></div>
                <div class="dcap" style="left:{{ $vx + $vw / 2 }}px; top:{{ $dy }}px; transform: translateX(-50%);">{{ $fmtM($roomW) }} m</div>

                {{-- length, to the left --}}
                @php $dx = $vx - 17; @endphp
                <div class="dv" style="left:{{ $dx + 7 }}px; top:{{ $vy }}px; height:{{ $vh }}px;"></div>
                <div class="tk" style="left:{{ $dx + 3 }}px; top:{{ $vy }}px; width:9px; height:1px;"></div>
                <div class="tk" style="left:{{ $dx + 3 }}px; top:{{ $vy + $vh - 1 }}px; width:9px; height:1px;"></div>
                <div class="dcap" style="left:{{ $dx + 7 }}px; top:{{ $vy + $vh / 2 }}px;
                     transform: translate(-50%, -50%) rotate(-90deg);">{{ $fmtM($roomL) }} m</div>

                <div class="note" style="left:{{ $vx }}px; top:{{ $vy + 6 }}px; width:{{ $vw }}px; text-align:center;">▲ &nbsp;Front of room&nbsp; ▲</div>
                <div class="note" style="right:8px; bottom:7px;">1 grid = {{ $gridM }} m</div>
            @else
                <div class="nodim">Room dimensions not set — the plan is shown unscaled.<br>
                    Add a width and a length in the builder to draw it to scale.</div>
            @endif

            {{-- ── the plan, in the builder's own coordinates ── --}}
            <div class="plan" style="left:{{ $planX }}px; top:{{ $planY }}px; transform: scale({{ $k }});">
                @foreach ($elements as $el)
                    <div class="piece" style="left:{{ $el['x'] ?? 480 }}px; top:{{ $el['y'] ?? 280 }}px;">
                        @if (($el['type'] ?? '') === 'seatblock')
                            @php $geo = \App\Models\EventRoom::seatChairs($el, $scale ?: 12); @endphp
                            <div style="position:relative; width:{{ $geo['w'] }}px; height:{{ $geo['h'] }}px;
                                        transform: rotate({{ $el['rot'] ?? 0 }}deg);">
                                @foreach ($geo['desks'] as [$dxx, $dyy, $dw, $dh])
                                    <span style="position:absolute; left:{{ $geo['w'] / 2 + $dxx - $dw / 2 }}px; top:{{ $geo['h'] / 2 + $dyy - $dh / 2 }}px;
                                                 width:{{ $dw }}px; height:{{ $dh }}px; border-radius:2px; background:#E8EDF4; border:0.6px solid #C6D1E0;"></span>
                                @endforeach
                                @foreach ($geo['tables'] as [$tx, $ty, $td])
                                    <span style="position:absolute; left:{{ $geo['w'] / 2 + $tx - $td / 2 }}px; top:{{ $geo['h'] / 2 + $ty - $td / 2 }}px;
                                                 width:{{ $td }}px; height:{{ $td }}px; border-radius:50%; background:#EEF3FA; border:1px solid #8DA0BC;"></span>
                                @endforeach
                                @foreach ($geo['rects'] ?? [] as [$rx, $ry, $rw, $rh])
                                    <span style="position:absolute; left:{{ $geo['w'] / 2 + $rx - $rw / 2 }}px; top:{{ $geo['h'] / 2 + $ry - $rh / 2 }}px;
                                                 width:{{ $rw }}px; height:{{ $rh }}px; border-radius:2px; background:#E8EDF4; border:1px solid #C6D1E0;"></span>
                                @endforeach
                                @foreach ($geo['chairs'] as [$cx, $cy])
                                    <span style="position:absolute; left:{{ $geo['w'] / 2 + $cx - $geo['chairPx'] / 2 }}px; top:{{ $geo['h'] / 2 + $cy - $geo['chairPx'] / 2 }}px;
                                                 width:{{ $geo['chairPx'] }}px; height:{{ $geo['chairPx'] }}px; border-radius:1px; background:{{ $navy }};"></span>
                                @endforeach
                                @foreach ($geo['labels'] ?? [] as [$lx, $ly, $lt])
                                    <span style="position:absolute; left:{{ $geo['w'] / 2 + $lx }}px; top:{{ $geo['h'] / 2 + $ly }}px;
                                                 transform: translate(-100%, -50%); padding-right:2px; font-size:8px; font-weight:800; color:#64748B;">{{ $lt }}</span>
                                @endforeach
                            </div>
                            @if (($el['seats'] ?? 0) > 0)
                                <span class="seatTag">{{ $el['seats'] }} seats</span>
                            @endif
                        @else
                            <div style="transform: rotate({{ $el['rot'] ?? 0 }}deg);">
                                <x-layout-element :type="$el['type']" :seats="$el['seats'] ?? 0"
                                                  :w="$el['w'] ?? 96" :h="$el['h'] ?? 96" :scale="$scale" />
                            </div>
                            @if (($el['seats'] ?? 0) > 0 && ($el['type'] ?? '') !== 'chair')
                                <span class="seatNum">{{ $el['seats'] }}</span>
                            @endif
                        @endif

                        @if (! empty($el['name']))
                            <span class="nameTag">{{ $el['name'] }}</span>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    {{-- ── title block ── --}}
    <div class="tblock">
        <div class="cell" style="flex:2.2;"><div class="k">Project</div><div class="v">{{ $event->name }}</div></div>
        <div class="cell" style="flex:1.9;"><div class="k">Drawing</div><div class="v">{{ $room->name }} — General Arrangement</div></div>
        <div class="cell" style="flex:1;"><div class="k">Capacity laid out</div><div class="v">{{ $seatTotal }}{{ $room->capacity ? ' / '.number_format($room->capacity) : '' }}</div></div>
        <div class="cell" style="flex:0.85;"><div class="k">Issued</div><div class="v">{{ now()->format('d M Y') }}</div></div>
        <div class="cell dark" style="flex:0.75;"><div class="k">Scale</div><div class="v">{{ $ratio ? '1 : '.$ratio : 'NTS' }}</div></div>
        <div class="cell dark" style="flex:0.6;"><div class="k">Sheet</div><div class="v">{{ $sheetRef }}</div></div>
    </div>
</div>

{{-- ═══════════════════ SHEET 2 · the schedule ═══════════════════ --}}
<div class="sheet">
    <div class="mh">
        <div style="min-width:0;">
            <div class="eb">Elite Business Hub · Equipment &amp; Requirements</div>
            <h1>{{ $room->name }}</h1>
            <div class="sub">{{ $event->name }} · schedule accompanying drawing {{ $sheetRef }}</div>
        </div>
        <div class="chips">
            <div class="chip"><b>{{ $days }}</b><span>Days held</span></div>
            <div class="chip"><b>{{ $reqs->count() }}</b><span>Line items</span></div>
            <div class="chip"><b>{{ $event->money($room->totalCents()) }}</b><span>Venue total</span></div>
        </div>
    </div>

    <div class="body2">
        {{-- ── the priced schedule ── --}}
        <div style="flex:1; min-width:0; display:flex; flex-direction:column;">
            <div class="sec">
                <h3>Equipment &amp; Requirements <em>rate is per unit, per day</em></h3>
                @if ($reqs->isEmpty())
                    <div class="empty">Nothing scheduled for this venue yet.</div>
                @else
                    <table class="sch">
                        <thead><tr>
                            <th class="idx">#</th><th>Item</th>
                            <th class="n">Rate</th><th class="n">Qty</th><th class="n">Days</th><th class="n">Total</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($reqs as $i => $r)
                                @php $q = max(1, (int) ($r['qty'] ?? 1)); $d = max(1, (int) ($r['days'] ?? 1)); @endphp
                                <tr>
                                    <td class="idx">{{ $i + 1 }}</td>
                                    <td class="item">{{ $r['name'] ?? '—' }}
                                        {{-- Said out loud, because a short run inside a long hire is the thing that gets missed. --}}
                                        @if ($d < $days)
                                            <span class="pill" style="background:#FEF3C7; color:#92400E;">{{ $d }} of {{ $days }} days</span>
                                        @endif
                                    </td>
                                    <td class="n">{{ $event->money($r['cost_cents'] ?? 0) }}</td>
                                    <td class="n">{{ $q }}</td>
                                    <td class="n">{{ $d }}</td>
                                    <td class="n tot">{{ $event->money(\App\Models\EventRoom::requirementCents($r)) }}</td>
                                </tr>
                            @endforeach
                            <tr class="sum">
                                <td colspan="5">Equipment &amp; requirements</td>
                                <td class="n">{{ $event->money($reqTotal) }}</td>
                            </tr>
                        </tbody>
                    </table>
                @endif
            </div>

            @if ($equip->isNotEmpty())
                <div class="sec">
                    <h3>Room Kit <em>{{ $equip->sum('qty') }} units · {{ $room->equipmentReadiness() }}% confirmed</em></h3>
                    <table class="sch">
                        <thead><tr><th>Item</th><th class="n">Units</th><th>Status</th><th>Notes</th></tr></thead>
                        <tbody>
                            @foreach ($equip as $name => $line)
                                @php
                                    [$bg, $fg] = match ($line['status']) {
                                        'onsite' => ['#DCFCE7', '#166534'],
                                        'confirmed' => ['#DBEAFE', '#1E40AF'],
                                        default => ['#FEF3C7', '#92400E'],
                                    };
                                @endphp
                                <tr>
                                    <td class="item">{{ Str::title(str_replace('_', ' ', $name)) }}</td>
                                    <td class="n">{{ $line['qty'] }}</td>
                                    <td><span class="pill" style="background:{{ $bg }}; color:{{ $fg }};">{{ $line['status'] }}</span></td>
                                    <td style="color:#64748B;">{{ $line['notes'] ?: '—' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            {{-- A plan comes back signed, or it comes back as an argument. --}}
            <div class="sec" style="margin-top:auto;">
                <h3>Confirmed by the venue</h3>
                <div style="display:flex;">
                    @foreach (['Venue representative', 'Name in print', 'Date'] as $field)
                        <div style="flex:1; padding:11px 12px 9px; {{ $loop->last ? '' : 'border-right:1px solid #EEF2F8;' }}">
                            <div style="height:26px; border-bottom:1px solid #C8D2E0;"></div>
                            <div style="margin-top:5px; font-size:6.5px; font-weight:800; letter-spacing:1.4px;
                                        text-transform:uppercase; color:#94A3B8;">{{ $field }}</div>
                        </div>
                    @endforeach
                </div>
                <div style="padding:0 12px 9px; font-size:8.5px; color:#94A3B8;">
                    Signing confirms the room can be set as drawn, and that the equipment above is available for the days listed.
                </div>
            </div>
        </div>

        {{-- ── the rail ── --}}
        <div class="rail">
            <div class="sec">
                <h3>Cost <em>{{ $event->currency }}</em></h3>
                <div class="kv"><span class="a">Hire · {{ $event->money($room->cost_cents ?? 0) }} per day</span><span class="b">× {{ $days }}</span></div>
                <div class="kv"><span class="a">Hire total</span><span class="b">{{ $event->money($room->hireCents()) }}</span></div>
                <div class="kv"><span class="a">Equipment &amp; requirements</span><span class="b">{{ $event->money($reqTotal) }}</span></div>
                <div class="kv big"><span class="a">Venue total</span><span class="b">{{ $event->money($room->totalCents()) }}</span></div>
            </div>

            <div class="sec">
                <h3>How the days were counted</h3>
                <div class="prose">
                    @php
                        $counted = $room->daysOnTheAgenda();
                        $sentence = $room->daysAreCounted()
                            ? 'Counted from the programme — this venue holds sessions on <b>'.$counted.'</b> '.str('day')->plural($counted).'.'
                            : 'Set to <b>'.$room->days.'</b> '.str('day')->plural((int) $room->days).' by hand'
                                .($counted !== (int) $room->days ? ', against '.$counted.' on the programme' : '').'.';
                        if ($room->setup_days) {
                            $sentence .= ' Plus <b>'.$room->setup_days.'</b> '
                                .str('day')->plural($room->setup_days).' for setup and teardown.';
                        }
                    @endphp
                    {!! $sentence !!}
                </div>
            </div>

            @if (! empty($breakdown))
                @php $maxSeats = max(1, collect($breakdown)->max('seats') ?: 1); @endphp
                <div class="sec">
                    <h3>Layout Breakdown <em>{{ $seatTotal }} seats</em></h3>
                    <div style="padding:4px 12px 9px;">
                        @foreach ($breakdown as $lbl => $b)
                            <div style="padding:5px 0;">
                                <div style="display:flex; font-size:9.5px;">
                                    <span style="font-weight:700; color:#334155;">{{ $b['count'] }}× {{ $lbl }}</span>
                                    <span style="margin-left:auto; font-weight:800; color:{{ $navy }};">{{ $b['seats'] > 0 ? $b['seats'].' seats' : '—' }}</span>
                                </div>
                                @if ($b['seats'] > 0)
                                    <div class="bar"><i style="width:{{ max(4, (int) round($b['seats'] / $maxSeats * 100)) }}%"></i></div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="sec">
                <h3>Legend <em>drawn to scale</em></h3>
                <div class="lgd">
                    <div><s style="width:11px;height:11px;background:{{ $navy }};border-radius:1px;"></s>Chair — 0.6 × 0.6 m</div>
                    <div><s style="width:18px;height:9px;background:#EEF3FA;border:1px solid #8DA0BC;border-radius:2px;"></s>Table</div>
                    <div><s style="width:13px;height:13px;background:#EEF3FA;border:1px solid #8DA0BC;border-radius:50%;"></s>Round table</div>
                    <div><s style="width:18px;height:8px;background:{{ $navy }};border-radius:2px;"></s>Stage / podium</div>
                    <div><s style="width:13px;height:13px;background:#334155;border-radius:2px;"></s>Translation booth</div>
                </div>
            </div>
        </div>
    </div>

    <div class="foot2">
        <span>{{ $event->name }} · {{ $room->name }} · schedule to drawing {{ $sheetRef }}</span>
        <span style="margin-left:auto;">Issued {{ now()->format('d M Y') }} · indicative layout, not for construction</span>
    </div>
</div>

</body>
</html>
