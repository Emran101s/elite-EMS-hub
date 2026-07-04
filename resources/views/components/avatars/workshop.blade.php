<svg {{ $attributes }} viewBox="0 0 200 140" fill="none" xmlns="http://www.w3.org/2000/svg" role="img" aria-label="Workshop — Learning Center">
    <defs>
        <linearGradient id="ws-sky" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#F8FAFC"/><stop offset="1" stop-color="#E9F4EC"/></linearGradient>
        <linearGradient id="ws-fin" x1="0" y1="0" x2="0" y2="1"><stop stop-color="#4ADE80"/><stop offset="1" stop-color="#22C55E"/></linearGradient>
    </defs>
    <rect width="200" height="140" fill="url(#ws-sky)"/>
    <ellipse cx="100" cy="122" rx="86" ry="14" fill="#FFFFFF"/>
    <ellipse cx="100" cy="122" rx="86" ry="14" stroke="#E2E8F0"/>
    {{-- main learning block --}}
    <rect x="44" y="62" width="84" height="50" rx="3" fill="#FFFFFF"/>
    <rect x="44" y="62" width="84" height="50" rx="3" stroke="#E2E8F0"/>
    <rect x="44" y="58" width="84" height="6" rx="2" fill="#0B1F3A"/>
    {{-- window grid --}}
    @foreach ([52, 72, 92] as $x)
        @foreach ([70, 84] as $y)
            <rect x="{{ $x }}" y="{{ $y }}" width="16" height="10" rx="1.5" fill="#0B1F3A" opacity="0.85"/>
            <line x1="{{ $x + 8 }}" y1="{{ $y }}" x2="{{ $x + 8 }}" y2="{{ $y + 10 }}" stroke="#FFFFFF" stroke-opacity="0.3"/>
        @endforeach
    @endforeach
    <rect x="82" y="98" width="12" height="14" rx="1.5" fill="#22C55E"/>
    {{-- green fin + annex --}}
    <rect x="116" y="52" width="8" height="60" rx="2" fill="url(#ws-fin)"/>
    <rect x="128" y="80" width="34" height="32" rx="3" fill="#FFFFFF"/>
    <rect x="128" y="80" width="34" height="32" rx="3" stroke="#E2E8F0"/>
    <rect x="128" y="76" width="34" height="6" rx="2" fill="#22C55E"/>
    <rect x="134" y="88" width="22" height="9" rx="1.5" fill="#0B1F3A" opacity="0.8"/>
    {{-- courtyard trees --}}
    @foreach ([34, 170] as $x)
        <line x1="{{ $x }}" y1="118" x2="{{ $x }}" y2="108" stroke="#16A34A" stroke-width="1.5"/>
        <circle cx="{{ $x }}" cy="104" r="6" fill="#22C55E" opacity="0.85"/>
    @endforeach
</svg>
