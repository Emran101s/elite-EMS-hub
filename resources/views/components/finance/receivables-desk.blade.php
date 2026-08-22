@props(['open', 'due'])

<div class="rounded-lg border border-line bg-white p-4">
    <div class="mb-3 flex items-start justify-between gap-3">
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Operations</p>
            <h3 class="mt-1 text-[16px] font-semibold text-ink">Receivables desk</h3>
            <p class="mt-1 text-[12px] text-muted">Contract instalments due</p>
        </div>
        <span class="flex h-10 w-10 items-center justify-center rounded-lg bg-gold-50 text-gold-700">
            <x-icon name="clipboard" class="h-5 w-5" />
        </span>
    </div>

    <div class="grid grid-cols-3 gap-2">
        <div class="rounded-md bg-page px-3 py-2.5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">Open</p>
            <p class="mt-1 text-[18px] font-bold tabular-nums text-ink">{{ $open }}</p>
        </div>
        <div class="rounded-md bg-page px-3 py-2.5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">Due</p>
            <p class="mt-1 text-[18px] font-bold tabular-nums text-warning-ink">{{ $due }}</p>
        </div>
        <div class="rounded-md bg-page px-3 py-2.5">
            <p class="text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">Blocked</p>
            <p class="mt-1 text-[18px] font-bold tabular-nums text-danger-ink">0</p>
        </div>
    </div>
</div>
