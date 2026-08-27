@props(['venue'])

<div wire:key="venue-{{ $venue->id }}" class="group flex flex-col overflow-hidden rounded-lg border border-line bg-white transition hover:-translate-y-0.5 hover:shadow-float">
    <div class="flex flex-1 flex-col p-4">
        <div class="flex items-start gap-3">
            <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg bg-page text-muted">
                <x-icon name="building" class="h-5 w-5" />
            </span>
            <div class="min-w-0 flex-1">
                <p class="truncate text-[13.5px] font-bold text-ink">{{ $venue->name }}</p>
                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-[11px] text-muted">
                    @if ($venue->type)<span class="rounded-full bg-page px-1.5 py-0.5 text-muted">{{ $venue->type }}</span>@endif
                    <span>{{ $venue->locationLine() ?: 'No location set' }}</span>
                </p>
            </div>
            <div class="flex shrink-0 items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                <button type="button" wire:click="edit({{ $venue->id }})" class="rounded-md bg-page px-1.5 py-1 text-[10px] font-bold text-muted hover:bg-line">✎</button>
                <x-confirm title="Delete “{{ $venue->name }}”?"
                           body="Events using it keep working — they just lose the venue link."
                           confirm="Delete" run="$wire.delete({{ $venue->id }})"
                           class="rounded-md bg-danger-soft px-1.5 py-1 text-[10px] font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
            </div>
        </div>

        @if ($venue->address)
            <p class="mt-2.5 flex items-start gap-1.5 text-[11px] text-muted"><x-icon name="pin" class="mt-0.5 h-3 w-3 shrink-0 text-muted" />{{ $venue->address }}</p>
        @endif

        @if ($venue->contact_name || $venue->contact_phone || $venue->contact_email)
            <div class="mt-2.5 rounded-md bg-page px-2.5 py-1.5 text-[11px] text-muted">
                @if ($venue->contact_name)<span class="font-semibold text-ink">{{ $venue->contact_name }}</span>@endif
                @if ($venue->contact_phone) · {{ $venue->contact_phone }}@endif
                @if ($venue->contact_email) · {{ $venue->contact_email }}@endif
            </div>
        @endif
    </div>

    <div class="mt-auto flex items-center gap-2 border-t border-line bg-page px-3.5 py-2">
        <x-icon name="building" class="h-3 w-3 shrink-0 text-gold-600" />
        <span class="text-[11px] font-semibold text-muted">{{ $venue->capacity ? number_format($venue->capacity) : '—' }} capacity</span>
        <span class="shrink-0 text-[10px] font-bold uppercase tracking-wide text-muted">{{ $venue->events_count }} {{ str('event')->plural($venue->events_count) }}</span>
        <a href="{{ route('venues.show', $venue) }}" wire:navigate class="ml-auto shrink-0 text-[10.5px] font-bold text-gold-600 hover:underline">Open Studio →</a>
    </div>
</div>
