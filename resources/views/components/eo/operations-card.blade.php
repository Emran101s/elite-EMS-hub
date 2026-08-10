@props([
    'title',
    'subtitle' => null,
    'open' => null,
    'due' => null,
    'blocked' => null,
])

<div {{ $attributes->class(['eo-domain-card eo-operations-card']) }}>
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <p class="eo-label">Operations</p>
            <h3 class="mt-1 text-[16px] font-semibold text-eo-text">{{ $title }}</h3>
            @if ($subtitle)
                <p class="mt-1 text-[12px] text-eo-muted">{{ $subtitle }}</p>
            @endif
        </div>
        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-eo-teal-soft text-eo-teal-ink">
            <x-icon name="clipboard" class="h-5 w-5" />
        </span>
    </div>

    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-2xl bg-eo-workspace px-3 py-2.5">
            <p class="eo-label">Open</p>
            <p class="mt-1 text-[18px] font-bold tabular-nums text-eo-text">{{ $open ?? '—' }}</p>
        </div>
        <div class="rounded-2xl bg-eo-workspace px-3 py-2.5">
            <p class="eo-label">Due</p>
            <p class="mt-1 text-[18px] font-bold tabular-nums text-eo-warn">{{ $due ?? '—' }}</p>
        </div>
        <div class="rounded-2xl bg-eo-workspace px-3 py-2.5">
            <p class="eo-label">Blocked</p>
            <p class="mt-1 text-[18px] font-bold tabular-nums text-eo-risk">{{ $blocked ?? '—' }}</p>
        </div>
    </div>

    {{ $slot }}
</div>
