@props(['dueFollowUps'])

<div class="rounded-lg border border-line bg-white p-4">
    <p class="mb-2 text-eyebrow font-bold uppercase tracking-[0.14em] text-muted">Follow-ups due</p>
    <div class="space-y-1.5">
        @foreach ($dueFollowUps as $a)
            <button type="button" wire:click="select({{ $a->deal_id }})"
                    class="flex w-full items-center gap-2 rounded-md bg-page px-2.5 py-1.5 text-left transition hover:bg-gold-50">
                <span class="h-1.5 w-1.5 shrink-0 rounded-full bg-danger"></span>
                <span class="min-w-0 flex-1">
                    <span class="block truncate text-[11.5px] font-semibold text-ink">{{ $a->subject }}</span>
                    <span class="block truncate text-[10px] text-muted">{{ $a->deal?->title ?? $a->client?->name }}</span>
                </span>
                <span class="shrink-0 text-[10px] font-bold tabular-nums text-danger-ink">{{ $a->follow_up_on->format('j M') }}</span>
            </button>
        @endforeach
    </div>
</div>
