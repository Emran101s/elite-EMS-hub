<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=amiri:400,700" rel="stylesheet">
    <style>{!! $css !!}</style>
    @php
        $navy = '#0B1F3A'; $gold = '#D4AF37';
        $money = fn ($c) => number_format(($c ?? 0) / 100, 3);
        $f = $data['financials']; $cur = $f['currency'];
        $est = $f['estimated_total_cents'] ?? 0;
    @endphp
    <style>
        @page { size: A4; margin: 0 0 62px 0; }   /* reserve the bottom band for the fixed footer */
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; box-sizing: border-box; }
        html, body { background:#fff; color:#26313F; }
        .sheet { width: 794px; margin: 0 auto; padding: 44px 54px 8px; }
        .en { font-family:'Spectral', Georgia, serif; }
        .ar { font-family:'Amiri', serif; direction: rtl; text-align: right; }
        .avoid { break-inside: avoid; }
        .pf { font-family:'Spectral', Georgia, serif; }

        .badge { display:inline-flex; width:34px; height:34px; align-items:center; justify-content:center;
                 background:{{ $navy }}; color:{{ $gold }}; font-weight:800; font-size:15px; border-radius:6px; }
        .rule-gold { height:2px; background:{{ $gold }}; }
        .clause-p-en { font-size:10px; line-height:1.65; color:#33415A; margin-top:7px; text-align:justify; }
        .clause-p-ar { font-size:11px; line-height:1.9; color:#33415A; margin-top:5px; margin-bottom:9px;
                       border-right:2px solid #EAE0C6; padding-right:12px; }

        table.t { width:100%; border-collapse:collapse; margin-top:10px; }
        table.t th { background:{{ $navy }}; color:#fff; font-size:8px; letter-spacing:.5px; text-transform:uppercase; text-align:left; padding:7px 10px; }
        table.t td { padding:8px 10px; border-bottom:1px solid #EAEEF4; font-size:10px; vertical-align:top; }
        table.t tr:nth-child(even) td { background:#FAFBFD; }
        .totrow td { background:{{ $navy }} !important; color:#fff; font-weight:bold; }
        .num { text-align:right; font-variant-numeric: tabular-nums; }

        .foot { position: fixed; bottom:16px; left:54px; right:54px; display:flex; justify-content:space-between;
                font-size:7px; letter-spacing:1.2px; text-transform:uppercase; color:#A9A18C;
                border-top:.75px solid #E4DCC9; padding-top:6px; }
        .foot .pn:after { content: counter(page) " / " counter(pages); }
    </style>
</head>
<body>

<div class="foot">
    <span>Elite Business Hub &middot; Events &amp; Logistics, Amman, Jordan</span>
    <span>{{ $data['meta']['confidentiality'] }} &middot; {{ $contract->reference }}</span>
</div>

<div class="sheet">

    {{-- ══ Header ══ --}}
    <div class="avoid" style="text-align:center;">
        <svg width="42" height="42" viewBox="0 0 40 40" style="margin:0 auto;">
            <rect x="20" y="3.5" width="23.3" height="23.3" rx="4" transform="rotate(45 20 3.5)" fill="none" stroke="{{ $gold }}" stroke-width="2.5"/>
            <rect x="20" y="12.5" width="10.6" height="10.6" rx="2" transform="rotate(45 20 12.5)" fill="{{ $gold }}"/>
        </svg>
        <div class="pf" style="font-size:15px; font-weight:700; letter-spacing:.5px; color:{{ $navy }}; margin-top:8px;">
            {{ $data['first_party']['name_en'] }}
        </div>
        <div class="ar" style="font-size:12px; color:#5B667A; margin-top:3px; text-align:center; direction:rtl;">
            {{ $data['first_party']['name_ar'] }}
        </div>
        <div class="rule-gold" style="margin:16px 0;"></div>
        <div style="font-size:9px; letter-spacing:5px; text-transform:uppercase; color:{{ $gold }}; font-weight:700;">Agreement &middot; اتفاقية</div>
        <h1 class="pf" style="font-size:23px; font-weight:800; color:{{ $navy }}; margin-top:8px;">{{ $data['meta']['title_en'] }}</h1>
        <div class="ar" style="font-size:18px; font-weight:700; color:{{ $navy }}; margin-top:4px; text-align:center; direction:rtl;">{{ $data['meta']['title_ar'] }}</div>
        <div class="pf" style="font-size:13px; font-style:italic; color:{{ $gold }}; margin-top:12px;">{{ $data['event']['name'] }}</div>
    </div>

    {{-- meta grid --}}
    <table class="t avoid" style="margin-top:18px;">
        <tr><th style="width:25%">Reference</th><td style="width:25%">{{ $contract->reference }}</td><th style="width:22%">Date</th><td>{{ $data['meta']['date'] }}</td></tr>
        <tr><th>Event Dates</th><td>{{ $data['event']['dates'] }}</td><th>Venue</th><td>{{ $data['event']['venue'] }}</td></tr>
        <tr><th>Location</th><td>{{ $data['event']['location'] }}</td><th>Estimated Total</th><td><strong>{{ $cur }} {{ $money($est) }}</strong></td></tr>
    </table>

    {{-- ══ Recitals / Parties ══ --}}
    <div class="avoid" style="margin-top:22px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <span class="badge">§</span>
            <div>
                <div class="pf" style="font-size:14px; font-weight:700; color:{{ $navy }};">Parties &amp; Recitals</div>
                <div class="ar" style="font-size:12px; color:#5B667A; direction:rtl;">الأطراف والتمهيد</div>
            </div>
        </div>
        <div class="rule-gold" style="margin:8px 0 4px; width:44px;"></div>
        @foreach ($recitals['en'] as $i => $p)
            <p class="clause-p-en">{{ $p }}</p>
            <p class="clause-p-ar ar">{{ $recitals['ar'][$i] ?? '' }}</p>
        @endforeach
    </div>

    {{-- ══ Clauses ══ --}}
    @foreach ($clauses as $c)
        <div class="avoid" style="margin-top:20px;">
            <div style="display:flex; align-items:center; gap:12px;">
                <span class="badge">{{ $c['n'] }}</span>
                <div style="flex:1; display:flex; justify-content:space-between; align-items:baseline; gap:10px;">
                    <div class="pf" style="font-size:14px; font-weight:700; color:{{ $navy }};">{{ $c['en_title'] }}</div>
                    <div class="ar" style="font-size:13px; font-weight:700; color:{{ $navy }}; direction:rtl;">{{ $c['ar_title'] }}</div>
                </div>
            </div>
            <div class="rule-gold" style="margin:8px 0 4px; width:44px;"></div>

            @foreach ($c['en'] as $i => $p)
                <p class="clause-p-en">{{ $p }}</p>
                <p class="clause-p-ar ar">{{ $c['ar'][$i] ?? '' }}</p>
            @endforeach

            @if (($c['type'] ?? '') === 'costshare')
                <table class="t">
                    <tr><th>Funding Entity</th><th class="ar" style="text-align:right; direction:rtl;">الجهة المموِّلة</th><th class="num">Share</th></tr>
                    @foreach ($c['rows'] as $r)
                        <tr>
                            <td><strong>{{ $r['name_en'] }}</strong></td>
                            <td class="ar" style="direction:rtl;">{{ $r['name_ar'] }}</td>
                            <td class="num"><strong>{{ $r['share'] }}%</strong> <span style="color:#94A3B8">· {{ $cur }} {{ $money($est * $r['share'] / 100) }}</span></td>
                        </tr>
                    @endforeach
                    <tr class="totrow"><td colspan="2">Total Guaranteed · إجمالي التغطية</td><td class="num">100% · {{ $cur }} {{ $money($est) }}</td></tr>
                </table>

            @elseif (($c['type'] ?? '') === 'schedule')
                <table class="t">
                    <tr><th style="width:14%">#</th><th class="num" style="width:16%">Installment</th><th>Due · الاستحقاق</th></tr>
                    @foreach ($c['schedule'] as $i => $s)
                        <tr>
                            <td>{{ $i + 1 }}</td>
                            <td class="num"><strong>{{ $s['pct'] }}%</strong><br><span style="color:#94A3B8; font-size:9px;">{{ $cur }} {{ $money($est * $s['pct'] / 100) }}</span></td>
                            <td>{{ $s['when_en'] }}<div class="ar" style="direction:rtl; color:#5B667A; margin-top:2px;">{{ $s['when_ar'] }}</div></td>
                        </tr>
                    @endforeach
                    <tr class="totrow"><td colspan="1">Total</td><td class="num">100%</td><td>{{ $cur }} {{ $money($est) }} · estimated</td></tr>
                </table>

            @elseif (($c['type'] ?? '') === 'list')
                <table class="t">
                    <tr><th style="width:26%">Category · الفئة</th><th>Policy · السياسة</th></tr>
                    @foreach ($c['items'] as $it)
                        <tr>
                            <td><strong>{{ $it['l_en'] }}</strong><div class="ar" style="direction:rtl; color:#5B667A; font-weight:400; margin-top:2px;">{{ $it['l_ar'] }}</div></td>
                            <td>{{ $it['t_en'] }}<div class="ar" style="direction:rtl; color:#46505F; margin-top:3px;">{{ $it['t_ar'] }}</div></td>
                        </tr>
                    @endforeach
                </table>
            @endif
        </div>
    @endforeach

    {{-- ══ Signatures ══ --}}
    <div class="avoid" style="margin-top:26px;">
        <div style="display:flex; align-items:center; gap:12px;">
            <span class="badge">✓</span>
            <div>
                <div class="pf" style="font-size:14px; font-weight:700; color:{{ $navy }};">Signatures</div>
                <div class="ar" style="font-size:12px; color:#5B667A; direction:rtl;">التواقيع</div>
            </div>
        </div>
        <div class="rule-gold" style="margin:8px 0 12px; width:44px;"></div>
        <p class="clause-p-en" style="margin-top:0;">By signing below, the parties confirm their acceptance of this Agreement and its terms.
            <span class="ar" style="direction:rtl; display:block; margin-top:3px;">بالتوقيع أدناه، يؤكّد الطرفان قبولهما لهذه الاتفاقية وشروطها.</span></p>

        @php
            $blocks = array_merge(
                [['role_en' => 'First Party — Elite Business Hub', 'role_ar' => 'الطرف الأول', 'name' => $data['first_party']['rep_en']]],
                array_map(fn ($p) => ['role_en' => 'Second Party — '.$p['name_en'], 'role_ar' => 'الطرف الثاني', 'name' => 'Authorised Representative'], $data['second_parties'])
            );
        @endphp
        <table style="width:100%; border-collapse:separate; border-spacing:10px; margin:6px -10px 0;">
            <tr>
                @foreach ($blocks as $b)
                    <td style="width:33.33%; border:.75px solid #E2DAC6; border-top:3px solid {{ $navy }}; border-radius:4px; padding:12px; vertical-align:top;">
                        <div style="font-size:7px; letter-spacing:.5px; text-transform:uppercase; color:{{ $gold }}; font-weight:700;">{{ $b['role_en'] }}</div>
                        <div class="ar" style="direction:rtl; font-size:9px; color:#94A3B8; margin-top:1px;">{{ $b['role_ar'] }}</div>
                        <div class="pf" style="font-size:11px; font-weight:700; color:{{ $navy }}; margin-top:8px;">{{ $b['name'] }}</div>
                        <div style="border-top:.75px dashed #C7BFA9; margin-top:34px; padding-top:3px; font-size:6.5px; letter-spacing:.5px; text-transform:uppercase; color:#A9A18C;">Signature &amp; Date · التوقيع والتاريخ</div>
                    </td>
                @endforeach
            </tr>
        </table>
    </div>

</div>
</body>
</html>
