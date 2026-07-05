<svg {{ $attributes }} viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Festival / Outdoor Event — Outdoor Event Island">
    <defs>
        <linearGradient id="fo-sky" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#F8FAFC"/><stop offset="1" stop-color="#FBF7EE"/></linearGradient>
        <linearGradient id="fo-water" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#93C5FD"/><stop offset="1" stop-color="#3B82F6"/></linearGradient>
        <linearGradient id="fo-stage" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#FBBF24"/><stop offset="1" stop-color="#F59E0B"/></linearGradient>
        <linearGradient id="fo-pod" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#EFD98E"/><stop offset="0.55" stop-color="#D4AF37"/><stop offset="1" stop-color="#8F6F1F"/></linearGradient>
    </defs>
    <rect width="200" height="140" fill="url(#fo-sky)"/>
    {{-- gold podium with water ring + island deck --}}
    <ellipse cx="100" cy="130" rx="80" ry="7" fill="#0B1F3A" opacity="0.10"/>
    <path d="M18 112 L18 122 A82 13 0 0 0 182 122 L182 112 A82 13 0 0 1 18 112 Z" fill="url(#fo-pod)"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" fill="url(#fo-water)" opacity="0.6"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" stroke="#D4AF37" stroke-opacity="0.55" stroke-width="0.8"/>
    <ellipse cx="100" cy="111" rx="66" ry="10" fill="#FDF6E3"/>
    {{-- stage shell --}}
    <path d="M64 108 A30 30 0 0 1 124 108 Z" fill="url(#fo-stage)"/>
    <path d="M72 108 A22 22 0 0 1 116 108 Z" fill="#FFFFFF" opacity="0.35"/>
    <rect x="64" y="106" width="60" height="4" rx="2" fill="#0B1F3A"/>
    {{-- tents --}}
    @foreach ([['x' => 40, 'c' => '#22C55E'], ['x' => 140, 'c' => '#3B82F6'], ['x' => 156, 'c' => '#F59E0B']] as $tent)
        <path d="M{{ $tent['x'] }} 112 L{{ $tent['x'] + 7 }} 100 L{{ $tent['x'] + 14 }} 112 Z" fill="{{ $tent['c'] }}" opacity="0.9"/>
    @endforeach
    {{-- palms --}}
    @foreach ([28, 172] as $x)
        <line x1="{{ $x }}" y1="112" x2="{{ $x }}" y2="94" stroke="#937423" stroke-width="1.5"/>
        <path d="M{{ $x }} 94 Q{{ $x - 9 }} 90 {{ $x - 12 }} 94 M{{ $x }} 94 Q{{ $x + 9 }} 90 {{ $x + 12 }} 94 M{{ $x }} 94 Q{{ $x - 5 }} 84 {{ $x - 10 }} 84 M{{ $x }} 94 Q{{ $x + 5 }} 84 {{ $x + 10 }} 84" stroke="#16A34A" stroke-width="1.5" stroke-linecap="round"/>
    @endforeach
    {{-- bunting --}}
    <path d="M48 60 Q100 76 152 60" stroke="#94A3B8" stroke-opacity="0.6"/>
    @foreach ([64, 82, 100, 118, 136] as $i => $x)
        <path d="M{{ $x }} {{ 66 + (2 - abs($i - 2)) * 2 }} l3 6 l-6 0 Z" fill="{{ ['#F59E0B', '#3B82F6', '#22C55E', '#F59E0B', '#3B82F6'][$i] }}" transform="rotate(180 {{ $x }} {{ 69 + (2 - abs($i - 2)) * 2 }})"/>
    @endforeach
</svg>
