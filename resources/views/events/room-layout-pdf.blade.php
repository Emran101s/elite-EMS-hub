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
    </style>
</head>
<body>
    <div class="accent"></div>
    <div class="head">
        <div class="brand">ELITE BUSINESS HUB · FLOOR PLAN</div>
        <h1>{{ $room->name }} — {{ str($room->type)->replace('_', ' ')->title() }}</h1>
        <div class="meta">{{ $event->name }}
            @if ($room->capacity) · room capacity {{ number_format($room->capacity) }} @endif
            · {{ $room->seatCount() }} seats laid out</div>
    </div>

    <div class="canvas">
        <div class="stage">Stage / Front</div>
        @php $sx = 780 / 960; $sy = 455 / 560; @endphp
        @foreach ($elements as $el)
            @php
                $left = ($el['x'] ?? 480) * $sx;
                $top = ($el['y'] ?? 280) * $sy;
                $rot = $el['rot'] ?? 0;
            @endphp
            <div style="position:absolute; left:{{ round($left) }}px; top:{{ round($top) }}px; transform: translate(-50%,-50%) rotate({{ $rot }}deg);">
                <x-layout-element :type="$el['type']" :seats="$el['seats'] ?? 0" :w="$el['w'] ?? 96" :h="$el['h'] ?? 96" />
            </div>
        @endforeach
    </div>

    <p class="foot">Generated {{ now()->format('M j, Y · H:i') }} · Elite Business Hub — Operations Command Center</p>
</body>
</html>
