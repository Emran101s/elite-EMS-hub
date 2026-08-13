<div class="max-w-6xl">
    <x-eo.operations-header active="suppliers" title="Suppliers" subtitle="Your supplier network, rated and categorized — entered once, picked by any event.">
        <x-slot:actions>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search suppliers…"
                       class="eo-input h-9 w-48 !rounded-xl !py-0 !ps-9 text-xs">
            </div>
            <x-eo.button size="sm" wire:click="newItem">＋ Add Supplier</x-eo.button>
        </x-slot:actions>
    </x-eo.operations-header>

    <div class="mt-4 flex flex-wrap items-center gap-1.5">
        @foreach (['all' => 'All'] + collect(\App\Models\Supplier::CATEGORIES)->mapWithKeys(fn ($c) => [$c => str($c)->replace('_', ' & ')->title()])->all() as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold transition',
                        'bg-eo-navy text-white' => $filter === $key,
                        'bg-white text-eo-muted ring-1 ring-eo-line hover:text-eo-text' => $filter !== $key,
                    ])>
                {{ $label }}
                <span @class([
                    'rounded-full px-1.5 text-[10px] tabular-nums',
                    'bg-white/15' => $filter === $key,
                    'bg-eo-bg text-eo-muted' => $filter !== $key,
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
                    <div wire:key="supplier-{{ $supplier->id }}" class="group eo-soft-card flex flex-col overflow-hidden transition hover:-translate-y-0.5">
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <button type="button" wire:click="toggleSelect({{ $supplier->id }})"
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-[10px] {{ $this->isSelected($supplier->id) ? 'border-eo-navy bg-eo-navy text-white' : 'border-eo-line text-transparent hover:border-eo-muted' }}"
                                            title="Select">✓</button>
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-bold text-eo-gold" style="background: linear-gradient(135deg, var(--color-eo-navy-mid), var(--color-eo-navy-deep));">{{ str($supplier->name)->substr(0, 1) }}</span>
                                    <div class="min-w-0">
                                        <p class="truncate text-sm font-bold text-eo-text">{{ $supplier->name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-eo-muted">
                                            {{ $supplier->category ? str($supplier->category)->replace('_', ' & ')->title() : 'Uncategorised' }}
                                            @if ($supplier->city) · {{ $supplier->city }}@if ($supplier->country), {{ $supplier->country }}@endif @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="eo-pill eo-pill-premium">★ {{ number_format($supplier->rating, 1) }}</span>
                                    <span class="flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                                        <button type="button" wire:click="edit({{ $supplier->id }})" title="Edit" class="rounded-lg bg-eo-bg px-1.5 py-1 text-[10px] font-bold text-eo-muted hover:bg-eo-line">✎</button>
                                        <x-confirm title="Delete “{{ $supplier->name }}”?"
                                                   body="Events using it keep working — they just lose the vendor link."
                                                   confirm="Delete" run="$wire.delete({{ $supplier->id }})"
                                                   class="rounded-lg bg-eo-risk/10 px-1.5 py-1 text-[10px] font-bold text-eo-risk hover:bg-eo-risk/20">✕</x-confirm>
                                    </span>
                                </div>
                            </div>

                            @if ($supplier->email || $supplier->phone)
                                <div class="mt-2.5 rounded-lg bg-eo-workspace px-2.5 py-1.5 text-[11px] text-eo-muted">
                                    @if ($supplier->email)<span class="font-semibold text-eo-text">{{ $supplier->email }}</span>@endif
                                    @if ($supplier->phone) · {{ $supplier->phone }}@endif
                                </div>
                            @endif
                        </div>
                        <div class="mt-auto flex items-center gap-2 border-t border-eo-line bg-eo-workspace px-3.5 py-2 text-eo-text">
                            <x-icon name="truck" class="h-3 w-3 shrink-0 text-eo-gold" />
                            <span class="truncate text-[11px] font-semibold text-eo-muted">Working on {{ $supplier->events_count }} {{ str('event')->plural($supplier->events_count) }}</span>
                        </div>
                    </div>
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
                    @error('name')<p class="mt-1 text-xs text-eo-risk">{{ $message }}</p>@enderror
                </div>

                <div>
                    <label class="eo-label mb-1">Category</label>
                    <select wire:model="category" class="eo-select h-10 text-sm">
                        <option value="">— None —</option>
                        @foreach (\App\Models\Supplier::CATEGORIES as $c)
                            <option value="{{ $c }}">{{ str($c)->replace('_', ' & ')->title() }}</option>
                        @endforeach
                    </select>
                    @error('category')<p class="mt-1 text-xs text-eo-risk">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="eo-label mb-1">Rating</label>
                    <input type="number" min="0" max="5" step="0.1" wire:model="rating" class="eo-input h-10 text-sm" placeholder="4.5">
                    @error('rating')<p class="mt-1 text-xs text-eo-risk">{{ $message }}</p>@enderror
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
                    @error('email')<p class="mt-1 text-xs text-eo-risk">{{ $message }}</p>@enderror
                </div>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="eo-btn-ghost eo-btn-sm">Cancel</button>
                    <x-eo.button type="submit" size="sm">{{ $editingId ? 'Update supplier' : 'Add supplier' }}</x-eo.button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
