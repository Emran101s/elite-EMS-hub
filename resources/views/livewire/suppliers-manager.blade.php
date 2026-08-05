<div class="max-w-6xl">
    <x-page-head title="Suppliers" subtitle="Your supplier network, rated and categorized — entered once, picked by any event.">
        <x-slot:actions>
            <div class="relative">
                <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
                <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search suppliers…"
                       class="input h-9 w-48 !rounded-xl !py-0 !ps-9 text-xs">
            </div>
            <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add Supplier</button>
        </x-slot:actions>
    </x-page-head>

    <div class="mt-4 flex flex-wrap items-center gap-1.5">
        @foreach (['all' => 'All'] + collect(\App\Models\Supplier::CATEGORIES)->mapWithKeys(fn ($c) => [$c => str($c)->replace('_', ' & ')->title()])->all() as $key => $label)
            <button type="button" wire:click="$set('filter', '{{ $key }}')"
                    @class([
                        'inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-bold transition',
                        'bg-navy-900 text-white' => $filter === $key,
                        'bg-white text-navy-500 ring-1 ring-line hover:text-navy-900' => $filter !== $key,
                    ])>
                {{ $label }}
                <span @class([
                    'rounded-full px-1.5 text-[10px] tabular-nums',
                    'bg-white/15' => $filter === $key,
                    'bg-navy-50 text-navy-400' => $filter !== $key,
                ])>{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach
    </div>

    <div class="mt-5">
        @if ($suppliers->isEmpty())
            <x-empty icon="truck" title="No suppliers yet"
                     hint="Add the caterers, AV houses and logistics partners you work with. Each becomes a reusable vendor any event can pick from.">
                <x-slot:actions>
                    <button type="button" wire:click="newItem" class="btn-gold btn-sm">＋ Add your first supplier</button>
                </x-slot:actions>
            </x-empty>
        @else
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                @foreach ($suppliers as $supplier)
                    <div wire:key="supplier-{{ $supplier->id }}" class="group op-card">
                        <div class="flex flex-1 flex-col p-5">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex min-w-0 items-center gap-3">
                                    <button type="button" wire:click="toggleSelect({{ $supplier->id }})"
                                            class="flex h-4 w-4 shrink-0 items-center justify-center rounded border text-eyebrow {{ $this->isSelected($supplier->id) ? 'border-navy-900 bg-navy-900 text-white' : 'border-navy-200 text-transparent hover:border-navy-400' }}"
                                            title="Select">✓</button>
                                    <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl text-sm font-bold text-gold-400" style="background: linear-gradient(135deg, #1E3352, #14315a);">{{ str($supplier->name)->substr(0, 1) }}</span>
                                    <div class="min-w-0">
                                        <p class="pf truncate text-sm font-bold text-navy-900">{{ $supplier->name }}</p>
                                        <p class="mt-0.5 truncate text-xs text-muted">
                                            {{ $supplier->category ? str($supplier->category)->replace('_', ' & ')->title() : 'Uncategorised' }}
                                            @if ($supplier->city) · {{ $supplier->city }}@if ($supplier->country), {{ $supplier->country }}@endif @endif
                                        </p>
                                    </div>
                                </div>
                                <div class="flex shrink-0 items-center gap-1">
                                    <span class="pill bg-gold-50 text-gold-700 ring-1 ring-gold-200">★ {{ number_format($supplier->rating, 1) }}</span>
                                    <span class="flex items-center gap-0.5 opacity-0 transition group-hover:opacity-100">
                                        <button type="button" wire:click="edit({{ $supplier->id }})" title="Edit" class="rounded-lg bg-navy-50 px-1.5 py-1 text-eyebrow font-bold text-navy-600 hover:bg-navy-100">✎</button>
                                        <button type="button" wire:click="delete({{ $supplier->id }})"
                                                wire:confirm="Delete “{{ $supplier->name }}”? Events using it keep working — they just lose the vendor link."
                                                title="Delete" class="rounded-lg bg-risk/10 px-1.5 py-1 text-eyebrow font-bold text-red-700 hover:bg-risk/20">✕</button>
                                    </span>
                                </div>
                            </div>

                            @if ($supplier->email || $supplier->phone)
                                <div class="mt-2.5 rounded-lg bg-page/60 px-2.5 py-1.5 text-micro text-navy-600">
                                    @if ($supplier->email)<span class="font-semibold text-navy-800">{{ $supplier->email }}</span>@endif
                                    @if ($supplier->phone) · {{ $supplier->phone }}@endif
                                </div>
                            @endif
                        </div>
                        <div class="op-card-foot">
                            <x-icon name="truck" class="h-3 w-3 shrink-0 text-gold-600" />
                            <span class="truncate text-eyebrow font-semibold text-navy-700">Working on {{ $supplier->events_count }} {{ str('event')->plural($supplier->events_count) }}</span>
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
                <x-field label="Supplier name" :error="$errors->first('name')" class="sm:col-span-2">
                    <input type="text" wire:model="name" class="input h-10 text-sm" placeholder="Petra Catering Co.">
                </x-field>

                <x-field label="Category" :error="$errors->first('category')">
                    <select wire:model="category" class="input h-10 text-sm">
                        <option value="">— None —</option>
                        @foreach (\App\Models\Supplier::CATEGORIES as $c)
                            <option value="{{ $c }}">{{ str($c)->replace('_', ' & ')->title() }}</option>
                        @endforeach
                    </select>
                </x-field>
                <x-field label="Rating" :error="$errors->first('rating')">
                    <input type="number" min="0" max="5" step="0.1" wire:model="rating" class="input h-10 text-sm" placeholder="4.5">
                </x-field>

                <x-field label="City">
                    <input type="text" wire:model="city" class="input h-10 text-sm" placeholder="Amman">
                </x-field>
                <x-field label="Country">
                    <input type="text" wire:model="country" class="input h-10 text-sm" placeholder="Jordan">
                </x-field>

                <div class="sm:col-span-2 mt-1 border-t border-line pt-3">
                    <p class="eyebrow">Contact</p>
                </div>
                <x-field label="Phone">
                    <input type="text" wire:model="phone" class="input h-10 text-sm" placeholder="+962 6 000 0000">
                </x-field>
                <x-field label="Email" :error="$errors->first('email')">
                    <input type="text" wire:model="email" class="input h-10 text-sm" placeholder="events@petracatering.com">
                </x-field>

                <div class="flex justify-end gap-2 sm:col-span-2">
                    <button type="button" wire:click="$set('showForm', false)" class="btn-ghost btn-sm">Cancel</button>
                    <button type="submit" class="btn-navy btn-sm">{{ $editingId ? 'Update supplier' : 'Add supplier' }}</button>
                </div>
            </form>
        </x-modal>
    @endif
</div>
