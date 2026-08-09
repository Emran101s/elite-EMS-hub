@props([
    'title' => null,
    'subtitle' => null,
])

{{-- Center column: selected entity detail. --}}
<section {{ $attributes->class(['eo-soft-card flex flex-col']) }}>
    @if ($title || isset($header) || isset($meta))
        <div class="border-b border-eo-line px-5 py-4 sm:px-6">
            <div class="flex items-start justify-between gap-3">
                <div class="min-w-0">
                    @if ($title)
                        <h2 class="eo-title">{{ $title }}</h2>
                    @endif
                    @if ($subtitle)
                        <p class="mt-1 text-[13px] text-eo-muted">{{ $subtitle }}</p>
                    @endif
                    {{ $meta ?? '' }}
                </div>
                {{ $header ?? '' }}
            </div>
        </div>
    @endif

    <div class="flex-1 px-5 py-5 sm:px-6">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="border-t border-eo-line px-5 py-4 sm:px-6">
            {{ $footer }}
        </div>
    @endisset
</section>
