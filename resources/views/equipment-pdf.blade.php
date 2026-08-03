<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1E3352; font-size: 11px; }
        .head { background: #0B1F3A; color: #fff; padding: 20px 26px; }
        .eyebrow { color: #D4AF37; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; }
        .title { font-size: 20px; font-weight: bold; margin-top: 6px; }
        .meta { color: rgba(255,255,255,0.72); font-size: 10px; margin-top: 3px; }
        .wrap { padding: 18px 26px; }
        table { width: 100%; border-collapse: collapse; }
        thead th { text-align: left; font-size: 8px; text-transform: uppercase; letter-spacing: 1px; color: #64748B; border-bottom: 2px solid #D4AF37; padding: 6px 8px; }
        thead th.r, td.r { text-align: right; }
        tbody td { padding: 7px 8px; border-bottom: 1px solid #EEF2F7; font-size: 11px; }
        tbody td.name { font-weight: bold; }
        tbody td.notes { color: #64748B; font-size: 10px; }
        tfoot td { padding: 9px 8px; font-weight: bold; border-top: 2px solid #0B1F3A; font-size: 12px; }
        .foot { text-align: center; font-size: 8px; color: #94A3B8; padding: 12px; }
        .empty { text-align: center; color: #94A3B8; padding: 40px; }
    </style>
</head>
<body>
    <div class="head">
        <div class="eyebrow">&#9670; Elite Business Hub · Equipment Catalog</div>
        <div class="title">Equipment Catalog</div>
        <div class="meta">{{ $items->count() }} {{ \Illuminate\Support\Str::plural('item', $items->count()) }} · Generated {{ now()->format('j M Y · H:i') }}</div>
    </div>

    <div class="wrap">
        @if ($items->isEmpty())
            <div class="empty">No equipment in the catalog yet.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:34%">Equipment</th>
                        <th class="r" style="width:16%">Unit price</th>
                        <th style="width:50%">Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $r)
                        <tr>
                            <td class="name">{{ $r->name }}</td>
                            <td class="r">{{ $r->unit_price_cents ? number_format($r->unit_price_cents / 100, 2) : '—' }}</td>
                            <td class="notes">{{ $r->notes }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr>
                        <td>Total unit value</td>
                        <td class="r">{{ number_format($total / 100, 2) }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        @endif
    </div>

    <div class="foot">Elite Business Hub · Equipment Catalog</div>
</body>
</html>
