@props(['segments', 'size' => 'h-36 w-36'])

{{-- $segments: array of ['pct' => float 0–100] plus either 'class' => 'stroke-*'
     or 'hex' => '#RRGGBB' for palettes that come from the database. --}}
@php $offset = 0; @endphp

<span {{ $attributes->merge(['class' => "relative inline-flex items-center justify-center {$size}"]) }}>
    <svg viewBox="0 0 36 36" class="h-full w-full -rotate-90">
        <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="4" class="stroke-navy-50" />
        @foreach ($segments as $segment)
            @if ($segment['pct'] > 0)
                <circle cx="18" cy="18" r="15.9155" fill="none" stroke-width="4"
                        stroke-dasharray="{{ $segment['pct'] }} {{ 100 - $segment['pct'] }}"
                        stroke-dashoffset="{{ -$offset }}"
                        @isset($segment['hex']) stroke="{{ $segment['hex'] }}" @endisset
                        class="{{ $segment['class'] ?? '' }}" />
                @php $offset += $segment['pct']; @endphp
            @endif
        @endforeach
    </svg>
    <span class="absolute flex flex-col items-center leading-tight">{{ $slot }}</span>
</span>
