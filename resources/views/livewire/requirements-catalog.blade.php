<div>
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h1 class="text-lg font-bold text-navy-900">Equipment Catalog</h1>
            <p class="text-xs text-muted">Your reusable list of equipment &amp; prices — pick these when adding equipment to a venue or event.</p>
        </div>
        <div class="flex items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search equipment…" class="input h-10 w-48 text-sm">
            <button type="button" wire:click="$toggle('showImport')" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-xs font-semibold text-navy-700 transition hover:border-gold-300">⇪ Import</button>
            <a href="{{ route('requirements.pdf') }}" class="flex h-10 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-xs font-semibold text-navy-700 transition hover:border-gold-300"><x-icon name="chart" class="h-3.5 w-3.5" /> PDF</a>
            <button type="button" wire:click="newItem" class="btn-gold h-10 px-4 text-xs">＋ Add equipment</button>
        </div>
    </div>

    @if ($showImport)
        <form wire:submit="import" class="card mb-4 flex flex-wrap items-end gap-3 p-4">
            <div class="flex-1">
                <label class="field-label !mb-1 !text-eyebrow" for="eq-import">Excel (.xlsx) or CSV — columns: <span class="font-semibold text-navy-700">name</span>, price, notes</label>
                <input id="eq-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv" class="input h-10 text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-navy-900 file:px-3 file:py-1 file:text-xs file:font-semibold file:text-white">
                <p class="mt-1 text-eyebrow text-muted">First row is treated as headers. No headers? We'll use column order: name, price, notes.</p>
                @error('importFile') <p class="mt-1 text-xs text-risk">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
            <button type="submit" class="btn-navy h-10 px-5 text-xs" wire:loading.attr="disabled" wire:target="import,importFile">Import</button>
        </form>
    @endif

    @if (session('status'))
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50/60 px-4 py-2 text-xs font-semibold text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="card overflow-x-auto">
        @if ($items->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-sm font-semibold text-navy-900">Your catalog is empty</p>
                <p class="mx-auto mt-1 max-w-md text-xs text-muted">Add the equipment you use across events — each with a price. You'll then choose from this list when adding equipment to a venue.</p>
                <button type="button" wire:click="newItem" class="btn-gold mt-4 h-10 px-5 text-xs">＋ Add your first item</button>
            </div>
        @else
            <table class="w-full min-w-[520px]">
                <thead>
                    <tr class="border-b border-line bg-page/40 text-left text-eyebrow font-bold uppercase tracking-wide text-muted">
                        <th class="px-5 py-2.5">Equipment</th>
                        <th class="px-3 py-2.5 text-right">Unit price</th>
                        <th class="px-3 py-2.5">Notes</th>
                        <th class="px-3 py-2.5 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $r)
                        <tr wire:key="req-{{ $r->id }}" class="group border-b border-line last:border-0 hover:bg-page/30">
                            <td class="px-5 py-3 text-sm font-semibold text-navy-900">{{ $r->name }}</td>
                            <td class="px-3 py-3 text-right text-sm font-bold text-navy-900">{{ $r->unit_price_cents ? number_format($r->unit_price_cents / 100, 2) : '—' }}</td>
                            <td class="px-3 py-3 text-xs text-muted">{{ $r->notes }}</td>
                            <td class="px-3 py-3">
                                <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                    <button type="button" wire:click="edit({{ $r->id }})" class="rounded-lg bg-navy-50 px-1.5 py-1 text-eyebrow font-bold text-navy-600 hover:bg-navy-100">✎</button>
                                    <button type="button" wire:click="delete({{ $r->id }})" wire:confirm="Delete “{{ $r->name }}” from the catalog?" class="rounded-lg bg-risk/10 px-1.5 py-1 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
    <p class="mt-3 text-center text-eyebrow text-muted">{{ $items->count() }} {{ str('item')->plural($items->count()) }} in the catalog</p>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit equipment' : 'New equipment'" max="md" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5">
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Equipment name</label>
                        <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="e.g. AV & sound system">
                        @error('name')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Unit price</label>
                        <input type="number" step="0.01" min="0" wire:model="price" class="input h-10 text-sm" placeholder="0">
                        @error('price')<p class="mt-1 text-xs text-risk">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">Notes (optional)</label>
                        <input type="text" wire:model="notes" class="input h-10 text-sm" placeholder="Spec, supplier, etc.">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="h-10 rounded-xl px-4 text-xs font-semibold text-navy-600 hover:text-navy-900">Cancel</button>
                        <button type="submit" class="btn-navy h-10 px-6 text-xs">{{ $editingId ? 'Update' : 'Add' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
