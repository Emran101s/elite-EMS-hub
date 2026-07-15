<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $statusMeta = [
            'needed' => ['Needed', '#64748B'],
            'requested' => ['Requested', '#B45309'],
            'confirmed' => ['Confirmed', '#047857'],
            'onsite' => ['On-site', '#0369A1'],
        ];
        $units = $lines->sum('qty');
        $ready = $lines->whereIn('status', ['confirmed', 'onsite'])->sum('qty');
        $readiness = $units > 0 ? (int) round($ready / $units * 100) : 0;
        $custom = $lines->keys()->diff(collect(\App\Models\EventRoom::EQUIPMENT)->flatten());
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; }
        .accent { height: 5px; background: {{ $theme['accent'] }}; }
        .head { background: {{ $theme['primary'] }}; color: #fff; padding: 16px 28px; }
        .brand { font-size: 9px; letter-spacing: 3px; color: {{ $theme['accent'] }}; font-weight: bold; }
        .head h1 { font-size: 19px; margin-top: 3px; }
        .head .meta { font-size: 10px; color: rgba(255,255,255,0.8); margin-top: 2px; }
        .summary { display: table; width: 100%; padding: 14px 28px; border-bottom: 1.5px solid #EEF2F8; }
        .summary .cell { display: table-cell; width: 33%; }
        .summary .n { font-size: 22px; font-weight: bold; color: {{ $theme['primary'] }}; }
        .summary .l { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #94A3B8; }
        .bar { height: 7px; border-radius: 4px; background: #E2E8F0; margin-top: 6px; overflow: hidden; }
        .bar > div { height: 7px; background: {{ $theme['accent'] }}; }
        .wrap { padding: 8px 28px 0; }
        h2.cat { font-size: 10px; color: {{ $theme['primary'] }}; letter-spacing: 1.5px; text-transform: uppercase; margin: 14px 0 5px; border-bottom: 1px solid {{ $theme['accent'] }}; padding-bottom: 3px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { font-size: 10px; padding: 5px 8px; text-align: left; }
        th { font-size: 7.5px; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8; border-bottom: 1px solid #E2E8F0; }
        tbody td { border-bottom: 1px solid #F1F5F9; }
        .qty { text-align: center; font-weight: bold; width: 40px; color: {{ $theme['primary'] }}; }
        .st { width: 78px; font-weight: bold; font-size: 8.5px; }
        .dot { display: inline-block; width: 6px; height: 6px; border-radius: 50%; margin-right: 4px; }
        .notes { color: #64748B; font-style: italic; }
        .chk { width: 16px; }
        .box { display: inline-block; width: 9px; height: 9px; border: 1.2px solid #94A3B8; border-radius: 2px; }
        .foot { text-align: center; font-size: 9px; color: #94A3B8; margin-top: 18px; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="accent"></div>
    <div class="head">
        <div class="brand">ELITE BUSINESS HUB · AV &amp; EQUIPMENT PREP SHEET</div>
        <h1>{{ $room->name }} — {{ str($room->type)->replace('_', ' ')->title() }}</h1>
        <div class="meta">{{ $event->name }}
            @if ($room->width_m && $room->length_m) · {{ rtrim(rtrim(number_format($room->width_m, 1), '0'), '.') }}m × {{ rtrim(rtrim(number_format($room->length_m, 1), '0'), '.') }}m @endif
        </div>
    </div>

    <div class="summary">
        <div class="cell"><div class="n">{{ $units }}</div><div class="l">Total units · {{ $lines->count() }} lines</div></div>
        <div class="cell"><div class="n">{{ $readiness }}%</div><div class="l">Confirmed / on-site</div><div class="bar"><div style="width: {{ $readiness }}%"></div></div></div>
        <div class="cell">
            @foreach ($statusMeta as $s => [$lbl, $color])
                <div style="font-size:8.5px; color:#475569;"><span class="dot" style="background: {{ $color }}"></span>{{ $lbl }}: <b>{{ $lines->where('status', $s)->sum('qty') }}</b></div>
            @endforeach
        </div>
    </div>

    <div class="wrap">
        @foreach (\App\Models\EventRoom::EQUIPMENT as $category => $items)
            @php $rows = collect($items)->filter(fn ($i) => isset($lines[$i])); @endphp
            @if ($rows->isNotEmpty())
                <h2 class="cat">{{ $category }}</h2>
                <table>
                    <thead><tr><th class="chk"></th><th>Item</th><th class="qty">Qty</th><th class="st">Status</th><th>Notes</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $item)
                            @php $line = $lines[$item]; [$lbl, $color] = $statusMeta[$line['status']] ?? $statusMeta['needed']; @endphp
                            <tr>
                                <td class="chk"><span class="box"></span></td>
                                <td>{{ $item }}</td>
                                <td class="qty">{{ $line['qty'] }}</td>
                                <td class="st" style="color: {{ $color }}"><span class="dot" style="background: {{ $color }}"></span>{{ $lbl }}</td>
                                <td class="notes">{{ $line['notes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        @endforeach

        @if ($custom->isNotEmpty())
            <h2 class="cat">Custom</h2>
            <table>
                <thead><tr><th class="chk"></th><th>Item</th><th class="qty">Qty</th><th class="st">Status</th><th>Notes</th></tr></thead>
                <tbody>
                    @foreach ($custom as $item)
                        @php $line = $lines[$item]; [$lbl, $color] = $statusMeta[$line['status']] ?? $statusMeta['needed']; @endphp
                        <tr>
                            <td class="chk"><span class="box"></span></td>
                            <td>{{ $item }}</td>
                            <td class="qty">{{ $line['qty'] }}</td>
                            <td class="st" style="color: {{ $color }}"><span class="dot" style="background: {{ $color }}"></span>{{ $lbl }}</td>
                            <td class="notes">{{ $line['notes'] }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    <p class="foot">Generated {{ now()->format('M j, Y · H:i') }} · Elite Business Hub — Operations Command Center</p>
</body>
</html>
