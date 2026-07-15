<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; }
        .accent { height: 5px; background: {{ $theme['accent'] }}; }
        .head { background: {{ $theme['primary'] }}; color: #fff; padding: 14px 24px; }
        .brand { font-size: 9px; letter-spacing: 3px; color: {{ $theme['accent'] }}; font-weight: bold; }
        .head h1 { font-size: 18px; margin-top: 3px; }
        .head .meta { font-size: 10px; color: rgba(255,255,255,0.8); margin-top: 2px; }
        .canvas { position: relative; width: 780px; height: 455px; margin: 18px auto 0; border: 1px solid #E2E8F0; border-radius: 8px; background: #FBFCFE; }
        .stage { position: absolute; top: 0; left: 0; right: 0; height: 18px; background: {{ $theme['primary'] }}; color: {{ $theme['accent'] }}; font-size: 8px; font-weight: bold; letter-spacing: 3px; text-align: center; line-height: 18px; text-transform: uppercase; border-radius: 8px 8px 0 0; }
        .foot { text-align: center; font-size: 9px; color: #94A3B8; margin-top: 12px; }
        .equip { width: 780px; margin: 14px auto 0; }
        .equip h2 { font-size: 11px; color: {{ $theme['primary'] }}; letter-spacing: 1px; text-transform: uppercase; border-bottom: 1.5px solid {{ $theme['accent'] }}; padding-bottom: 4px; margin-bottom: 8px; }
        .equip table { width: 100%; border-collapse: collapse; }
        .equip td { font-size: 10px; padding: 3px 8px; border-bottom: 1px solid #EEF2F8; }
        .equip .cat { font-weight: bold; color: #64748B; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; padding-top: 8px; }
        .equip .qty { text-align: right; font-weight: bold; color: {{ $theme['primary'] }}; width: 40px; }
    </style>
</head>
<body>
    <div class="accent"></div>
    <div class="head">
        <div class="brand">ELITE BUSINESS HUB · FLOOR PLAN</div>
        <h1>{{ $room->name }} — {{ str($room->type)->replace('_', ' ')->title() }}</h1>
        <div class="meta">{{ $event->name }}
            @if ($room->width_m && $room->length_m) · {{ rtrim(rtrim(number_format($room->width_m, 1), '0'), '.') }}m × {{ rtrim(rtrim(number_format($room->length_m, 1), '0'), '.') }}m @endif
            @if ($room->capacity) · room capacity {{ number_format($room->capacity) }} @endif
            · {{ $room->seatCount() }} seats laid out</div>
    </div>

    <div class="canvas">
        <div class="stage">Stage / Front</div>
        @php
            $sx = 780 / 960; $sy = 455 / 560;
            $pdfScale = ($room->width_m && $room->length_m) ? min(960 / $room->width_m, 560 / $room->length_m) * $sx : 12 * $sx;
        @endphp
        @foreach ($elements as $el)
            @php
                $left = ($el['x'] ?? 480) * $sx;
                $top = ($el['y'] ?? 280) * $sy;
                $rot = $el['rot'] ?? 0;
            @endphp
            @if (($el['type'] ?? '') === 'seatblock')
                @php $geo = \App\Models\EventRoom::seatChairs($el, $pdfScale); @endphp
                <div style="position:absolute; left:{{ round($left) }}px; top:{{ round($top) }}px; transform: translate(-50%,-50%) rotate({{ $rot }}deg); width:{{ $geo['w'] }}px; height:{{ $geo['h'] }}px;">
                    @foreach ($geo['desks'] as [$dx, $dy, $dw, $dh])
                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $dx - $dw / 2 }}px; top:{{ $geo['h'] / 2 + $dy - $dh / 2 }}px; width:{{ $dw }}px; height:{{ $dh }}px; background:#E2E8F0;"></div>
                    @endforeach
                    @foreach ($geo['tables'] as [$tx, $ty, $td])
                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $tx - $td / 2 }}px; top:{{ $geo['h'] / 2 + $ty - $td / 2 }}px; width:{{ $td }}px; height:{{ $td }}px; border:1px solid #94A3B8; border-radius:50%; background:#F1F5F9;"></div>
                    @endforeach
                    @foreach ($geo['rects'] ?? [] as [$rx, $ry, $rw, $rh])
                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $rx - $rw / 2 }}px; top:{{ $geo['h'] / 2 + $ry - $rh / 2 }}px; width:{{ $rw }}px; height:{{ $rh }}px; background:#E2E8F0; border:1px solid #CBD5E1;"></div>
                    @endforeach
                    @php $cf = max(2.4, round($geo['chairPx'] * 0.42, 1)); @endphp
                    @foreach ($geo['chairs'] as [$cx, $cy, $cn])
                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $cx - $geo['chairPx'] / 2 }}px; top:{{ $geo['h'] / 2 + $cy - $geo['chairPx'] / 2 }}px; width:{{ $geo['chairPx'] }}px; height:{{ $geo['chairPx'] }}px; background:#334155; color:#fff; font-size:{{ $cf }}px; line-height:{{ $geo['chairPx'] }}px; text-align:center; overflow:hidden;">{{ $cn }}</div>
                    @endforeach
                    @foreach ($geo['labels'] ?? [] as [$lx, $ly, $lt])
                        <div style="position:absolute; left:{{ $geo['w'] / 2 + $lx - 10 }}px; top:{{ $geo['h'] / 2 + $ly - 4 }}px; width:9px; text-align:right; font-size:5px; font-weight:bold; color:#64748B;">{{ $lt }}</div>
                    @endforeach
                </div>
            @else
                <div style="position:absolute; left:{{ round($left) }}px; top:{{ round($top) }}px; transform: translate(-50%,-50%) rotate({{ $rot }}deg);">
                    <x-layout-element :type="$el['type']" :seats="$el['seats'] ?? 0" :w="$el['w'] ?? 96" :h="$el['h'] ?? 96" />
                </div>
            @endif
        @endforeach
    </div>

    @php $equip = $room->equipmentLines(); @endphp
    @if ($equip->isNotEmpty())
        <div class="equip">
            <h2>Equipment & AV — {{ $equip->sum('qty') }} units · see prep sheet for status</h2>
            <table>
                @foreach (\App\Models\EventRoom::EQUIPMENT as $category => $items)
                    @php $rows = collect($items)->filter(fn ($i) => isset($equip[$i])); @endphp
                    @if ($rows->isNotEmpty())
                        <tr><td class="cat" colspan="2">{{ $category }}</td></tr>
                        @foreach ($rows as $item)
                            <tr><td>{{ $item }}</td><td class="qty">{{ $equip[$item]['qty'] }}</td></tr>
                        @endforeach
                    @endif
                @endforeach
            </table>
        </div>
    @endif

    <p class="foot">Generated {{ now()->format('M j, Y · H:i') }} · Elite Business Hub — Operations Command Center</p>
</body>
</html>
