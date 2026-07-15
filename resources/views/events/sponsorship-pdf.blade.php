<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    @php
        $accentFor = function (string $name) use ($theme) {
            return match (mb_strtolower(trim($name))) {
                'platinum' => '#1E3352', 'gold' => '#D4AF37', 'silver' => '#94A3B8', 'bronze' => '#B45309',
                'media partner' => '#0EA5E9', 'strategic partner' => '#6366F1', 'airlines partner' => '#2563EB',
                'tourism partner' => '#14B8A6', default => $theme['accent'],
            };
        };
        $textOn = fn ($hex) => mb_strtolower($hex) === '#d4af37' ? '#1E3352' : '#ffffff';
        $money = fn ($c) => $event->money($c);
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; color: #1E3352; font-size: 11px; }
        .hero { background: {{ $theme['primary'] }}; color: #fff; padding: 26px 30px; }
        .eyebrow { color: {{ $theme['accent'] }}; font-size: 9px; letter-spacing: 2px; text-transform: uppercase; font-weight: bold; }
        .title { font-size: 22px; font-weight: bold; margin-top: 12px; }
        .meta { color: rgba(255,255,255,0.7); font-size: 10px; margin-top: 4px; }
        .ptitle { color: {{ $theme['accent'] }}; font-size: 15px; font-weight: bold; margin-top: 16px; }
        .intro { color: rgba(255,255,255,0.7); font-size: 10px; margin-top: 4px; line-height: 1.5; }
        .wrap { padding: 20px 30px; }
        .pkg { border: 1px solid #e2e8f0; border-radius: 8px; margin-bottom: 14px; overflow: hidden; }
        .pkg-head { padding: 12px 16px; color: #fff; }
        .pkg-name { font-size: 14px; font-weight: bold; }
        .pkg-price { font-size: 18px; font-weight: bold; margin-top: 2px; }
        .pkg-blurb { font-size: 10px; margin-top: 3px; opacity: 0.9; }
        .pkg-body { padding: 12px 16px; }
        .benefit { font-size: 10.5px; margin-bottom: 5px; line-height: 1.35; }
        .benefit .tick { font-weight: bold; }
        .foot { border-top: 1px solid #e2e8f0; padding: 16px 30px; color: #64748b; font-size: 10px; }
        .foot b { color: #1E3352; }
    </style>
</head>
<body>
    <div class="hero">
        <div class="eyebrow">&#9670; Elite Business Hub</div>
        <div class="title">{{ $event->name }}</div>
        <div class="meta">{{ $event->city }}@if ($event->city && $event->country), {{ $event->country }}@endif ·
            {{ $event->starts_at?->format('M j') }} – {{ $event->ends_at?->format('M j, Y') ?? $event->starts_at?->format('Y') }}@if ($event->expected_participants) · {{ number_format($event->expected_participants) }} delegates @endif</div>
        <div class="ptitle">{{ $single ? $single->name.' — Partnership Opportunity' : 'Sponsorship & Partnership Prospectus' }}</div>
        <div class="intro">Partner with us to reach a curated audience of decision-makers and leaders. Choose the package that fits your goals.</div>
    </div>

    <div class="wrap">
        @foreach ($packages as $p)
            @php $acc = $accentFor($p->name); $tc = $textOn($acc); @endphp
            <div class="pkg">
                @php $sold = $soldByPackage[$p->name] ?? 0; $left = $p->slots !== null ? max(0, $p->slots - $sold) : null; @endphp
                <div class="pkg-head" style="background: {{ $acc }}; color: {{ $tc }};">
                    @if ($p->slots !== null)<div style="font-size:8.5px; text-transform:uppercase; letter-spacing:1px; opacity:0.85;">{{ $left === 0 ? 'Sold out' : ($left === 1 ? 'Only 1 available' : $left.' available') }}</div>@endif
                    <div class="pkg-name">{{ $p->name }}</div>
                    <div class="pkg-price">{{ $p->price_cents ? $money($p->price_cents) : 'On request' }}</div>
                    @if ($p->blurb)<div class="pkg-blurb">{{ $p->blurb }}</div>@endif
                </div>
                <div class="pkg-body">
                    @if (! empty($p->benefits))
                        @foreach ($p->benefits as $b)
                            <div class="benefit"><span class="tick" style="color: {{ $acc === '#D4AF37' ? '#B8912E' : $acc }};">&#10003;</span> {{ $b }}</div>
                        @endforeach
                    @else
                        <div class="benefit" style="color:#94a3b8;">Benefits to be confirmed.</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>

    <div class="foot">
        <b>Ready to partner with {{ $event->name }}?</b> Contact Elite Business Hub to confirm your package and secure your placement.
    </div>
</body>
</html>
