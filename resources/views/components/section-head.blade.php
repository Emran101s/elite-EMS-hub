@props([
    'number' => null,
    'title',
    'subtitle' => null,
])

{{-- Shared section header: gold serif numeral · Spectral title · hairline rule.
     Use everywhere so sections read the same across the platform. --}}
<div {{ $attributes->merge(['class' => 'mb-5 flex items-end justify-between gap-3 border-b border-line pb-2.5']) }}>
    <div class="flex min-w-0 items-baseline gap-2.5">
        @if ($number)
            <span class="pf shrink-0 text-xl font-bold leading-none text-gold-400/50">{{ $number }}</span>
        @endif
        <div class="min-w-0">
            <h3 class="pf truncate text-base font-bold text-navy-900">{{ $title }}</h3>
            @if ($subtitle)
                <p class="truncate text-[0.68rem] text-muted">{{ $subtitle }}</p>
            @endif
        </div>
    </div>

    @if (! $slot->isEmpty())
        <div class="flex shrink-0 items-center gap-2">{{ $slot }}</div>
    @endif
</div>
