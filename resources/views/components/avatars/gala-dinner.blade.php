<svg {{ $attributes }} viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Gala Dinner — Luxury Ballroom">
    <defs>
        <linearGradient id="gd-sky" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#F8FAFC"/><stop offset="1" stop-color="#F1EBDD"/></linearGradient>
        <radialGradient id="gd-glow" cx="0.5" cy="0.4" r="0.6"><stop stop-color="#D4AF37" stop-opacity="0.5"/><stop offset="1" stop-color="#D4AF37" stop-opacity="0"/></radialGradient>
        <linearGradient id="gd-door" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#E3C766"/><stop offset="1" stop-color="#B8942C"/></linearGradient>
    </defs>
    <rect width="200" height="140" fill="url(#gd-sky)"/>
    <ellipse cx="100" cy="122" rx="86" ry="14" fill="#FFFFFF"/>
    <ellipse cx="100" cy="122" rx="86" ry="14" stroke="#E2E8F0"/>
    <ellipse cx="100" cy="86" rx="58" ry="34" fill="url(#gd-glow)"/>
    {{-- ballroom facade --}}
    <rect x="46" y="62" width="108" height="50" rx="3" fill="#FFFFFF"/>
    <rect x="46" y="62" width="108" height="50" rx="3" stroke="#E2E8F0"/>
    <rect x="42" y="56" width="116" height="8" rx="2" fill="#10141A"/>
    <rect x="42" y="54" width="116" height="3" rx="1.5" fill="#D4AF37"/>
    {{-- arched entrance --}}
    <path d="M86 112 L86 84 Q100 70 114 84 L114 112 Z" fill="url(#gd-door)"/>
    <path d="M86 112 L86 84 Q100 70 114 84 L114 112" stroke="#937423" stroke-opacity="0.4"/>
    <line x1="100" y1="74" x2="100" y2="112" stroke="#FFFFFF" stroke-opacity="0.45"/>
    {{-- columns --}}
    @foreach ([56, 70, 130, 144] as $x)
        <rect x="{{ $x }}" y="70" width="6" height="42" rx="1" fill="#F1F5F9"/>
        <rect x="{{ $x - 1 }}" y="68" width="8" height="3" rx="1" fill="#D4AF37"/>
    @endforeach
    {{-- string lights --}}
    <path d="M50 48 Q100 62 150 48" stroke="#D4AF37" stroke-opacity="0.6"/>
    @foreach ([62, 81, 100, 119, 138] as $x)
        <circle cx="{{ $x }}" cy="{{ 53 + abs(100 - $x) * -0.04 + 4 }}" r="1.6" fill="#E3C766"/>
    @endforeach
    {{-- gold carpet --}}
    <path d="M92 112 L108 112 L116 126 L84 126 Z" fill="#D4AF37" opacity="0.85"/>
</svg>
