@props(['event' => null, 'name' => null, 'type' => null])

@php
    /**
     * A generated brand crest. Deterministic: the same event always produces the
     * same mark, so it reads as identity rather than decoration. The field stays
     * in the platform's deep-navy family while the accent hue and the geometry
     * are derived from the event's name and type — one system, distinct marks.
     */
    $label = trim((string) ($name ?? $event?->name ?? 'Event')) ?: 'Event';
    $kind = (string) ($type ?? $event?->type ?? '');

    $seed = abs(crc32($label.'|'.$kind));
    $hue = $seed % 360;

    $accent = 'hsl('.$hue.' 76% 62%)';
    $accentSoft = 'hsl('.$hue.' 70% 55%)';
    $deep = 'hsl('.(($hue + 208) % 360).' 40% 13%)';
    $deeper = 'hsl('.(($hue + 224) % 360).' 46% 7%)';

    // Years and connectors ("2026", "of", "&") make poor initials — drop them.
    $words = \Illuminate\Support\Str::of($label)->explode(' ')
        ->map(fn ($p) => trim($p, " \t\n\r.,&·-"))
        ->reject(fn ($p) => $p === '' || is_numeric($p) || in_array(mb_strtolower($p), ['the', 'of', 'and', 'for', 'a'], true))
        ->values();

    $initials = $words->count() >= 2
        ? $words->take(3)->map(fn ($p) => mb_substr($p, 0, 1))->implode('')
        : mb_substr($words->first() ?? $label, 0, 3);
    $initials = mb_strtoupper($initials) ?: 'EV';

    $uid = 'crest-'.$seed;

    $glyph = match (true) {
        str_contains($kind, 'exhibition') => 'grid',
        str_contains($kind, 'gala'), str_contains($kind, 'awards') => 'diamond',
        str_contains($kind, 'workshop'), str_contains($kind, 'training'), str_contains($kind, 'seminar') => 'bars',
        str_contains($kind, 'outdoor'), str_contains($kind, 'festival') => 'peaks',
        str_contains($kind, 'hybrid') => 'orbits',
        default => 'rings',
    };

    // A little deterministic variation so two events of the same type still differ.
    $tilt = ($seed % 24) - 12;
@endphp

<svg {{ $attributes->merge(['class' => 'block']) }} viewBox="0 0 100 100" preserveAspectRatio="xMidYMid slice"
     role="img" aria-label="{{ $label }} crest">
    <defs>
        <linearGradient id="{{ $uid }}-bg" x1="0" y1="0" x2="1" y2="1">
            <stop offset="0" stop-color="{{ $deep }}"/>
            <stop offset="1" stop-color="{{ $deeper }}"/>
        </linearGradient>
        <radialGradient id="{{ $uid }}-glow" cx="0.82" cy="0.12" r="0.75">
            <stop offset="0" stop-color="{{ $accent }}" stop-opacity="0.42"/>
            <stop offset="1" stop-color="{{ $accent }}" stop-opacity="0"/>
        </radialGradient>
    </defs>

    <rect width="100" height="100" fill="url(#{{ $uid }}-bg)"/>
    <rect width="100" height="100" fill="url(#{{ $uid }}-glow)"/>

    <g transform="rotate({{ $tilt }} 50 50)" opacity="0.5" fill="none" stroke="{{ $accentSoft }}" stroke-width="2.2">
        @switch ($glyph)
            @case ('grid')
                @foreach ([28, 50, 72] as $gx)
                    @foreach ([28, 50, 72] as $gy)
                        <rect x="{{ $gx - 8 }}" y="{{ $gy - 8 }}" width="16" height="16" rx="3"/>
                    @endforeach
                @endforeach
                @break

            @case ('diamond')
                <path d="M50 14 L79 50 L50 86 L21 50 Z"/>
                <path d="M50 28 L67 50 L50 72 L33 50 Z"/>
                @break

            @case ('bars')
                @foreach ([[26, 46], [40, 30], [54, 38], [68, 22]] as [$bx, $bh])
                    <rect x="{{ $bx }}" y="{{ 74 - $bh }}" width="10" height="{{ $bh }}" rx="3"/>
                @endforeach
                @break

            @case ('peaks')
                <path d="M12 74 L34 40 L50 62 L68 30 L90 74"/>
                <path d="M12 84 L90 84"/>
                @break

            @case ('orbits')
                <circle cx="40" cy="50" r="24"/>
                <circle cx="60" cy="50" r="24"/>
                @break

            @default
                <circle cx="50" cy="50" r="34"/>
                <circle cx="50" cy="50" r="24"/>
                <circle cx="50" cy="50" r="14"/>
        @endswitch
    </g>

    <text x="50" y="50" text-anchor="middle" dominant-baseline="central"
          font-family="Spectral, Georgia, serif" font-weight="800"
          font-size="{{ mb_strlen($initials) >= 3 ? 27 : 34 }}" letter-spacing="1.5"
          fill="var(--chrome-ink)" fill-opacity="0.94">{{ $initials }}</text>
</svg>
