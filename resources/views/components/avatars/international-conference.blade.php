<svg {{ $attributes }} viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="International Conference — Modern Convention Center">
    <defs>
        <linearGradient id="ic-sky" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#F8FAFC"/><stop offset="1" stop-color="#F1F5FA"/></linearGradient>
        <linearGradient id="ic-glass" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#2A4368"/><stop offset="1" stop-color="#0B1F3A"/></linearGradient>
        <linearGradient id="ic-tower" x1="0" y1="0" x2="1" y2="1"><stop stop-color="#3A5885"/><stop offset="1" stop-color="#0B1F3A"/></linearGradient>
        <linearGradient id="ic-pod" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#EFD98E"/><stop offset="0.55" stop-color="#D4AF37"/><stop offset="1" stop-color="#8F6F1F"/></linearGradient>
    </defs>
    <rect width="200" height="140" fill="url(#ic-sky)"/>
    {{-- gold podium --}}
    <ellipse cx="100" cy="130" rx="80" ry="7" fill="#0B1F3A" opacity="0.10"/>
    <path d="M18 112 L18 122 A82 13 0 0 0 182 122 L182 112 A82 13 0 0 1 18 112 Z" fill="url(#ic-pod)"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" fill="#F7F3E6"/>
    <ellipse cx="100" cy="112" rx="82" ry="13" stroke="#D4AF37" stroke-opacity="0.55" stroke-width="0.8"/>
    <ellipse cx="100" cy="112" rx="68" ry="9.5" fill="#FFFFFF" opacity="0.65"/>
    {{-- glass tower --}}
    <rect x="132" y="38" width="30" height="76" rx="3" fill="url(#ic-tower)"/>
    <rect x="136" y="44" width="22" height="64" rx="2" fill="#FFFFFF" opacity="0.12"/>
    <line x1="147" y1="44" x2="147" y2="108" stroke="#FFFFFF" stroke-opacity="0.25"/>
    <rect x="132" y="34" width="30" height="4" rx="2" fill="#D4AF37"/>
    {{-- main hall --}}
    <path d="M34 76 Q78 58 126 76 L126 112 L34 112 Z" fill="#FFFFFF"/>
    <path d="M34 76 Q78 58 126 76" stroke="#D4AF37" stroke-width="3" stroke-linecap="round"/>
    <rect x="42" y="84" width="76" height="28" rx="2" fill="url(#ic-glass)"/>
    @foreach ([54, 66, 78, 90, 102] as $x)
        <line x1="{{ $x }}" y1="84" x2="{{ $x }}" y2="112" stroke="#FFFFFF" stroke-opacity="0.3"/>
    @endforeach
    <rect x="74" y="96" width="14" height="16" rx="1.5" fill="#D4AF37"/>
    {{-- flags --}}
    @foreach ([22, 28, 34] as $i => $x)
        <line x1="{{ $x }}" y1="{{ 112 }}" x2="{{ $x }}" y2="{{ 78 + $i * 4 }}" stroke="#B8942C" stroke-width="1.5"/>
        <rect x="{{ $x }}" y="{{ 78 + $i * 4 }}" width="9" height="5" rx="1" fill="{{ ['#0B1F3A', '#D4AF37', '#3A5885'][$i] }}"/>
    @endforeach
</svg>
