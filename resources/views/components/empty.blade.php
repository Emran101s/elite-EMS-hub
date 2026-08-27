@props([
    'title',
    'hint' => null,
    'icon' => null,
])

{{--
    One empty state. Replaces 39 ad-hoc centred blocks. The `actions` slot keeps
    the "nothing here yet — here's how to start" pairing consistent, which is the
    part that was most often missing.

    V7: shares its markup with <x-eo.empty-state> exactly, so the two names
    render identically — every existing <x-empty> call site updates for free
    without touching the 23 files that use it.
--}}
<div {{ $attributes->class(['rounded-lg border border-line bg-white shadow-raise flex flex-col items-center px-6 py-14 text-center']) }}>
    @if ($icon)
        <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-gold-50 text-gold-700">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @endif

    <p class="text-[15px] font-semibold text-ink">{{ $title }}</p>

    @if ($hint)
        <p class="mx-auto mt-1.5 max-w-md text-[13px] leading-relaxed text-muted">{{ $hint }}</p>
    @endif

    @isset($actions)
        <div class="mt-5 flex flex-wrap items-center justify-center gap-2">
            {{ $actions }}
        </div>
    @endisset
</div>
