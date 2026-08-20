<div>
    <x-eo.operations-header active="requirements" title="Equipment Catalog"
                 subtitle="Your reusable list of equipment & prices — pick these when adding equipment to a venue or event.">
        <x-slot:actions>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search equipment…" class="eo-input h-9 w-48 text-xs">
            <button type="button" wire:click="$toggle('showImport')" class="eo-btn-ghost eo-btn-sm">⇪ Import</button>
            <a href="{{ route('requirements.pdf') }}" class="eo-btn-ghost eo-btn-sm"><x-icon name="chart" class="h-3.5 w-3.5" /> PDF</a>
            <x-eo.button size="sm" wire:click="newItem">＋ Add equipment</x-eo.button>
        </x-slot:actions>
    </x-eo.operations-header>

    @if ($showImport)
        <form wire:submit="import" class="eo-soft-card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="flex-1">
                <label class="eo-label mb-1" for="eq-import">Excel (.xlsx) or CSV — columns: <span class="font-semibold text-eo-text">name</span>, price, notes</label>
                <input id="eq-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv" class="eo-input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-eo-navy file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                <p class="mt-1 text-[11px] text-eo-muted">First row is treated as headers. No headers? We'll use column order: name, price, notes.</p>
                @error('importFile') <p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
            <x-eo.button type="submit" size="sm" wire:loading.attr="disabled" wire:target="import,importFile">Import</x-eo.button>
        </form>
    @endif

    <div class="eo-table-wrap">
        @if ($items->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-semibold text-eo-text">Your catalog is empty</p>
                <p class="mx-auto mt-1 max-w-md text-xs text-eo-muted">Add the equipment you use across events — each with a price. You'll then choose from this list when adding equipment to a venue.</p>
                <x-eo.button size="sm" wire:click="newItem" class="mt-4">＋ Add your first item</x-eo.button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="eo-table w-full min-w-[520px]">
                    <thead>
                        <tr>
                            <th class="px-5 py-2.5">Equipment</th>
                            <th class="px-3 py-2.5 text-right">Unit price</th>
                            <th class="px-3 py-2.5">Notes</th>
                            <th class="px-3 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($items as $r)
                            <tr wire:key="req-{{ $r->id }}" class="group">
                                <td class="px-5 py-3 text-sm font-semibold text-eo-text">{{ $r->name }}</td>
                                <td class="px-3 py-3 text-right text-sm font-bold text-eo-text">{{ $r->unit_price_cents ? number_format($r->unit_price_cents / 100, 2) : '—' }}</td>
                                <td class="px-3 py-3 text-xs text-eo-muted">{{ $r->notes }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button type="button" wire:click="edit({{ $r->id }})" class="rounded-lg bg-eo-bg px-1.5 py-1 text-[10px] font-bold text-eo-muted hover:bg-eo-line">✎</button>
                                        <x-confirm title="Delete “{{ $r->name }}” from the catalog?" confirm="Delete" run="$wire.delete({{ $r->id }})" class="rounded-lg bg-eo-risk/10 px-1.5 py-1 text-[10px] font-bold text-eo-risk hover:bg-eo-risk/20">✕</x-confirm>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <p class="mt-3 text-center text-[11px] text-eo-muted">{{ $items->count() }} {{ str('item')->plural($items->count()) }} in the catalog</p>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit equipment' : 'New equipment'" max="md" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5">
                    <div>
                        <label class="eo-label mb-1">Equipment name</label>
                        <input type="text" wire:model="name" class="eo-input h-10 text-sm" placeholder="e.g. AV & sound system">
                        @error('name')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label mb-1">Unit price</label>
                        <input type="number" step="0.01" min="0" wire:model="price" class="eo-input h-10 text-sm" placeholder="0">
                        @error('price')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="eo-label mb-1">Notes (optional)</label>
                        <input type="text" wire:model="notes" class="eo-input h-10 text-sm" placeholder="Spec, supplier, etc.">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                        <x-eo.button type="submit" size="sm">{{ $editingId ? 'Update' : 'Add' }}</x-eo.button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
