@props([
    'title',
    'subtitle' => null,
    'eyebrow' => null,
])

{{--
    One page/section title pattern. Replaces eight different h1/h2 class
    combinations across the platform. `actions` slot holds the buttons, so the
    title/action relationship is identical everywhere.
--}}
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-end justify-between gap-3']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="eyebrow">{{ $eyebrow }}</p>
        @endif
        <h2 class="pf text-h1 font-bold leading-tight text-navy-900">{{ $title }}</h2>
        @if ($subtitle)
            <p class="mt-0.5 text-micro text-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex shrink-0 flex-wrap items-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
