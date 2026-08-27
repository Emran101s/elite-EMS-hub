@props(['title' => 'Actions'])

<aside {{ $attributes->class(['flex flex-col overflow-hidden rounded-lg border border-line bg-white']) }}>
    <div class="border-b border-line px-4 py-3">
        <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">{{ $title }}</p>
    </div>

    <div class="flex flex-col gap-2 p-4">
        {{ $slot }}
    </div>
</aside>
