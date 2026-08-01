<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        // Everything is read once here so the layout below is only layout.
        $fmt = fn ($cents) => number_format($cents / 100, 2);
        $sub = $invoice->subtotalCents();
        $tax = $invoice->taxCents();
        $total = $invoice->totalCents();
        $out = $invoice->outstandingCents();
        $cur = $invoice->currency ?: 'JOD';
        $state = $invoice->state();
        $billTo = $invoice->bill_to ?: ($invoice->client?->name ?: $invoice->event?->client?->name);
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; }

        .accent { height: 5px; background: {{ $theme['accent'] }}; }
        .head { background: {{ $theme['primary'] }}; color: #fff; padding: 18px 34px 20px; }
        .brand { font-size: 9px; letter-spacing: 3px; color: {{ $theme['accent'] }}; font-weight: bold; }
        .head h1 { font-size: 26px; margin-top: 4px; letter-spacing: 1px; }
        .head .num { font-size: 11px; color: rgba(255,255,255,0.85); margin-top: 3px; }

        /* A stamp rather than a chip: on paper the state has to be readable
           from the far side of a desk, because that is how invoices are sorted. */
        .stamp { float: right; margin-top: 4px; border: 2px solid rgba(255,255,255,0.5);
                 border-radius: 4px; padding: 5px 12px; font-size: 12px; font-weight: bold;
                 letter-spacing: 2px; text-transform: uppercase; }

        .parties { display: table; width: 100%; padding: 20px 34px 12px; }
        .parties .col { display: table-cell; width: 50%; vertical-align: top; }
        .parties .col.r { text-align: right; }
        .lbl { font-size: 7.5px; letter-spacing: 1.6px; text-transform: uppercase; color: #94A3B8; font-weight: bold; }
        .val { font-size: 12px; font-weight: bold; margin-top: 3px; }
        .sub { font-size: 9.5px; color: #64748B; margin-top: 2px; }

        .dates { display: table; width: 100%; padding: 4px 34px 14px; }
        .dates .cell { display: table-cell; width: 25%; }
        .dates .n { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .overdue { color: #B91C1C; }

        .wrap { padding: 0 34px; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 7.5px; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8;
             text-align: right; padding: 8px 8px 5px; border-bottom: 1.5px solid #E2E8F0; }
        th.l { text-align: left; }
        td { font-size: 10px; padding: 8px; border-bottom: 1px solid #F1F5F9; text-align: right; }
        td.l { text-align: left; }
        td .desc { font-weight: bold; }

        .totals { width: 46%; margin-left: 54%; margin-top: 14px; }
        .totals td { border: 0; padding: 4px 8px; font-size: 10.5px; }
        .totals .k { text-align: left; color: #64748B; }
        .totals .rule td { border-top: 1px solid #E2E8F0; }
        .totals .grand td { font-size: 14px; font-weight: bold; color: {{ $theme['primary'] }};
                            border-top: 2px solid {{ $theme['primary'] }}; padding-top: 8px; }
        .totals .due td { font-size: 12px; font-weight: bold; color: #B91C1C; }

        .note { margin: 22px 34px 0; padding: 12px 14px; background: #F8FAFC;
                border-left: 3px solid {{ $theme['accent'] }}; font-size: 9.5px; color: #475569; }
        .note b { display: block; font-size: 7.5px; letter-spacing: 1.4px; text-transform: uppercase;
                  color: #94A3B8; margin-bottom: 4px; }

        .foot { position: fixed; bottom: 22px; left: 34px; right: 34px;
                border-top: 1px solid #E2E8F0; padding-top: 7px;
                font-size: 8px; color: #94A3B8; }
        .foot .r { float: right; }
    </style>
</head>
<body>
    <div class="accent"></div>

    <div class="head">
        <div class="stamp">{{ $invoice->stateLabel() }}</div>
        <div class="brand">{{ mb_strtoupper($company['name'] ?? 'ELITE BUSINESS HUB') }}</div>
        <h1>INVOICE</h1>
        <div class="num">{{ $invoice->number }}</div>
    </div>

    <div class="parties">
        <div class="col">
            <div class="lbl">Billed to</div>
            <div class="val">{{ $billTo ?: '—' }}</div>
            @if ($invoice->event)
                <div class="sub">{{ $invoice->event->name }}</div>
            @endif
            @if ($invoice->contract?->reference)
                <div class="sub">Against agreement {{ $invoice->contract->reference }}</div>
            @endif
        </div>

        <div class="col r">
            <div class="lbl">From</div>
            <div class="val">{{ $company['name'] ?? 'Elite Business Hub' }}</div>
            @foreach (array_filter([$company['address'] ?? null, $company['email'] ?? null, $company['phone'] ?? null]) as $line)
                <div class="sub">{{ $line }}</div>
            @endforeach
        </div>
    </div>

    <div class="dates">
        <div class="cell">
            <div class="lbl">Issued</div>
            <div class="n">{{ $invoice->issued_on?->format('j F Y') ?? '—' }}</div>
        </div>
        <div class="cell">
            <div class="lbl">Due</div>
            <div class="n {{ $state === 'overdue' ? 'overdue' : '' }}">{{ $invoice->due_on?->format('j F Y') ?? '—' }}</div>
        </div>
        <div class="cell">
            <div class="lbl">Currency</div>
            <div class="n">{{ $cur }}</div>
        </div>
        <div class="cell">
            <div class="lbl">Amount due</div>
            <div class="n {{ $out > 0 ? 'overdue' : '' }}">{{ $cur }} {{ $fmt($out) }}</div>
        </div>
    </div>

    <div class="wrap">
        <table>
            <thead>
                <tr>
                    <th class="l" style="width: 56%">Description</th>
                    <th style="width: 10%">Qty</th>
                    <th style="width: 17%">Unit</th>
                    <th style="width: 17%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($invoice->lines as $line)
                    <tr>
                        <td class="l"><span class="desc">{{ $line->description }}</span></td>
                        <td>{{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                        <td>{{ $fmt($line->unit_cents) }}</td>
                        <td>{{ $fmt($line->amountCents()) }}</td>
                    </tr>
                @empty
                    <tr><td class="l" colspan="4" style="color:#94A3B8">No lines on this invoice.</td></tr>
                @endforelse
            </tbody>
        </table>

        <table class="totals">
            <tr>
                <td class="k">Subtotal</td>
                <td>{{ $cur }} {{ $fmt($sub) }}</td>
            </tr>
            @if ($invoice->tax_pct)
                <tr>
                    <td class="k">Tax ({{ rtrim(rtrim(number_format($invoice->tax_pct, 2), '0'), '.') }}%)</td>
                    <td>{{ $cur }} {{ $fmt($tax) }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td class="k" style="color: inherit">Total</td>
                <td>{{ $cur }} {{ $fmt($total) }}</td>
            </tr>
            @if ($invoice->paid_cents > 0)
                {{-- A plain hyphen, not a typographic minus: DomPDF's core
                     Helvetica has no U+2212 and prints it as a question mark,
                     which on an invoice reads as a number nobody is sure of. --}}
                <tr class="rule">
                    <td class="k">Received</td>
                    <td>- {{ $cur }} {{ $fmt($invoice->paid_cents) }}</td>
                </tr>
                <tr class="due">
                    <td class="k" style="color: inherit">Balance due</td>
                    <td>{{ $cur }} {{ $fmt($out) }}</td>
                </tr>
            @endif
        </table>
    </div>

    @if ($invoice->notes || $invoice->terms)
        <div class="note">
            <b>{{ $invoice->terms ? 'Terms' : 'Note' }}</b>
            {{ $invoice->terms ?: $invoice->notes }}
            @if ($invoice->terms && $invoice->notes)
                <div style="margin-top:6px">{{ $invoice->notes }}</div>
            @endif
        </div>
    @endif

    <div class="foot">
        <span class="r">{{ $invoice->number }}</span>
        {{ $company['name'] ?? 'Elite Business Hub' }} · Generated {{ now()->format('j M Y') }}
    </div>
</body>
</html>
