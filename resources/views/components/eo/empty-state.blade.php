@props([
    'title',
    'hint' => null,
    'icon' => null,
])

{{-- Soft Command empty state — soft card, calm CTA pairing. --}}
<div {{ $attributes->class(['rounded-lg border border-line bg-white shadow-raise flex flex-col items-center px-6 py-14 text-center']) }}>
    @if ($icon)
        <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-gold-50 text-gold-700">
            <x-icon :name="$icon" class="h-5 w-5" />
        </span>
    @else
        <span class="mb-4 flex h-12 w-12 items-center justify-center rounded-2xl bg-page text-muted">
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                <rect x="4" y="4" width="16" height="16" rx="4" />
                <path d="M9 12h6M12 9v6" stroke-linecap="round" />
            </svg>
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
