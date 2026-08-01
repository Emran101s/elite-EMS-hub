<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $fmt = fn ($cents) => number_format($cents / 100, 2);
        $sub = $proposal->subtotalCents();
        $tax = $proposal->taxCents();
        $total = $proposal->totalCents();
        $optional = $proposal->lines->filter->optional;
        $included = $proposal->lines->reject->optional;
        $cur = $proposal->currency ?: 'JOD';
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; }

        .accent { height: 5px; background: {{ $theme['accent'] }}; }
        .head { background: {{ $theme['primary'] }}; color: #fff; padding: 20px 34px 22px; }
        .brand { font-size: 9px; letter-spacing: 3px; color: {{ $theme['accent'] }}; font-weight: bold; }
        .head h1 { font-size: 23px; margin-top: 5px; line-height: 1.2; }
        .head .num { font-size: 10.5px; color: rgba(255,255,255,0.8); margin-top: 4px; }

        .parties { display: table; width: 100%; padding: 20px 34px 10px; }
        .parties .col { display: table-cell; width: 50%; vertical-align: top; }
        .parties .col.r { text-align: right; }
        .lbl { font-size: 7.5px; letter-spacing: 1.6px; text-transform: uppercase; color: #94A3B8; font-weight: bold; }
        .val { font-size: 12px; font-weight: bold; margin-top: 3px; }
        .sub { font-size: 9.5px; color: #64748B; margin-top: 2px; }

        .dates { display: table; width: 100%; padding: 4px 34px 14px; }
        .dates .cell { display: table-cell; width: 33.33%; }
        .dates .n { font-size: 11px; font-weight: bold; margin-top: 2px; }
        .lapsed { color: #B91C1C; }

        .summary { margin: 0 34px 16px; padding: 12px 14px; background: #F8FAFC;
                   border-left: 3px solid {{ $theme['accent'] }}; font-size: 10px;
                   line-height: 1.55; color: #334155; }

        .wrap { padding: 0 34px; }
        .grp { font-size: 8px; letter-spacing: 1.6px; text-transform: uppercase;
               color: {{ $theme['primary'] }}; font-weight: bold; padding: 14px 0 5px; }
        table { width: 100%; border-collapse: collapse; }
        th { font-size: 7.5px; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8;
             text-align: right; padding: 7px 8px 5px; border-bottom: 1.5px solid #E2E8F0; }
        th.l { text-align: left; }
        td { font-size: 10px; padding: 7px 8px; border-bottom: 1px solid #F1F5F9; text-align: right; }
        td.l { text-align: left; }
        td .desc { font-weight: bold; }
        td .detail { display: block; font-size: 9px; color: #64748B; margin-top: 2px; line-height: 1.45; }

        /* Optional work is quoted and not counted, and the document has to say
           so on its face — a client reading a total that includes something
           they did not ask for is a conversation nobody wants. */
        .optional td { background: #FFFBEB; }
        .optional-note { font-size: 9px; color: #92400E; padding: 6px 8px 0; }

        .totals { width: 46%; margin-left: 54%; margin-top: 14px; }
        .totals td { border: 0; padding: 4px 8px; font-size: 10.5px; }
        .totals .k { text-align: left; color: #64748B; }
        .totals .grand td { font-size: 15px; font-weight: bold; color: {{ $theme['primary'] }};
                            border-top: 2px solid {{ $theme['primary'] }}; padding-top: 9px; }

        .terms { margin: 24px 34px 0; padding-top: 10px; border-top: 1px solid #E2E8F0;
                 font-size: 9.5px; line-height: 1.55; color: #475569; }
        .terms b { display: block; font-size: 7.5px; letter-spacing: 1.4px; text-transform: uppercase;
                   color: #94A3B8; margin-bottom: 5px; }

        .sign { margin: 26px 34px 0; display: table; width: calc(100% - 68px); }
        .sign .box { display: table-cell; width: 50%; padding-right: 20px; }
        .sign .rule { border-bottom: 1px solid #94A3B8; height: 34px; }
        .sign .cap { font-size: 8px; letter-spacing: 1px; text-transform: uppercase;
                     color: #94A3B8; margin-top: 5px; }

        .foot { position: fixed; bottom: 22px; left: 34px; right: 34px;
                border-top: 1px solid #E2E8F0; padding-top: 7px; font-size: 8px; color: #94A3B8; }
        .foot .r { float: right; }
    </style>
</head>
<body>
    <div class="accent"></div>

    <div class="head">
        <div class="brand">{{ mb_strtoupper($company['name'] ?? 'ELITE BUSINESS HUB') }}</div>
        <h1>{{ $proposal->title }}</h1>
        <div class="num">Proposal {{ $proposal->number }}</div>
    </div>

    <div class="parties">
        <div class="col">
            <div class="lbl">Prepared for</div>
            <div class="val">{{ $proposal->client?->name ?? '—' }}</div>
            @if ($proposal->contact?->name)
                <div class="sub">{{ $proposal->contact->name }}</div>
            @endif
            @if ($proposal->contact?->email)
                <div class="sub">{{ $proposal->contact->email }}</div>
            @endif
        </div>

        <div class="col r">
            <div class="lbl">Prepared by</div>
            <div class="val">{{ $company['name'] ?? 'Elite Business Hub' }}</div>
            @if ($proposal->owner?->name)
                <div class="sub">{{ $proposal->owner->name }}</div>
            @endif
            @foreach (array_filter([$company['email'] ?? null, $company['phone'] ?? null]) as $line)
                <div class="sub">{{ $line }}</div>
            @endforeach
        </div>
    </div>

    <div class="dates">
        <div class="cell">
            <div class="lbl">Issued</div>
            <div class="n">{{ $proposal->issued_on?->format('j F Y') ?? '—' }}</div>
        </div>
        <div class="cell">
            <div class="lbl">Valid until</div>
            <div class="n {{ $proposal->state() === 'expired' ? 'lapsed' : '' }}">
                {{ $proposal->valid_until?->format('j F Y') ?? 'No expiry' }}
            </div>
        </div>
        <div class="cell">
            <div class="lbl">Total</div>
            <div class="n">{{ $cur }} {{ $fmt($total) }}</div>
        </div>
    </div>

    @if ($proposal->summary)
        <div class="summary">{{ $proposal->summary }}</div>
    @endif

    <div class="wrap">
        <div class="grp">What is included</div>
        <table>
            <thead>
                <tr>
                    <th class="l" style="width: 58%">Item</th>
                    <th style="width: 10%">Qty</th>
                    <th style="width: 16%">Unit</th>
                    <th style="width: 16%">Amount</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($included as $line)
                    <tr>
                        <td class="l">
                            <span class="desc">{{ $line->description }}</span>
                            @if ($line->detail)<span class="detail">{{ $line->detail }}</span>@endif
                        </td>
                        <td>{{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                        <td>{{ $fmt($line->unit_cents) }}</td>
                        <td>{{ $fmt($line->amountCents()) }}</td>
                    </tr>
                @empty
                    <tr><td class="l" colspan="4" style="color:#94A3B8">Nothing quoted yet.</td></tr>
                @endforelse
            </tbody>
        </table>

        @if ($optional->isNotEmpty())
            <div class="grp">Optional extras</div>
            <table>
                <tbody>
                    @foreach ($optional as $line)
                        <tr class="optional">
                            <td class="l" style="width: 58%">
                                <span class="desc">{{ $line->description }}</span>
                                @if ($line->detail)<span class="detail">{{ $line->detail }}</span>@endif
                            </td>
                            <td style="width: 10%">{{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }}</td>
                            <td style="width: 16%">{{ $fmt($line->unit_cents) }}</td>
                            <td style="width: 16%">{{ $fmt($line->amountCents()) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
            <div class="optional-note">
                Optional extras are quoted for your consideration and are not included in the total below.
            </div>
        @endif

        <table class="totals">
            <tr>
                <td class="k">Subtotal</td>
                <td>{{ $cur }} {{ $fmt($sub) }}</td>
            </tr>
            @if ($proposal->tax_pct)
                <tr>
                    <td class="k">Tax ({{ rtrim(rtrim(number_format($proposal->tax_pct, 2), '0'), '.') }}%)</td>
                    <td>{{ $cur }} {{ $fmt($tax) }}</td>
                </tr>
            @endif
            <tr class="grand">
                <td class="k" style="color: inherit">Total</td>
                <td>{{ $cur }} {{ $fmt($total) }}</td>
            </tr>
        </table>
    </div>

    @if ($proposal->terms)
        <div class="terms">
            <b>Terms</b>
            {{ $proposal->terms }}
        </div>
    @endif

    <div class="sign">
        <div class="box">
            <div class="rule"></div>
            <div class="cap">Accepted for {{ $proposal->client?->name ?? 'the client' }}</div>
        </div>
        <div class="box">
            <div class="rule"></div>
            <div class="cap">Date</div>
        </div>
    </div>

    <div class="foot">
        <span class="r">{{ $proposal->number }}</span>
        {{ $company['name'] ?? 'Elite Business Hub' }} · Prepared {{ $proposal->issued_on?->format('j M Y') ?? now()->format('j M Y') }}
    </div>
</body>
</html>
