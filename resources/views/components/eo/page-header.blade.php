@props([
    'eyebrow' => null,
    'title',
    'subtitle' => null,
])

{{-- Soft Command page header — title left, actions right. --}}
<header {{ $attributes->class(['mb-6 flex flex-col gap-4 sm:mb-8 sm:flex-row sm:items-end sm:justify-between']) }}>
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="eo-label mb-2">{{ $eyebrow }}</p>
        @endif
        <h1 class="eo-display">{{ $title }}</h1>
        @if ($subtitle)
            <p class="eo-body mt-2 max-w-2xl text-eo-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 sm:justify-end">
            {{ $actions }}
        </div>
    @endisset
</header>
