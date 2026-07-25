@props(['tone' => null, 'icon' => null])
@php $t = $tone ? (\App\Support\Tone::tryFrom($tone) ?? null) : null; @endphp
<div {{ $attributes->merge(['class' => 'o-toast']) }} role="status">
    @if ($icon)<span style="color:{{ $t?->var() ?? 'var(--ink-3)' }}"><x-orbit.icon :name="$icon" :size="16" /></span>@endif
    {{ $slot }}
</div>
