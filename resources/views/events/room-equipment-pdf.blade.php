<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $navy = $theme['primary'] ?? '#0B1F3A';
        $gold = $theme['accent'] ?? '#D4AF37';
        $statusMeta = [
            'needed' => ['Needed', '#64748B', '#F1F5F9'],
            'requested' => ['Requested', '#B45309', '#FEF3E2'],
            'confirmed' => ['Confirmed', '#047857', '#E7F6EF'],
            'onsite' => ['On-site', '#0369A1', '#E6F2FA'],
        ];
        $units = $lines->sum('qty');
        $ready = $lines->whereIn('status', ['confirmed', 'onsite'])->sum('qty');
        $readiness = $units > 0 ? (int) round($ready / $units * 100) : 0;
        $custom = $lines->keys()->diff(collect(\App\Models\EventRoom::EQUIPMENT)->flatten());
        $fmtM = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
    @endphp
    <style>
        @page { size: A4 portrait; margin: 0; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; }
        .page { padding: 112px 26px 44px; }

        /* readiness band */
        .rband { border: 1px solid #E4E9F1; border-radius: 7px; padding: 12px 14px; margin-bottom: 14px; }
        .rband .top { display: table; width: 100%; }
        .rband .rl, .rband .rr { display: table-cell; vertical-align: middle; }
        .rband .rr { text-align: right; }
        .rband .lbl { font-size: 8px; letter-spacing: 1.5px; text-transform: uppercase; color: #94A3B8; }
        .rband .pct { font-size: 20px; font-weight: bold; color: {{ $navy }}; }
        .rband .pct small { font-size: 9px; font-weight: normal; color: #94A3B8; letter-spacing: 0; }
        .bar { height: 9px; border-radius: 5px; background: #EDF1F7; margin-top: 9px; overflow: hidden; }
        .bar > div { height: 9px; background: {{ $gold }}; }
        .pills { margin-top: 10px; }
        .pills .p { display: inline-block; font-size: 8px; font-weight: bold; padding: 3px 9px; border-radius: 20px; margin-right: 5px; }

        /* category cards */
        .cat { border: 1px solid #E4E9F1; border-radius: 7px; margin-bottom: 11px; overflow: hidden; }
        .cat .ch { display: table; width: 100%; background: {{ $navy }}; }
        .cat .ch .a, .cat .ch .b { display: table-cell; vertical-align: middle; padding: 6px 12px; }
        .cat .ch .a { font-size: 9.5px; font-weight: bold; letter-spacing: 2px; text-transform: uppercase; color: {{ $gold }}; }
        .cat .ch .b { text-align: right; font-size: 8px; color: rgba(255,255,255,0.7); }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 7px; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8; text-align: left; padding: 6px 12px; border-bottom: 1px solid #EDF1F7; }
        td { font-size: 10px; padding: 6px 12px; border-bottom: 1px solid #F4F7FB; vertical-align: middle; }
        tr:last-child td { border-bottom: none; }
        .chk { width: 20px; }
        .box { display: inline-block; width: 10px; height: 10px; border: 1.3px solid #94A3B8; border-radius: 2px; }
        .item { font-weight: bold; color: #1E293B; }
        .qty { text-align: center; font-weight: bold; width: 42px; color: {{ $navy }}; }
        .st { width: 92px; }
        .pill { display: inline-block; font-size: 8px; font-weight: bold; padding: 2px 8px; border-radius: 20px; }
        .notes { color: #64748B; font-style: italic; font-size: 9px; }
        .empty { border: 1px dashed #CBD5E1; border-radius: 7px; padding: 30px; text-align: center; color: #94A3B8; font-size: 11px; }
    </style>
</head>
<body>
    {{-- ═══ masthead ═══ --}}
    <x-pdf-header fixed :navy="$navy" :gold="$gold"
        eyebrow="Elite Business Hub · AV & Equipment Prep Sheet"
        :title="$room->name"
        :subtitle="$event->name.' · '.str($room->type)->replace('_', ' ')->title().($room->width_m && $room->length_m ? ' · '.$fmtM($room->width_m).'×'.$fmtM($room->length_m).' m' : '')"
        :chips="[
            ['n' => (string) $units, 'l' => 'Units'],
            ['n' => (string) $lines->count(), 'l' => 'Lines'],
            ['n' => $readiness.'%', 'l' => 'Ready'],
        ]" />

    {{-- ═══ footer ═══ --}}
    <x-pdf-footer fixed :navy="$navy" :gold="$gold" :sheet="'AV-'.$room->id" />

    {{-- ═══ body ═══ --}}
    <div class="page">
    @if ($lines->isEmpty())
        <div class="empty">No equipment requested for this room yet.<br>Add items from the Equipment workspace to build the prep sheet.</div>
    @else
        <div class="rband">
            <div class="top">
                <div class="rl"><div class="lbl">Preparation readiness</div><div class="pct">{{ $readiness }}% <small>confirmed / on-site</small></div></div>
                <div class="rr"><div class="lbl">Total</div><div class="pct">{{ $units }} <small>units · {{ $lines->count() }} lines</small></div></div>
            </div>
            <div class="bar"><div style="width: {{ $readiness }}%"></div></div>
            <div class="pills">
                @foreach ($statusMeta as $s => [$lbl, $color, $soft])
                    <span class="p" style="background: {{ $soft }}; color: {{ $color }};">{{ $lbl }} · {{ $lines->where('status', $s)->sum('qty') }}</span>
                @endforeach
            </div>
        </div>

        @php
            $groups = collect(\App\Models\EventRoom::EQUIPMENT)
                ->map(fn ($items) => collect($items)->filter(fn ($i) => isset($lines[$i]))->values())
                ->filter(fn ($rows) => $rows->isNotEmpty());
            if ($custom->isNotEmpty()) {
                $groups = $groups->put('Custom', $custom->values());
            }
        @endphp

        @foreach ($groups as $category => $rows)
            @php $catUnits = collect($rows)->sum(fn ($i) => $lines[$i]['qty'] ?? 0); @endphp
            <div class="cat">
                <div class="ch">
                    <div class="a">{{ $category }}</div>
                    <div class="b">{{ count($rows) }} {{ \Illuminate\Support\Str::plural('line', count($rows)) }} · {{ $catUnits }} units</div>
                </div>
                <table>
                    <thead><tr><th class="chk"></th><th>Item</th><th class="qty">Qty</th><th class="st">Status</th><th>Notes</th></tr></thead>
                    <tbody>
                        @foreach ($rows as $item)
                            @php $line = $lines[$item]; [$lbl, $color, $soft] = $statusMeta[$line['status']] ?? $statusMeta['needed']; @endphp
                            <tr>
                                <td class="chk"><span class="box"></span></td>
                                <td class="item">{{ $item }}</td>
                                <td class="qty">{{ $line['qty'] }}</td>
                                <td class="st"><span class="pill" style="background: {{ $soft }}; color: {{ $color }};">{{ $lbl }}</span></td>
                                <td class="notes">{{ $line['notes'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endforeach
    @endif
    </div>
</body>
</html>
