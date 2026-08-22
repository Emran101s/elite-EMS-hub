@props(['supplier', 'selected' => false])

<div wire:key="supplier-{{ $supplier->id }}" class="group flex flex-col overflow-hidden rounded-lg border border-line bg-white transition hover:-translate-y-0.5 hover:shadow-float">
    <div class="flex flex-1 flex-col p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" wire:click="toggleSelect({{ $supplier->id }})"
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-[10px] {{ $selected ? 'border-navy-700 bg-navy-700 text-white' : 'border-line text-transparent hover:border-muted' }}"
                        title="Select">✓</button>
                <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-lg text-sm font-bold text-gold-400" style="background: linear-gradient(135deg, var(--color-navy-800), var(--color-navy-950));">{{ str($supplier->name)->substr(0, 1) }}</span>
                <div class="min-w-0">
                    <p class="truncate text-[13.5px] font-bold text-ink">{{ $supplier->name }}</p>
                    <p class="mt-0.5 truncate text-[11.5px] text-muted">
                        {{ $supplier->category ? str($supplier->category)->replace('_', ' & ')->title() : 'Uncategorised' }}
                        @if ($supplier->city) · {{ $supplier->city }}@if ($supplier->country), {{ $supplier->country }}@endif @endif
                    </p>
                </div>
            </div>
            <div class="flex shrink-0 items-center gap-1">
                <span class="inline-flex items-center gap-1 rounded-full bg-gold-50 px-2 py-0.5 text-[10.5px] font-bold text-gold-700">★ {{ number_format($supplier->rating, 1) }}</span>
                <span class="flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                    <button type="button" wire:click="edit({{ $supplier->id }})" title="Edit" class="rounded-md bg-page px-1.5 py-1 text-[10px] font-bold text-muted hover:bg-line">✎</button>
                    <x-confirm title="Delete “{{ $supplier->name }}”?"
                               body="Events using it keep working — they just lose the vendor link."
                               confirm="Delete" run="$wire.delete({{ $supplier->id }})"
                               class="rounded-md bg-danger-soft px-1.5 py-1 text-[10px] font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                </span>
            </div>
        </div>

        @if ($supplier->email || $supplier->phone)
            <div class="mt-2.5 rounded-md bg-page px-2.5 py-1.5 text-[11px] text-muted">
                @if ($supplier->email)<span class="font-semibold text-ink">{{ $supplier->email }}</span>@endif
                @if ($supplier->phone) · {{ $supplier->phone }}@endif
            </div>
        @endif
    </div>
    <div class="mt-auto flex items-center gap-2 border-t border-line bg-page px-3.5 py-2">
        <x-icon name="truck" class="h-3 w-3 shrink-0 text-gold-600" />
        <span class="truncate text-[11px] font-semibold text-muted">Working on {{ $supplier->events_count }} {{ str('event')->plural($supplier->events_count) }}</span>
    </div>
</div>
