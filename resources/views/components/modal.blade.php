@props([
    'title' => null,
    'subtitle' => null,
    'close' => null,      // Livewire expression, e.g. "$set('showForm', false)"
    'max' => 'xl',        // sm | md | lg | xl | 2xl | 3xl
    'align' => 'top',     // top | center
    'flush' => false,     // true: body manages its own padding (sectioned forms)
])

@php
    // Twelve hand-rolled shells existed with four different backdrops
    // (navy-900/40 pt-16, navy-950/40 py-10, pt-24, pt-12). This is the one.
    $widths = [
        'sm' => 'max-w-sm', 'md' => 'max-w-md', 'lg' => 'max-w-lg',
        'xl' => 'max-w-xl', '2xl' => 'max-w-2xl', '3xl' => 'max-w-3xl',
    ];
@endphp

<div dusk="modal" class="fixed inset-0 z-50 flex overflow-y-auto bg-navy-950/50 p-4 backdrop-blur-sm {{ $align === 'center' ? 'items-center justify-center' : 'items-start justify-center pt-16' }}">
    <div class="overflow-hidden rounded-lg border border-line bg-white shadow-raise w-full {{ $widths[$max] ?? $widths['xl'] }}"
         @if ($close) @click.outside="$wire.{{ $close }}" @endif>

        @if ($title)
            {{-- The header used to be a navy gradient. A modal already reads as
                 the front-most thing on screen — the backdrop does that work —
                 so it does not need a dark bar to announce itself as well. --}}
            <div class="flex items-start justify-between gap-4 rounded-t-lg border-b border-line bg-page/60 px-6 py-4">
                <div class="min-w-0">
                    <h3 class="text-h2 font-bold text-ink">{{ $title }}</h3>
                    @if ($subtitle)
                        <p class="mt-0.5 text-micro text-muted">{{ $subtitle }}</p>
                    @endif
                </div>
                @if ($close)
                    <button type="button" wire:click="{{ $close }}"
                            class="-me-1 shrink-0 rounded-lg p-1 text-muted transition hover:bg-page hover:text-ink"
                            aria-label="Close">✕</button>
                @endif
            </div>
        @endif

        @if ($flush)
            {{ $slot }}
        @else
            <div class="p-6">
                {{ $slot }}
            </div>
        @endif

        @isset($footer)
            <div class="flex items-center justify-end gap-2 border-t border-line px-6 py-4">
                {{ $footer }}
            </div>
        @endisset
    </div>
</div>
