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
    <div class="card w-full !rounded-3xl {{ $widths[$max] ?? $widths['xl'] }} shadow-overlay"
         @if ($close) @click.outside="$wire.{{ $close }}" @endif>

        @if ($title)
            {{-- dark navy accent header — the reference modal language --}}
            <div class="relative flex items-start justify-between gap-4 overflow-hidden rounded-t-3xl bg-gradient-to-br from-navy-900 to-navy-950 px-6 py-4 text-white">
                <div class="pointer-events-none absolute -right-6 -top-10 h-28 w-28 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.24),transparent_70%)]"></div>
                <div class="relative min-w-0">
                    <h3 class="pf text-h2 font-bold text-white">{{ $title }}</h3>
                    @if ($subtitle)
                        <p class="mt-0.5 text-micro text-white/55">{{ $subtitle }}</p>
                    @endif
                </div>
                @if ($close)
                    <button type="button" wire:click="{{ $close }}"
                            class="relative -me-1 shrink-0 rounded-lg p-1 text-white/50 transition hover:bg-white/10 hover:text-white"
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
