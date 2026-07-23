@props([
    'eyebrow' => 'Elite Business Hub',
    'title' => '',
    'subtitle' => '',
    'chips' => [],          // [['n' => '20×14', 'l' => 'Metres'], …]
    'fixed' => false,       // fixed masthead (single-page DomPDF sheets)
    'serif' => false,       // Chrome docs use the Spectral display face for the title
    'navy' => '#0B1F3A',
    'gold' => '#D4AF37',
])
{{-- One shared masthead for every Elite Business Hub PDF — landscape or portrait,
     DomPDF or headless-Chrome. Inline styles only, so it renders identically in
     both engines. Chips use a dark translucent fill so the white figure stays
     high-contrast on the navy band. --}}
<div style="position:{{ $fixed ? 'fixed' : 'relative' }}; top:0; left:0; width:100%;{{ $fixed ? ' height:96px;' : '' }} background:{{ $navy }};">
    <table style="width:100%;{{ $fixed ? ' height:92px;' : '' }} border-collapse:collapse;"><tr>
        <td style="vertical-align:middle; padding:15px 28px;">
            <div style="font-size:8.5px; letter-spacing:3.5px; text-transform:uppercase; font-weight:bold; color:{{ $gold }};">{{ $eyebrow }}</div>
            <div style="font-size:21px; font-weight:800; color:#ffffff; margin:4px 0 3px; line-height:1.1;{{ $serif ? " font-family:'Spectral',serif;" : '' }}">{{ $title }}</div>
            @if ($subtitle)
                <div style="font-size:10px; color:rgba(255,255,255,0.75);">{{ $subtitle }}</div>
            @endif
        </td>
        @if (count($chips))
            <td style="vertical-align:middle; padding:15px 28px; text-align:right; white-space:nowrap; width:1%;">
                <table align="right" style="border-collapse:separate; border-spacing:7px 0;"><tr>
                    @foreach ($chips as $c)
                        <td style="border:1px solid rgba(212,175,55,0.55); border-radius:7px; background:rgba(255,255,255,0.07); padding:7px 13px; text-align:center;">
                            <div style="font-size:18px; font-weight:800; color:#ffffff; line-height:1;">{{ $c['n'] }}</div>
                            <div style="font-size:6.5px; letter-spacing:1.5px; text-transform:uppercase; font-weight:bold; color:{{ $gold }}; margin-top:4px;">{{ $c['l'] }}</div>
                        </td>
                    @endforeach
                </tr></table>
            </td>
        @endif
    </tr></table>
    <div style="position:absolute; left:0; bottom:0; width:100%; height:4px; background:{{ $gold }};"></div>
</div>
