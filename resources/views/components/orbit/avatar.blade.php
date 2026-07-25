@props([
    'name' => null,      // full name; initials are derived
    'initials' => null,
    'tone' => null,      // tints the disc — use sparingly, it is not a status
    'size' => null,      // sm | lg
])
@php
    $ini = $initials ?? \Illuminate\Support\Str::of((string) $name)
        ->explode(' ')->filter()
        ->map(fn ($p) => mb_substr($p, 0, 1))
        ->take(2)->implode('');
    $t = $tone instanceof \App\Support\Tone ? $tone : ($tone ? \App\Support\Tone::tryFrom($tone) : null);
@endphp
<span {{ $attributes->merge(['class' => 'o-av'.($size ? ' o-av--'.$size : '')]) }}
      @if ($t) style="background:{{ $t->tint() }};color:{{ $t->var() }}" @endif
      @if ($name) title="{{ $name }}" @endif>{{ $ini }}</span>
