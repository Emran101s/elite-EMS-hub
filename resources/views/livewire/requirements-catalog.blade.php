@php
    $openSupplierIssues = \App\Models\Event::query()
        ->withCount(['suppliers as issues_count' => fn ($q) => $q->where('event_supplier.status', 'issue')])
        ->get()
        ->sum('issues_count');
@endphp

<div>
    <x-cc.header eyebrow="Operations Command" title="Equipment Catalog" subtitle="Your reusable list of equipment & prices — pick these when adding equipment to a venue or event.">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('suppliers.index'))
                <a href="{{ route('suppliers.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Suppliers →</a>
            @endif
            @if (\Illuminate\Support\Facades\Route::has('venues.index'))
                <a href="{{ route('venues.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Venues →</a>
            @endif
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search equipment…"
                   class="h-9 w-48 rounded-full border border-line bg-white px-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
            <button type="button" wire:click="$toggle('showImport')" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">⇪ Import</button>
            <a href="{{ route('requirements.pdf') }}" class="inline-flex items-center gap-1.5 rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300"><x-icon name="chart" class="h-3.5 w-3.5" /> PDF</a>
            <button type="button" wire:click="newItem" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Add equipment</button>
        </x-slot:actions>
    </x-cc.header>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-cc.kpi-tile label="Suppliers" :value="number_format(\App\Models\Supplier::count())" hint="Vendor directory" />
        <x-cc.kpi-tile label="Venues" :value="number_format(\App\Models\Venue::count())" hint="Locations on file" />
        <x-cc.kpi-tile label="Equipment" :value="number_format(\App\Models\Requirement::count())" hint="Catalog items" tone="live" />
        <x-cc.kpi-tile label="Open supplier issues" :value="number_format($openSupplierIssues)" hint="Flagged across live events" :tone="$openSupplierIssues > 0 ? 'warn' : 'ok'" />
    </div>

    @if ($showImport)
        <form wire:submit="import" class="mt-5 flex flex-wrap items-end gap-3 rounded-lg border border-line bg-white p-4">
            <div class="flex-1">
                <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted" for="eq-import">Excel (.xlsx) or CSV — columns: <span class="font-semibold text-ink">name</span>, price, notes</label>
                <input id="eq-import" type="file" wire:model="importFile" accept=".xlsx,.xls,.csv,text/csv"
                       class="h-10 w-full rounded-lg border border-line bg-white text-[13px] text-ink file:mr-3 file:rounded-md file:border-0 file:bg-navy-900 file:px-3 file:py-1.5 file:text-[11.5px] file:font-semibold file:text-white">
                <p class="mt-1 text-[11px] text-muted">First row is treated as headers. No headers? We'll use column order: name, price, notes.</p>
                @error('importFile') <p class="mt-1 text-xs text-danger-ink">{{ $message }}</p> @enderror
            </div>
            <button type="button" wire:click="$set('showImport', false)" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink hover:border-navy-300">Cancel</button>
            <button type="submit" wire:loading.attr="disabled" wire:target="import,importFile" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">Import</button>
        </form>
    @endif

    <div class="mt-5 overflow-hidden rounded-lg border border-line bg-white">
        @if ($items->isEmpty())
            <div class="px-6 py-16 text-center">
                <p class="text-[13.5px] font-semibold text-ink">Your catalog is empty</p>
                <p class="mx-auto mt-1 max-w-md text-[12px] text-muted">Add the equipment you use across events — each with a price. You'll then choose from this list when adding equipment to a venue.</p>
                <button type="button" wire:click="newItem" class="mt-4 rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Add your first item</button>
            </div>
        @else
            <div class="overflow-x-auto">
                <table class="w-full min-w-[520px]">
                    <thead>
                        <tr class="border-b border-line text-eyebrow font-bold uppercase tracking-[0.1em] text-muted">
                            <th class="px-5 py-2.5 text-left">Equipment</th>
                            <th class="px-3 py-2.5 text-right">Unit price</th>
                            <th class="px-3 py-2.5 text-left">Notes</th>
                            <th class="px-3 py-2.5 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($items as $r)
                            <tr wire:key="req-{{ $r->id }}" class="group">
                                <td class="px-5 py-3 text-[13px] font-semibold text-ink">{{ $r->name }}</td>
                                <td class="px-3 py-3 text-right text-[13px] font-bold tabular-nums text-ink">{{ $r->unit_price_cents ? number_format($r->unit_price_cents / 100, 2) : '—' }}</td>
                                <td class="px-3 py-3 text-[12px] text-muted">{{ $r->notes }}</td>
                                <td class="px-3 py-3">
                                    <div class="flex items-center justify-end gap-1 opacity-0 transition group-hover:opacity-100">
                                        <button type="button" wire:click="edit({{ $r->id }})" class="rounded-md bg-page px-1.5 py-1 text-[10px] font-bold text-muted hover:bg-line">✎</button>
                                        <x-confirm title="Delete “{{ $r->name }}” from the catalog?" confirm="Delete" run="$wire.delete({{ $r->id }})" class="rounded-md bg-danger-soft px-1.5 py-1 text-[10px] font-bold text-danger-ink hover:bg-danger-soft/70">✕</x-confirm>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
    <p class="mt-3 text-center text-[11px] text-muted">{{ $items->count() }} {{ str('item')->plural($items->count()) }} in the catalog</p>

    {{-- modal --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit equipment' : 'New equipment'" max="md" close="set('showForm', false)">
                <form wire:submit="save" class="grid gap-3.5">
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Equipment name</label>
                        <input type="text" wire:model="name" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="e.g. AV & sound system">
                        @error('name')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Unit price</label>
                        <input type="number" step="0.01" min="0" wire:model="price" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="0">
                        @error('price')<p class="mt-1 text-xs text-danger-ink">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted mb-1">Notes (optional)</label>
                        <input type="text" wire:model="notes" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-10 text-sm" placeholder="Spec, supplier, etc.">
                    </div>
                    <div class="flex justify-end gap-2">
                        <button type="button" wire:click="$set('showForm', false)" class="btn-sm rounded-full border border-line font-semibold text-ink transition hover:border-gold-300">Cancel</button>
                        <button type="submit" class="rounded-full bg-gold-500 px-4 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">{{ $editingId ? 'Update' : 'Add' }}</button>
                    </div>
                </form>
        </x-modal>
    @endif
</div>
