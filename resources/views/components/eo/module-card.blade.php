@props([
    'title',
    'description' => null,
    'href' => null,
    'icon' => null,
    'meta' => null,
])

@php
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }}
    @if ($href) href="{{ $href }}" @endif
    {{ $attributes->class([
        'eo-soft-card group block p-5 transition hover:-translate-y-0.5 hover:shadow-eo-float',
        'focus:outline-none focus-visible:ring-2 focus-visible:ring-eo-teal/40' => (bool) $href,
    ]) }}
>
    <div class="mb-4 flex items-start justify-between gap-3">
        @if ($icon)
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-eo-teal-soft text-eo-teal-ink">
                <x-icon :name="$icon" class="h-5 w-5" />
            </span>
        @else
            <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-eo-navy text-[13px] font-bold text-eo-gold-soft">
                EO
            </span>
        @endif
        @isset($badge)
            <div>{{ $badge }}</div>
        @endisset
    </div>

    <h3 class="text-[16px] font-semibold text-eo-text group-hover:text-eo-navy">{{ $title }}</h3>
    @if ($description)
        <p class="mt-1.5 text-[13px] leading-relaxed text-eo-muted">{{ $description }}</p>
    @endif
    @if ($meta)
        <p class="eo-label mt-4">{{ $meta }}</p>
    @endif

    {{ $slot }}
</{{ $tag }}>
