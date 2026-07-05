<svg {{ $attributes }} viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="VIP Event — Private Luxury Villa">
    <defs>
        <linearGradient id="vip-sky" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#F8FAFC"/><stop offset="1" stop-color="#F4F6FB"/></linearGradient>
        <linearGradient id="vip-pool" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#93C5FD"/><stop offset="1" stop-color="#3B82F6"/></linearGradient>
        <linearGradient id="vip-door" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#E3C766"/><stop offset="1" stop-color="#B8942C"/></linearGradient>
        <linearGradient id="vip-pod" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#EFD98E"/><stop offset="0.55" stop-color="#D4AF37"/><stop offset="1" stop-color="#8F6F1F"/></linearGradient>
    </defs>
    <rect width="200" height="140" fill="url(#vip-sky)"/>
    {{-- gold podium --}}
    <ellipse cx="100" cy="130" rx="80" ry="7" fill="#0B1F3A" opacity="0.10"/>
    <path d="M18 112 L18 122 A82 13 0 0 0 182 122 L182 112 A82 13 0 0 1 18 112 Z" fill="url(#vip-pod)"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" fill="#F7F3E6"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" stroke="#D4AF37" stroke-opacity="0.55" stroke-width="0.8"/>
    <ellipse cx="100" cy="112" rx="68" ry="9.5" fill="#FFFFFF" opacity="0.65"/>
    {{-- two-tier marble villa --}}
    <rect x="52" y="74" width="96" height="38" rx="3" fill="#FFFFFF"/>
    <rect x="52" y="74" width="96" height="38" rx="3" stroke="#E2E8F0"/>
    <rect x="48" y="70" width="104" height="5" rx="2" fill="#0B1F3A"/>
    <rect x="48" y="68" width="104" height="2.5" rx="1.25" fill="#D4AF37"/>
    <rect x="70" y="52" width="60" height="18" rx="2" fill="#F8FAFC"/>
    <rect x="70" y="52" width="60" height="18" rx="2" stroke="#E2E8F0"/>
    <rect x="67" y="48" width="66" height="4" rx="2" fill="#0B1F3A"/>
    <rect x="78" y="57" width="12" height="8" rx="1" fill="#0B1F3A" opacity="0.8"/>
    <rect x="110" y="57" width="12" height="8" rx="1" fill="#0B1F3A" opacity="0.8"/>
    {{-- tall gold door + columns --}}
    <rect x="92" y="84" width="16" height="28" rx="1.5" fill="url(#vip-door)"/>
    <line x1="100" y1="84" x2="100" y2="112" stroke="#FFFFFF" stroke-opacity="0.4"/>
    @foreach ([62, 76, 124, 138] as $x)
        <rect x="{{ $x }}" y="80" width="5" height="32" rx="1" fill="#F1F5F9"/>
        <rect x="{{ $x - 1 }}" y="78" width="7" height="2.5" rx="1" fill="#D4AF37"/>
    @endforeach
    {{-- reflection pool --}}
    <rect x="76" y="116" width="48" height="8" rx="4" fill="url(#vip-pool)" opacity="0.85"/>
    {{-- palms --}}
    @foreach ([36, 164] as $x)
        <line x1="{{ $x }}" y1="118" x2="{{ $x }}" y2="100" stroke="#937423" stroke-width="1.5"/>
        <path d="M{{ $x }} 100 Q{{ $x - 9 }} 96 {{ $x - 12 }} 100 M{{ $x }} 100 Q{{ $x + 9 }} 96 {{ $x + 12 }} 100 M{{ $x }} 100 Q{{ $x - 5 }} 90 {{ $x - 10 }} 90 M{{ $x }} 100 Q{{ $x + 5 }} 90 {{ $x + 10 }} 90" stroke="#16A34A" stroke-width="1.5" stroke-linecap="round"/>
    @endforeach
</svg>
