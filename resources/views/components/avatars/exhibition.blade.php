<svg {{ $attributes }} viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Exhibition — Exhibition Park">
    <defs>
        <linearGradient id="ex-sky" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#F8FAFC"/><stop offset="1" stop-color="#F1F6FC"/></linearGradient>
        <linearGradient id="ex-roof" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#60A5FA"/><stop offset="1" stop-color="#3B82F6"/></linearGradient>
        <linearGradient id="ex-pod" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#EFD98E"/><stop offset="0.55" stop-color="#D4AF37"/><stop offset="1" stop-color="#8F6F1F"/></linearGradient>
    </defs>
    <rect width="200" height="140" fill="url(#ex-sky)"/>
    {{-- gold podium --}}
    <ellipse cx="100" cy="130" rx="80" ry="7" fill="#0B1F3A" opacity="0.10"/>
    <path d="M18 112 L18 122 A82 13 0 0 0 182 122 L182 112 A82 13 0 0 1 18 112 Z" fill="url(#ex-pod)"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" fill="#F7F3E6"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" stroke="#D4AF37" stroke-opacity="0.55" stroke-width="0.8"/>
    <ellipse cx="100" cy="112" rx="68" ry="9.5" fill="#FFFFFF" opacity="0.65"/>
    {{-- three sawtooth halls --}}
    @foreach ([['x' => 30, 'w' => 52], ['x' => 88, 'w' => 52], ['x' => 146, 'w' => 30]] as $hall)
        <rect x="{{ $hall['x'] }}" y="78" width="{{ $hall['w'] }}" height="34" rx="2" fill="#FFFFFF"/>
        <rect x="{{ $hall['x'] }}" y="78" width="{{ $hall['w'] }}" height="34" rx="2" stroke="#CBD5E1"/>
        <path d="M{{ $hall['x'] }} 78 L{{ $hall['x'] + $hall['w'] * 0.33 }} 64 L{{ $hall['x'] + $hall['w'] * 0.33 }} 78 L{{ $hall['x'] + $hall['w'] * 0.66 }} 64 L{{ $hall['x'] + $hall['w'] * 0.66 }} 78 L{{ $hall['x'] + $hall['w'] }} 64 L{{ $hall['x'] + $hall['w'] }} 78 Z" fill="url(#ex-roof)"/>
        <rect x="{{ $hall['x'] + 6 }}" y="88" width="{{ $hall['w'] - 12 }}" height="12" rx="1.5" fill="#93C5FD" opacity="0.5"/>
    @endforeach
    {{-- visitor pathway + booths --}}
    <path d="M96 112 L104 112 L110 128 L90 128 Z" fill="#94A3B8" opacity="0.5"/>
    @foreach ([58, 74, 118, 134] as $i => $x)
        <rect x="{{ $x }}" y="114" width="8" height="6" rx="1" fill="{{ $i % 2 ? '#94A3B8' : '#3B82F6' }}" opacity="0.8"/>
    @endforeach
    {{-- banner mast --}}
    <line x1="100" y1="64" x2="100" y2="46" stroke="#94A3B8" stroke-width="1.5"/>
    <rect x="100" y="46" width="14" height="7" rx="1" fill="#3B82F6"/>
</svg>
