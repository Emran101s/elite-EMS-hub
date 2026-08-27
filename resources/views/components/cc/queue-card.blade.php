@props(['icon', 'label', 'tone' => 'warn', 'count' => 0, 'empty' => 'Nothing here'])

<div class="rounded-lg border border-line bg-white p-3.5">
    <div class="mb-2 flex items-center gap-2">
        <x-icon :name="$icon" class="h-3.5 w-3.5 text-muted" />
        <p class="text-[12px] font-bold text-ink">{{ $label }}</p>
        @if ($count > 0)
            <span @class([
                'ms-auto rounded-full px-1.5 py-0.5 text-[10px] font-bold tabular-nums',
                'bg-danger-soft text-danger-ink' => $tone === 'risk',
                'bg-warning-soft text-warning-ink' => $tone !== 'risk',
            ])>{{ $count }}</span>
        @endif
    </div>

    @if ($count === 0)
        <p class="text-[11.5px] text-muted">{{ $empty }}</p>
    @else
        <div class="space-y-1.5">{{ $slot }}</div>
    @endif
</div>
