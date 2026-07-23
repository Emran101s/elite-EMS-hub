<!doctype html>
{{--
    The generic document sheet: vendor / speaker / sponsorship agreements and
    letters. One identity with the client MSA — navy masthead, gold accents,
    Spectral for English, Amiri for Arabic — bilingual side-by-side when the
    document asks for it, clean single-column English when it doesn't.
--}}
@php
    $navy = '#0B1F3A';
    $gold = '#D4AF37';
    $isLetter = $contract->isLetter();
    $blocks = $data['blocks'] ?? [];
    $cp = $data['counterparty'] ?? [];
@endphp
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $contract->displayTitle() }}</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=spectral:500,600,700,800" rel="stylesheet">
    <link href="https://fonts.bunny.net/css?family=amiri:400,700" rel="stylesheet">
    <style>{!! $css !!}</style>
    <style>
        @page { size: A4; margin: 0 0 56px 0; }
        * { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        body { margin: 0; font-family: 'Spectral', Georgia, serif; color: #16202E; }
        .ar { font-family: 'Amiri', serif; direction: rtl; text-align: right; }
        .avoid { break-inside: avoid; }
        .p-en { font-size: 10px; line-height: 1.7; color: #33415A; margin: 6px 0 0; text-align: justify; }
        .p-ar { font-size: 11px; line-height: 1.9; color: #33415A; margin: 5px 0 0; }
    </style>
</head>
<body>

    {{-- ═══ masthead ═══ --}}
    <div style="background: linear-gradient(135deg, {{ $navy }}, #061225); color: #fff; padding: 26px 40px;">
        <div style="font-size: 8.5px; letter-spacing: 3.5px; text-transform: uppercase; font-weight: 700; color: {{ $gold }};">
            Elite Business Hub · {{ $contract->typeLabel() }}
        </div>
        <div style="font-size: 21px; font-weight: 800; margin: 5px 0 3px;">{{ $contract->title ?: $contract->typeLabel() }}</div>
        <div style="font-size: 10px; color: rgba(255,255,255,0.72);">
            {{ $data['event']['name'] ?? $event->name }} · {{ $data['event']['dates'] ?? '' }} · {{ $data['event']['location'] ?? '' }}
        </div>
    </div>

    <div style="padding: 26px 40px;">

        {{-- ═══ parties strip ═══ --}}
        @unless ($isLetter)
            <table style="width:100%; border-collapse:separate; border-spacing:8px 0; margin:0 -8px 16px;">
                <tr>
                    <td style="width:50%; border:.75px solid #E2DAC6; border-top:3px solid {{ $navy }}; border-radius:4px; padding:10px 12px; vertical-align:top;">
                        <div style="font-size:7px; letter-spacing:.6px; text-transform:uppercase; color:{{ $gold }}; font-weight:700;">The Organiser</div>
                        <div style="font-size:11px; font-weight:700; margin-top:3px;">Elite Business Hub</div>
                    </td>
                    <td style="width:50%; border:.75px solid #E2DAC6; border-top:3px solid {{ $navy }}; border-radius:4px; padding:10px 12px; vertical-align:top;">
                        <div style="font-size:7px; letter-spacing:.6px; text-transform:uppercase; color:{{ $gold }}; font-weight:700;">
                            {{ ['vendor' => 'The Supplier', 'speaker' => 'The Speaker', 'sponsorship' => 'The Sponsor'][$contract->type] ?? 'The Counterparty' }}
                        </div>
                        <div style="font-size:11px; font-weight:700; margin-top:3px;">{{ $cp['name_en'] ?: 'To be confirmed' }}</div>
                        @if (($cp['email'] ?? '') || ($cp['phone'] ?? ''))
                            <div style="font-size:8px; color:#7C889B; margin-top:2px;">{{ collect([$cp['email'] ?? null, $cp['phone'] ?? null])->filter()->implode(' · ') }}</div>
                        @endif
                    </td>
                </tr>
            </table>
        @endunless

        {{-- ═══ clauses ═══ --}}
        @foreach ($blocks as $bi => $b)
            <div class="avoid" style="margin-bottom: 14px;">
                @unless ($isLetter)
                    <table style="width:100%; border-collapse:collapse; border-bottom:1.25px solid {{ $navy }};">
                        <tr>
                            <td style="padding-bottom:3px;">
                                <span style="color:{{ $gold }}; font-weight:800; font-size:11px;">{{ $bi + 1 }}.</span>
                                <span style="font-weight:700; font-size:11.5px;">{{ $b['title_en'] ?: 'Clause' }}</span>
                            </td>
                            @if ($bilingual && ($b['title_ar'] ?? ''))
                                <td class="ar" style="padding-bottom:3px; font-weight:700; font-size:11.5px; color:#33415A;">{{ $b['title_ar'] }}</td>
                            @endif
                        </tr>
                    </table>
                @endunless

                @foreach ($b['en'] ?? [] as $p => $para)
                    @if ($bilingual && (($b['ar'][$p] ?? '') !== ''))
                        <table style="width:100%; border-collapse:collapse;">
                            <tr>
                                <td style="width:50%; vertical-align:top; padding-right:9px;"><p class="p-en">{{ $para }}</p></td>
                                <td class="ar" style="width:50%; vertical-align:top; padding-left:9px;"><p class="p-ar ar">{{ $b['ar'][$p] }}</p></td>
                            </tr>
                        </table>
                    @else
                        <p class="p-en" @if ($isLetter) style="font-size:11px; line-height:1.85;" @endif>{{ $para }}</p>
                    @endif
                @endforeach

                @if (! empty($b['items']))
                    <table style="width:100%; border-collapse:collapse; margin-top:5px;">
                        @foreach ($b['items'] as $it)
                            <tr>
                                <td style="padding:2.5px 0; font-size:9.5px; color:#33415A; border-bottom:.5px solid #EFF2F6;">
                                    <span style="color:{{ $gold }};">◆</span>
                                    <b>{{ $it['l_en'] ?? '' }}</b>@if ($it['t_en'] ?? '') — {{ $it['t_en'] }}@endif
                                </td>
                                @if ($bilingual && (($it['l_ar'] ?? '') !== ''))
                                    <td class="ar" style="padding:2.5px 0; font-size:10px; color:#33415A; border-bottom:.5px solid #EFF2F6;">
                                        <b>{{ $it['l_ar'] }}</b>@if ($it['t_ar'] ?? '') — {{ $it['t_ar'] }}@endif
                                    </td>
                                @endif
                            </tr>
                        @endforeach
                    </table>
                @endif
            </div>
        @endforeach

        {{-- ═══ signatures — driven by the signatory records ═══ --}}
        @if ($signatories->isNotEmpty())
            <div class="avoid" style="margin-top: 22px;">
                <div style="font-size:8px; letter-spacing:1.5px; text-transform:uppercase; font-weight:700; color:{{ $gold }}; border-bottom:1.25px solid {{ $navy }}; padding-bottom:3px;">
                    {{ $isLetter ? 'Issued by' : 'Signatures · التواقيع' }}
                </div>
                <table style="width:100%; border-collapse:separate; border-spacing:8px; margin:4px -8px 0;">
                    <tr>
                        @foreach ($signatories as $s)
                            <td style="width:{{ (int) (100 / max(1, $signatories->count())) }}%; border:.75px solid {{ $s->isSigned() ? '#BFD9CC' : '#E2DAC6' }}; border-top:3px solid {{ $navy }}; border-radius:4px; padding:11px; vertical-align:top;">
                                <div style="font-size:7px; letter-spacing:.5px; text-transform:uppercase; color:{{ $gold }}; font-weight:700;">{{ $s->roleLabel() }}</div>
                                <div style="font-size:10.5px; font-weight:700; margin-top:7px;">{{ $s->name }}</div>
                                @if ($s->isSigned())
                                    <div style="margin-top:13px; font-size:14px; font-style:italic;">{{ $s->signature_data }}</div>
                                    <div style="border-top:.75px solid #BFD9CC; margin-top:4px; padding-top:3px; font-size:6.5px; letter-spacing:.4px; text-transform:uppercase; color:#3F8E6E; font-weight:700;">✓ Signed {{ $s->signed_at->format('j M Y · H:i') }}</div>
                                    <div style="margin-top:2px; font-size:5.5px; color:#A9A18C; font-family:monospace;">verify {{ substr($s->signed_hash ?? '', 0, 12) }}</div>
                                @else
                                    <div style="border-top:.75px dashed #C7BFA9; margin-top:30px; padding-top:3px; font-size:6.5px; letter-spacing:.5px; text-transform:uppercase; color:#A9A18C;">Signature &amp; Date · التوقيع والتاريخ</div>
                                @endif
                            </td>
                        @endforeach
                    </tr>
                </table>
            </div>
        @endif
    </div>

    {{-- fixed footer band --}}
    <div style="position:fixed; bottom:0; left:0; right:0; height:40px; background:{{ $navy }}; color:rgba(255,255,255,0.65);">
        <table style="width:100%; border-collapse:collapse; height:40px;">
            <tr>
                <td style="padding:0 40px; font-size:7px; letter-spacing:1px; text-transform:uppercase;">Generated {{ now()->format('j M Y') }}</td>
                <td style="text-align:center; font-size:8px; letter-spacing:2.5px; text-transform:uppercase; font-weight:700; color:{{ $gold }};">Elite Business Hub</td>
                <td style="padding:0 40px; text-align:right; font-size:7px; letter-spacing:1px; text-transform:uppercase;">{{ $contract->reference }}</td>
            </tr>
        </table>
    </div>

</body>
</html>
