@props([
    'sheet' => '',          // e.g. "FP-12", "AV-12"
    'fixed' => false,
    'navy' => '#0B1F3A',
    'gold' => '#D4AF37',
])
{{-- Shared footer band, paired with <x-pdf-header>. --}}
<div style="position:{{ $fixed ? 'fixed' : 'relative' }}; bottom:0; left:0; width:100%; height:26px; background:{{ $navy }};">
    <table style="width:100%; height:26px; border-collapse:collapse;"><tr>
        <td style="vertical-align:middle; padding:0 28px; font-size:7.5px; color:rgba(255,255,255,0.62); letter-spacing:0.5px;">Generated {{ now()->format('M j, Y · H:i') }}</td>
        <td style="vertical-align:middle; text-align:center; font-size:7.5px; letter-spacing:2px; font-weight:bold; color:{{ $gold }};">ELITE BUSINESS HUB</td>
        <td style="vertical-align:middle; padding:0 28px; text-align:right; font-size:7.5px; color:rgba(255,255,255,0.62); letter-spacing:0.5px;">Operations Command Center{{ $sheet ? ' · Sheet '.$sheet : '' }}</td>
    </tr></table>
</div>
