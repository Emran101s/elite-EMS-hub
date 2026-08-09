@props([
    'searchPlaceholder' => 'Search…',
    'showSearch' => true,
])

{{-- Soft filter strip for lists and smart tables. --}}
<div {{ $attributes->class(['eo-soft-card flex flex-col gap-3 p-3 sm:flex-row sm:items-center sm:p-4']) }}>
    @if ($showSearch)
        <div class="relative min-w-0 flex-1">
            <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-eo-muted">
                <svg class="h-4 w-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
                    <circle cx="11" cy="11" r="7" />
                    <path d="m20 20-3.5-3.5" stroke-linecap="round" />
                </svg>
            </span>
            <input type="search" class="eo-input pl-10" placeholder="{{ $searchPlaceholder }}">
        </div>
    @endif

    @isset($filters)
        <div class="flex flex-wrap items-center gap-2">
            {{ $filters }}
        </div>
    @endisset

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2 sm:ml-auto">
            {{ $actions }}
        </div>
    @endisset
</div>
