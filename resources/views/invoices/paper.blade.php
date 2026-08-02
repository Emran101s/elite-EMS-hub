@props(['invoice', 'company', 'theme', 'screen' => false])

{{--
    The invoice document itself.

    One partial, two homes: the PDF wraps it in a page, the editor shows it
    beside the form. The preview IS the document, so nothing can look right on
    screen and wrong in the client's inbox.

    Plain HTML and table layout rather than flex or grid — DomPDF renders this
    partial too, and it understands neither. It costs nothing here: an invoice
    is a table.
--}}

@php
    $fmt = fn ($cents) => number_format($cents / 100, 2);
    $sub = $invoice->subtotalCents();
    $fee = $invoice->feeCents();
    $tax = $invoice->taxCents();
    $total = $invoice->totalCents();
    $out = $invoice->outstandingCents();
    $cur = $invoice->currency ?: 'JOD';
    $state = $invoice->state();
    $billTo = $invoice->bill_to ?: ($invoice->client?->name ?: $invoice->event?->client?->name);
@endphp

<style>
    .inv * { margin: 0; padding: 0; box-sizing: border-box; }
    .inv { font-family: 'Helvetica', 'Arial', sans-serif; color: #0F172A; background: #fff; }

    .inv .accent { height: 5px; background: {{ $theme['accent'] }}; }
    .inv .head { background: {{ $theme['primary'] }}; color: #fff; padding: 18px 34px 20px; }
    .inv .brand { font-size: 9px; letter-spacing: 3px; color: {{ $theme['accent'] }}; font-weight: bold; }
    .inv .head h1 { font-size: 26px; margin-top: 4px; letter-spacing: 1px; }
    .inv .head .num { font-size: 11px; color: rgba(255,255,255,0.85); margin-top: 3px; }

    /* A stamp rather than a chip: on paper the state has to be readable from
       the far side of a desk, because that is how invoices get sorted. */
    .inv .stamp { float: right; margin-top: 4px; border: 2px solid rgba(255,255,255,0.5);
                  border-radius: 4px; padding: 5px 12px; font-size: 12px; font-weight: bold;
                  letter-spacing: 2px; text-transform: uppercase; }

    .inv .parties { display: table; width: 100%; padding: 20px 34px 12px; }
    .inv .parties .col { display: table-cell; width: 50%; vertical-align: top; }
    .inv .parties .col.r { text-align: right; }
    .inv .lbl { font-size: 7.5px; letter-spacing: 1.6px; text-transform: uppercase; color: #94A3B8; font-weight: bold; }
    .inv .val { font-size: 12px; font-weight: bold; margin-top: 3px; }
    .inv .sub { font-size: 9.5px; color: #64748B; margin-top: 2px; }

    .inv .dates { display: table; width: 100%; padding: 4px 34px 14px; }
    .inv .dates .cell { display: table-cell; width: 25%; }
    .inv .dates .n { font-size: 11px; font-weight: bold; margin-top: 2px; }
    .inv .overdue { color: #B91C1C; }

    .inv .wrap { padding: 0 34px; }
    .inv table { width: 100%; border-collapse: collapse; }
    .inv th { font-size: 7.5px; letter-spacing: 1px; text-transform: uppercase; color: #94A3B8;
              text-align: right; padding: 8px 8px 5px; border-bottom: 1.5px solid #E2E8F0; }
    .inv th.l { text-align: left; }
    .inv td { font-size: 10px; padding: 8px; border-bottom: 1px solid #F1F5F9; text-align: right; }
    .inv td.l { text-align: left; }
    .inv td .desc { font-weight: bold; }

    .inv .totals { width: 46%; margin-left: 54%; margin-top: 14px; }
    .inv .totals td { border: 0; padding: 4px 8px; font-size: 10.5px; }
    .inv .totals .k { text-align: left; color: #64748B; }
    .inv .totals .rule td { border-top: 1px solid #E2E8F0; }
    .inv .totals .grand td { font-size: 14px; font-weight: bold; color: {{ $theme['primary'] }};
                             border-top: 2px solid {{ $theme['primary'] }}; padding-top: 8px; }
    .inv .totals .due td { font-size: 12px; font-weight: bold; color: #B91C1C; }

    .inv .note { margin: 22px 34px 0; padding: 12px 14px; background: #F8FAFC;
                 border-left: 3px solid {{ $theme['accent'] }}; font-size: 9.5px; color: #475569; line-height: 1.5; }
    .inv .note b { display: block; font-size: 7.5px; letter-spacing: 1.4px; text-transform: uppercase;
                   color: #94A3B8; margin-bottom: 4px; }

    .inv .pay { margin: 22px 34px 0; }
    .inv .pay > b { display: block; font-size: 7.5px; letter-spacing: 1.4px; text-transform: uppercase;
                    color: {{ $theme['primary'] }}; margin-bottom: 6px; }
    .inv .pay table { width: 100%; border-collapse: separate; border-spacing: 10px 0; table-layout: fixed; }
    {{-- text-align is stated, not inherited: the block sits after the totals
         table, whose cells are right-aligned, and inheriting that turned every
         line of the bank details flush right. --}}
    .inv .pay td { vertical-align: top; background: #F8FAFC; padding: 11px 13px; text-align: left; }
    .inv .pay .h { font-size: 8px; letter-spacing: 1.2px; text-transform: uppercase;
                   color: {{ $theme['primary'] }}; font-weight: bold; margin-bottom: 5px; }
    .inv .pay p { margin: 0 0 2px; font-size: 8.5px; color: #64748B; line-height: 1.45; }
    .inv .pay p b { color: #0F172A; }
    .inv .pay .mono b { font-family: {{ $screen ? "ui-monospace, SFMono-Regular, Menlo, monospace" : "'DejaVu Sans Mono', monospace" }}; letter-spacing: 0.2px; }

    .inv .foot { {{ $screen ? 'margin: 26px 34px 0;' : 'position: fixed; bottom: 22px; left: 34px; right: 34px;' }}
                 border-top: 1px solid #E2E8F0; padding-top: 7px; font-size: 8px; color: #94A3B8; }
    .inv .foot .r { float: right; }
    .inv .foot::after { content: ''; display: block; clear: both; }
</style>

<div class="inv">
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
            @if ($invoice->fee_pct)
                {{-- Its own row: a fee smeared across the lines is a fee the
                     client cannot see, and this is what the argument about the
                     bill is usually about. --}}
                <tr>
                    <td class="k">Management fee ({{ rtrim(rtrim(number_format($invoice->fee_pct, 2), '0'), '.') }}%)</td>
                    <td>{{ $cur }} {{ $fmt($fee) }}</td>
                </tr>
            @endif
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

    @if ($invoice->terms || $invoice->notes)
        <div class="note">
            <b>{{ $invoice->terms ? 'Terms' : 'Note' }}</b>
            {{ $invoice->terms ?: $invoice->notes }}
            @if ($invoice->terms && $invoice->notes)
                <div style="margin-top:6px">{{ $invoice->notes }}</div>
            @endif
        </div>
    @endif

    {{-- ══ Payment details ══

         Where to send the money, from Settings → Company. An invoice that
         does not say how to pay it is an invoice somebody has to reply to
         before they can pay it — and these details belong in one place, so a
         changed bank corrects every invoice raised afterwards rather than the
         ones somebody remembered to edit. --}}
    @php $accounts = \App\Models\CompanyProfile::bankAccounts(); @endphp

    @if ($accounts)
        <div class="pay">
            <b>Payment details</b>
            <table>
                <tr>
                    @foreach ($accounts as $a)
                        <td width="{{ (int) floor(100 / max(1, count($accounts))) }}%">
                            @if ($a['label'])<div class="h">{{ $a['label'] }}</div>@endif
                            @if ($a['account_name'])<p>Account Name: <b>{{ $a['account_name'] }}</b></p>@endif
                            @if ($a['bank_name'])<p>Bank Name: <b>{{ $a['bank_name'] }}</b></p>@endif
                            @if ($a['account_no'])<p class="mono">Account No.: <b>{{ $a['account_no'] }}</b></p>@endif
                            @if ($a['iban'])<p class="mono">IBAN: <b>{{ $a['iban'] }}</b></p>@endif
                            @if ($a['swift'])<p class="mono">Swift Code: <b>{{ $a['swift'] }}</b></p>@endif
                            @if ($a['currency'])<p>Currency: <b>{{ $a['currency'] }}</b></p>@endif
                        </td>
                    @endforeach
                </tr>
            </table>
        </div>
    @endif

    <div class="foot">
        <span class="r">{{ $invoice->number }}</span>
        {{ $company['name'] ?? 'Elite Business Hub' }} · Generated {{ now()->format('j M Y') }}
    </div>
</div>
