@props(['count' => 0, 'noun' => 'item'])

@if ($count > 0)
    <div class="bulkbar-in fixed bottom-6 left-1/2 z-50 -translate-x-1/2">
        <div class="flex items-center gap-1 rounded-full border border-line bg-white/95 py-1.5 pl-2 pr-1.5 shadow-[0_16px_44px_rgba(11,31,58,0.22)] backdrop-blur">
            <span class="flex items-center gap-2 pl-1.5 pr-1 text-xs font-semibold text-navy-700">
                <span class="flex h-5 min-w-[1.25rem] items-center justify-center rounded-full bg-navy-900 px-1.5 text-[0.62rem] font-bold text-white">{{ $count }}</span>
                {{ \Illuminate\Support\Str::plural($noun, $count) }} selected
            </span>
            <span class="mx-0.5 h-5 w-px bg-line"></span>
            <button type="button" wire:click="deleteSelected" wire:confirm="Delete the {{ $count }} selected {{ \Illuminate\Support\Str::plural($noun, $count) }}?"
                    class="flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-semibold text-red-600 transition hover:bg-red-50">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"><path d="M4 6h12M8 6V4.5A1.5 1.5 0 0 1 9.5 3h1A1.5 1.5 0 0 1 12 4.5V6m2 0v9.5A1.5 1.5 0 0 1 12.5 17h-5A1.5 1.5 0 0 1 6 15.5V6m2.5 3v5M11.5 9v5"/></svg>
                Delete
            </button>
            <button type="button" wire:click="clearSelection" title="Clear selection"
                    class="flex h-8 w-8 items-center justify-center rounded-full text-navy-400 transition hover:bg-navy-50 hover:text-navy-700">
                <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"><path d="M5 5l10 10M15 5L5 15"/></svg>
            </button>
        </div>
    </div>
@endif
