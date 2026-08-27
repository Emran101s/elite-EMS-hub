@props(['eyebrow' => null, 'title', 'subtitle' => null])

<div class="flex flex-wrap items-start justify-between gap-4">
    <div class="min-w-0">
        @if ($eyebrow)
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">{{ $eyebrow }}</p>
        @endif
        <h1 class="pf mt-1 text-display font-semibold leading-tight text-ink">{{ $title }}</h1>
        @if ($subtitle)
            <p class="mt-1.5 max-w-2xl text-[14px] text-muted">{{ $subtitle }}</p>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
