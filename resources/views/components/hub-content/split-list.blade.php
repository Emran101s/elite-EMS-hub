@props(['columns' => null])

<div class="overflow-hidden rounded-lg border border-line bg-white">
    @isset($columns)
        <div class="hidden grid-cols-12 gap-3 border-b border-line bg-page px-4 py-2 text-eyebrow font-semibold uppercase tracking-wide text-muted md:grid">
            {{ $columns }}
        </div>
    @endisset

    <div class="divide-y divide-line">
        {{ $slot }}
    </div>
</div>
