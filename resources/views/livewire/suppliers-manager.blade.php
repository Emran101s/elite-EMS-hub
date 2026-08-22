@php
    // Same event_supplier pivot query the old shared operations-header ran.
    $openSupplierIssues = \App\Models\Event::query()
        ->withCount(['suppliers as issues_count' => fn ($q) => $q->where('event_supplier.status', 'issue')])
        ->get()
        ->sum('issues_count');
@endphp

<div class="max-w-6xl">
    <x-cc.header eyebrow="Operations Command" title="Suppliers" subtitle="Your supplier network, rated and categorized — entered once, picked by any event.">
        <x-slot:actions>
            @if (\Illuminate\Support\Facades\Route::has('venues.index'))
                <a href="{{ route('venues.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Venues →</a>
            @endif
            @if (\Illuminate\Support\Facades\Route::has('requirements.index'))
                <a href="{{ route('requirements.index') }}" class="rounded-full border border-line bg-white px-3.5 py-2 text-[12px] font-bold text-ink transition hover:-translate-y-0.5 hover:border-navy-300">Equipment →</a>
            @endif
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search suppliers…"
                       class="h-9 w-48 rounded-full border border-line bg-white py-0 pl-9 pr-3 text-[12.5px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none">
            </div>
            <button type="button" wire:click="newItem" class="rounded-full bg-gold-500 px-3.5 py-2 text-[12px] font-bold text-navy-900 shadow-raise transition hover:-translate-y-0.5 hover:bg-gold-400">＋ Add Supplier</button>
        </x-slot:actions>
    </x-cc.header>

    <div class="mt-4 grid grid-cols-2 gap-3 sm:grid-cols-4">
        <x-cc.kpi-tile label="Suppliers" :value="number_format(\App\Models\Supplier::count())" hint="Vendor directory" tone="live" />
        <x-cc.kpi-tile label="Venues" :value="number_format(\App\Models\Venue::count())" hint="Locations on file" />
        <x-cc.kpi-tile label="Equipment" :value="number_format(\App\Models\Requirement::count())" hint="Catalog items" />
        <x-cc.kpi-tile label="Open supplier issues" :value="number_format($openSupplierIssues)" hint="Flagged across live events" :tone="$openSupplierIssues > 0 ? 'warn' : 'ok'" />
    </div>

    <div class="mt-5 flex flex-wrap items-center gap-1.5">
        @foreach (['all' => 'All'] + collect(\App\Models\Supplier::CATEGORIES)->mapWithKeys(fn ($c) => [$c => str($c)->replace('_', ' & ')->title()])->all() as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-[12px] font-bold transition',
                        'bg-navy-900 text-white' => $filter === $key,
                        'bg-white text-muted ring-1 ring-line hover:text-ink' => $filter !== $key,
                    ])>
                {{ $label }}
                <span @class([
                    'rounded-full px-1.5 text-[10px] tabular-nums',
                    'bg-white/15' => $filter === $key,
                    'bg-page text-muted' => $filter !== $key,
                ])>{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-5">
        @if ($suppliers->isEmpty())
            <x-eo.empty-state icon="truck" title="No suppliers yet"
                     hint="Add the caterers, AV houses and logistics partners you work with. Each becomes a reusable vendor any event can pick from.">
                <x-slot:actions>
                    <x-eo.button size="sm" wire:click="newItem">＋ Add your first supplier</x-eo.button>
                </x-slot:actions>
            </x-eo.empty-state>
        @else
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($suppliers as $supplier)
                    <x-operations.supplier-card :supplier="$supplier" :selected="$this->isSelected($supplier->id)" />
                @endforeach
            </div>
        @endif
    </div>

    <x-bulk-bar :count="$this->selectedCount()" noun="supplier" />

    {{-- ══ add / edit modal ══ --}}
    @if ($showForm)
        <x-modal :title="$editingId ? 'Edit supplier' : 'New supplier'"
                 subtitle="A reusable vendor any event's Budget, Catering, Stay or Transport can pick."
                 max="xl" close="$set('showForm', false)">
            <form wire:submit="save" class="grid gap-3.5 sm:grid-cols-2">
                <div class="sm:col-span-2">
                    <label class="eo-label mb-1">Supplier name</label>
                    <input type="text" wire:model="name" class="eo-input h-10 text-sm" placeholder="Petra Catering Co.">
                    @error('name')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="eo-label mb-1">Category</label>
                    <select wire:model="category" class="eo-select h-10 text-sm">
                        <option value="">— None —</option>
                        @foreach (\App\Models\Supplier::CATEGORIES as $c)
                            <option value="{{ $c }}">{{ str($c)->replace('_', ' & ')->title() }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="eo-label mb-1">Rating</label>
                    <input type="number" min="0" max="5" step="0.1" wire:model="rating" class="eo-input h-10 text-sm" placeholder="4.5">
                    @error('rating')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="eo-label mb-1">City</label>
                    <input type="text" wire:model="city" class="eo-input h-10 text-sm" placeholder="Amman">
                </div>
                <div>
                    <label class="eo-label mb-1">Country</label>
                    <input type="text" wire:model="country" class="eo-input h-10 text-sm" placeholder="Jordan">
                </div>

                <div class="sm:col-span-2 mt-1 border-t border-eo-line pt-3">
                    <p class="eo-label">Contact</p>
                </div>
                <div>
                    <label class="eo-label mb-1">Phone</label>
                    <input type="text" wire:model="phone" class="eo-input h-10 text-sm" placeholder="+962 6 000 0000">
                </div>
                <div>
                    <label class="eo-label mb-1">Email</label>
                    <input type="text" wire:model="email" class="eo-input h-10 text-sm" placeholder="events@petracatering.com">
                    @error('email')<p class="mt-1 text-xs text-eo-risk-ink">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                    <x-eo.button type="submit" size="sm">{{ $editingId ? 'Update supplier' : 'Add supplier' }}</x-eo.button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
