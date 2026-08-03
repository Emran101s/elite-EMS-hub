<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $fmt = fn ($cents) => $event->money($cents);
        $event->ensureBudgetCategories();
        $cats = $event->budgetCategories()->get();
        $known = $cats->pluck('name');
        $sections = $cats->map(fn ($c) => ['label' => $c->name, 'items' => $items->where('category', $c->name)->values()])
            ->merge(
                $items->reject(fn ($i) => $known->contains($i->category))->groupBy('category')
                    ->map(fn ($grp, $name) => ['label' => $name ?: 'Uncategorised', 'items' => $grp->values()])->values()
            )
            ->filter(fn ($s) => $s['items']->isNotEmpty())->values();
        $est = $items->sum('estimated_cents');
        $act = $items->sum(fn ($i) => (int) $i->actual_cents);
        $paid = $items->sum('paid_cents');
        $forecast = $items->sum(fn ($i) => $i->costCents());
        $pct = (float) ($event->management_fee_pct ?? 15);

        // Charged line by line, not as a flat percentage of the subtotal — a
        // hand-typed price, a per-line markup and a non-billable line all have
        // to survive into the document the client is shown. See
        // EventBudgetItem::sellCentsOn().
        $costed = $items->filter->hasActual();
        $grandEst = (int) $items->sum(fn ($i) => $i->sellCentsOn((int) $i->estimated_cents, $pct));
        $grandAct = (int) $costed->sum(fn ($i) => $i->sellCentsOn((int) $i->actual_cents, $pct));
        $grandForecast = (int) $items->sum(fn ($i) => $i->sellCents($pct));
        $feeEst = $grandEst - $est;
        $feeAct = $grandAct - $act;
        $cap = $event->budget_cents ?? 0;
        $pctLabel = rtrim(rtrim(number_format($pct, 2), '0'), '.');
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; }
        .accent { height: 5px; background: {{ $theme['accent'] }}; }
        .head { background: {{ $theme['primary'] }}; color: #fff; padding: 16px 28px; }
        .brand { font-size: 9px; letter-spacing: 3px; color: {{ $theme['accent'] }}; font-weight: bold; }
        .head h1 { font-size: 19px; margin-top: 3px; }
        .head .meta { font-size: 10px; color: rgba(255,255,255,0.8); margin-top: 2px; }
        .summary { display: table; width: 100%; padding: 12px 28px; border-bottom: 1.5px solid #EEF2F8; }
        .summary .cell { display: table-cell; width: 20%; }
        .summary .n { font-size: 15px; font-weight: bold; color: {{ $theme['primary'] }}; }
        .summary .over { color: #B91C1C; }
        .summary .l { font-size: 7.5px; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8; }
        .wrap { padding: 6px 28px 0; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 7.5px; letter-spacing: 0.5px; text-transform: uppercase; color: #94A3B8; text-align: right; padding: 6px 6px 3px; border-bottom: 1px solid #E2E8F0; }
        th.l { text-align: left; }
        td { font-size: 9.5px; padding: 4px 6px; border-bottom: 1px solid #F1F5F9; text-align: right; }
        td.l { text-align: left; }
        .cat { background: #F8FAFC; }
        .cat td { font-weight: bold; font-size: 9px; color: {{ $theme['primary'] }}; border-bottom: 1px solid #E2E8F0; }
        .grp { font-size: 7.5px; letter-spacing: 1.5px; text-transform: uppercase; color: #94A3B8; font-weight: bold; padding: 10px 6px 2px; }
        .sub { color: #64748B; font-size: 8px; }
        .total td { font-weight: bold; font-size: 11px; color: {{ $theme['primary'] }}; border-top: 2px solid #CBD5E1; border-bottom: none; padding-top: 7px; }
        .paid { color: #047857; }
        .foot { text-align: center; font-size: 9px; color: #94A3B8; margin-top: 16px; padding-bottom: 10px; }
    </style>
</head>
<body>
    <div class="accent"></div>
    <div class="head">
        <div class="brand">ELITE BUSINESS HUB · EVENT BUDGET</div>
        <h1>{{ $event->name }}</h1>
        <div class="meta">
            @if ($event->starts_at){{ $event->starts_at->format('j M') }}@if ($event->ends_at) – {{ $event->ends_at->format('j M Y') }}@endif @endif
            · {{ $items->count() }} lines
        </div>
    </div>

    <div class="summary">
        <div class="cell"><div class="n">{{ $fmt($cap) }}</div><div class="l">Total budget</div></div>
        <div class="cell"><div class="n">{{ $fmt($grandEst) }}</div><div class="l">Grand budget (incl. {{ $pctLabel }}% fee)</div></div>
        <div class="cell"><div class="n {{ $grandAct > $grandEst && $grandEst > 0 ? 'over' : '' }}">{{ $fmt($grandAct) }}</div><div class="l">Actual</div></div>
        <div class="cell"><div class="n">{{ $fmt($paid) }}</div><div class="l">Paid</div></div>
        <div class="cell"><div class="n {{ ($cap - $grandForecast) < 0 ? 'over' : '' }}">{{ $fmt($cap - $grandForecast) }}</div><div class="l">Remaining</div></div>
    </div>

    <div class="wrap">
        <table>
            <thead>
                <tr><th class="l">Line item</th><th>Budgeted</th><th>Actual</th><th>Paid</th><th>Status</th></tr>
            </thead>
            <tbody>
                @foreach ($sections as $sec)
                    @php $catItems = $sec['items']; @endphp
                    <tr class="cat">
                        <td class="l">{{ $sec['label'] }}</td>
                        <td>{{ $fmt($catItems->sum('estimated_cents')) }}</td>
                        <td>{{ $catItems->sum('actual_cents') ? $fmt($catItems->sum('actual_cents')) : '—' }}</td>
                        <td class="paid">{{ $catItems->sum('paid_cents') ? $fmt($catItems->sum('paid_cents')) : '—' }}</td>
                        <td></td>
                    </tr>
                    @foreach ($catItems as $item)
                        <tr>
                            <td class="l">{{ $item->description ?? $item->categoryLabel() }}<span class="sub">@if ($item->quantity > 1 || $item->unit_cents)  ({{ $item->quantity }} × {{ $fmt($item->unit_cents) }})@endif @if ($item->vendor) · {{ $item->vendor }}@endif</span></td>
                            <td>{{ $item->estimated_cents ? $fmt($item->estimated_cents) : '—' }}</td>
                            <td>{{ $item->actual_cents ? $fmt($item->actual_cents) : '—' }}</td>
                            <td class="paid">{{ $item->paid_cents ? $fmt($item->paid_cents) : '—' }}</td>
                            <td>{{ ucfirst($item->payment_status) }}</td>
                        </tr>
                    @endforeach
                @endforeach
                <tr class="cat">
                    <td class="l">Subtotal</td>
                    <td>{{ $fmt($est) }}</td>
                    <td>{{ $act ? $fmt($act) : '—' }}</td>
                    <td class="paid">{{ $paid ? $fmt($paid) : '—' }}</td>
                    <td></td>
                </tr>
                <tr class="cat">
                    <td class="l">Events management fee ({{ $pctLabel }}%)</td>
                    <td>{{ $fmt($feeEst) }}</td>
                    <td>{{ $feeAct ? $fmt($feeAct) : '—' }}</td>
                    <td class="paid">—</td>
                    <td></td>
                </tr>
                <tr class="total">
                    <td class="l">Grand total</td>
                    <td>{{ $fmt($grandEst) }}</td>
                    <td>{{ $grandAct ? $fmt($grandAct) : '—' }}</td>
                    <td class="paid">{{ $paid ? $fmt($paid) : '—' }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>
    </div>

    <p class="foot">Generated {{ now()->format('j M Y · H:i') }} · Elite Business Hub — Operations Command Center</p>
</body>
</html>
