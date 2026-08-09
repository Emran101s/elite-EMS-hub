@props([
    'title' => 'Actions',
])

{{-- Right column: next actions for the selected entity. --}}
<aside {{ $attributes->class(['eo-soft-card flex flex-col']) }}>
    <div class="border-b border-eo-line px-5 py-4">
        <h2 class="eo-label">{{ $title }}</h2>
    </div>

    <div class="flex flex-col gap-2 p-4">
        {{ $slot }}
    </div>

    @isset($footer)
        <div class="mt-auto border-t border-eo-line px-5 py-4">
            {{ $footer }}
        </div>
    @endisset
</aside>
