@props([
    'title' => null,
])

{{-- Master column: scrollable queue of items (pair with selected-dark-card rows). --}}
<section {{ $attributes->class(['eo-soft-card flex flex-col overflow-hidden']) }}>
    @if ($title || isset($header))
        <div class="flex items-center justify-between border-b border-eo-line px-5 py-4">
            @if ($title)
                <h2 class="eo-title text-[17px]">{{ $title }}</h2>
            @endif
            {{ $header ?? '' }}
        </div>
    @endif

    <div class="flex flex-col gap-2 p-3 sm:p-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-eo-line px-5 py-3">
            {{ $footer }}
        </div>
    @endisset
</section>
