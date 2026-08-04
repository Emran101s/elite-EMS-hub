@php
    use App\Models\EventInvoiceItem;

    $may = auth()->user()?->can('manage-budget') ?? false;
    $cur = $event->currency ?: 'JOD';
    $money = fn ($c) => number_format($c / 100, 2);
    $margin = $totals['sell'] - $totals['cost'];
@endphp

<div class="space-y-4">

    {{-- ══ what the list is worth ══ --}}
    <div class="card flex flex-wrap items-center gap-x-6 gap-y-3 p-4">
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Priced to sell</p>
            <p class="pf mt-0.5 text-[20px] font-black leading-none text-navy-950">{{ \App\Support\Money::forDocument($totals['sell'], $cur) }}</p>
        </div>
        <span class="h-9 w-px bg-line"></span>
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Costs us</p>
            <p class="pf mt-0.5 text-[20px] font-black leading-none text-navy-800">{{ \App\Support\Money::forDocument($totals['cost'], $cur) }}</p>
        </div>
        <span class="h-9 w-px bg-line"></span>
        <div>
            <p class="text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-400">Margin</p>
            <p class="pf mt-0.5 text-[20px] font-black leading-none {{ $margin < 0 ? 'text-risk' : 'text-emerald-700' }}">
                {{ $margin < 0 ? '−' : '' }}{{ \App\Support\Money::forDocument(abs($margin), $cur) }}
                @if ($totals['sell'] > 0)
                    <span class="text-[12px] font-bold text-navy-400">{{ round($margin / $totals['sell'] * 100) }}%</span>
                @endif
            </p>
        </div>

        {{-- Selling below cost is worth saying out loud rather than leaving in
             a column somebody has to scan for. --}}
        @if ($underwater->isNotEmpty())
            <p class="ms-auto rounded-xl bg-red-50 px-3 py-1.5 text-[11.5px] font-bold text-red-700">
                {{ $underwater->count() }} {{ str('item')->plural($underwater->count()) }} priced below cost
            </p>
        @endif
    </div>

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Item, code, category…"
                   class="input h-10 w-52 !rounded-2xl !py-0 !ps-9 text-xs xl:w-64">
        </div>

        <label class="flex h-10 cursor-pointer items-center gap-2 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-600 shadow-sm">
            <input type="checkbox" wire:model.live="showInactive" class="h-3.5 w-3.5 rounded border-navy-300"> Show retired
        </label>

        <p class="text-[11.5px] text-muted">{{ $items->count() }} {{ str('item')->plural($items->count()) }}</p>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('events.pricing.template', $event) }}"
               class="flex h-10 items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300">
                <x-icon name="archive" class="h-3.5 w-3.5 text-navy-400" /> Template
            </a>

            @if ($may)
                <button type="button" wire:click="toggleCatalogue"
                        @class(['flex h-10 items-center gap-1.5 rounded-2xl border px-3.5 text-[12px] font-semibold shadow-sm transition',
                            'border-gold-300 bg-gold-50 text-gold-800' => $showCatalogue,
                            'border-line bg-white text-navy-700 hover:border-gold-300' => ! $showCatalogue])>
                    <x-icon name="list" class="h-3.5 w-3.5" /> From the house list
                </button>

                <button type="button" wire:click="newItem"
                        class="flex h-10 items-center rounded-2xl bg-navy-950 px-4 text-[12px] font-bold text-white shadow-[0_10px_24px_-14px_rgba(11,31,58,0.9)] transition hover:bg-navy-800">
                    ＋ New item
                </button>
            @endif
        </div>
    </div>

    {{-- ══ the house list, pulled one at a time ══ --}}
    @if ($showCatalogue)
        <div class="card overflow-hidden border-gold-200">
            <div class="flex flex-wrap items-center gap-2 border-b border-line bg-gold-50/50 px-4 py-2.5">
                <span class="min-w-0">
                    <span class="block text-[12.5px] font-bold text-navy-900">The house price list</span>
                    <span class="block text-[11px] text-muted">
                        Copied at the house price, for you to reprice. Nothing is pulled in automatically —
                        a stale copy of a price nobody agreed is worse than no copy.
                    </span>
                </span>
                <input type="search" wire:model.live.debounce.250ms="catalogueQuery" placeholder="Item, code, or a section…"
                       class="input ms-auto h-8 w-52 text-[11px]">
            </div>

            {{-- The sections, as one-click searches. The house list is long
                 enough that "what does the hotel provide" is a real question. --}}
            <div class="flex flex-wrap items-center gap-1.5 border-b border-line bg-white px-4 py-2">
                <button type="button" wire:click="$set('catalogueQuery', '')"
                        @class(['rounded-full px-2.5 py-1 text-[10.5px] font-bold transition',
                            'bg-navy-950 text-white' => $catalogueQuery === '',
                            'bg-navy-50 text-navy-500 hover:text-navy-900' => $catalogueQuery !== ''])>All</button>
                @foreach ($sections as $label)
                    <button type="button" wire:click="$set('catalogueQuery', @js($label))"
                            @class(['rounded-full px-2.5 py-1 text-[10.5px] font-bold transition',
                                'bg-navy-950 text-white' => $catalogueQuery === $label,
                                'bg-navy-50 text-navy-500 hover:text-navy-900' => $catalogueQuery !== $label])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="scrollbar-none max-h-64 overflow-y-auto">
                @forelse ($catalogue as $it)
                    @php $already = in_array($it->id, $taken, true); @endphp
                    <div wire:key="src-{{ $it->id }}"
                         class="flex items-center gap-3 border-b border-line/50 px-4 py-2 last:border-0 {{ $already ? 'opacity-45' : '' }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12px] font-semibold text-navy-900">{{ $it->name }}</span>
                            <span class="block truncate text-[10.5px] text-muted">
                                @if ($it->section)<span class="font-semibold text-navy-500">{{ $it->sectionLabel() }}</span> · @endif
                                {{ $it->category ?: 'Uncategorised' }} · {{ mb_strtolower($it->unitLabel()) }}
                            </span>
                        </span>
                        <span class="pf shrink-0 text-[12px] font-black tabular-nums text-navy-700">
                            {{ number_format($it->unit_price_cents / 100, 2) }}
                        </span>
                        @if ($already)
                            <span class="shrink-0 text-[10.5px] italic text-navy-400">Already here</span>
                        @else
                            <button type="button" wire:click="pullFromHouse({{ $it->id }})"
                                    class="shrink-0 rounded-lg bg-gold-500 px-2.5 py-1 text-[10.5px] font-bold text-navy-950 transition hover:bg-gold-600">
                                Add it
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-[12px] italic text-navy-300">
                        Nothing in the house list matches. <a href="{{ route('catalogue.index') }}" class="font-semibold text-gold-700 hover:underline">Manage it in Settings</a>.
                    </p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ══ import ══ --}}
    @if ($may)
        <div class="card flex flex-wrap items-center gap-3 p-3.5">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-navy-950 text-gold-400">
                <x-icon name="archive" class="h-4 w-4" />
            </span>
            <span class="min-w-0">
                <span class="block text-[12.5px] font-bold text-navy-900">Import this event's prices</span>
                <span class="block text-[11px] text-muted">A code already priced here is repriced, not duplicated.</span>
            </span>

            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv"
                   class="ms-auto max-w-[240px] text-[11px] file:mr-2 file:rounded-lg file:border-0 file:bg-navy-50 file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-navy-700">

            <button type="button" wire:click="import" @disabled(! $importFile)
                    class="rounded-xl bg-navy-950 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-navy-800 disabled:opacity-30">
                Import
            </button>

            @error('importFile') <p class="w-full text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
            @if ($importMsg)<p class="w-full text-[11.5px] font-semibold text-emerald-700">{{ $importMsg }}</p>@endif
        </div>
    @endif

    {{-- ══ the editor ══ --}}
    @if ($editingId !== null)
        <div class="card border-gold-300 bg-gold-50/40 p-4">
            <p class="field-label !mb-3">{{ $editingId ? 'Edit the item' : 'A new item' }}</p>

            <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Item</span>
                    <input type="text" wire:model="name" placeholder="Double room, 5★" class="input h-9 w-full text-xs">
                    @error('name') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Code</span>
                    <input type="text" wire:model="code" placeholder="ACC-DBL" class="input h-9 w-full text-xs">
                    @error('code') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Category</span>
                    <input type="text" wire:model="itemCategory" placeholder="Accommodation" class="input h-9 w-full text-xs">
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Section</span>
                    <select wire:model="itemSection" class="input h-9 w-full text-xs">
                        <option value="">Unsectioned</option>
                        @foreach ($sections as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('itemSection') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Sold by</span>
                    <select wire:model.live="unit" class="input h-9 w-full text-xs">
                        @foreach (EventInvoiceItem::UNITS as $key => [$label, $noun, $factors])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                        Costs us / {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}
                    </span>
                    <input type="number" step="0.01" min="0" wire:model.live="cost" placeholder="0.00" class="input h-9 w-full text-end text-xs">
                    @error('cost') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                        We charge / {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}
                    </span>
                    <input type="number" step="0.01" min="0" wire:model.live="sell" placeholder="0.00" class="input h-9 w-full text-end text-xs">
                    @error('sell') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Tax %</span>
                    <input type="number" step="0.5" min="0" max="100" wire:model="tax" placeholder="From the invoice" class="input h-9 w-full text-xs">
                </label>

                <label class="block sm:col-span-2 xl:col-span-4">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Detail</span>
                    <input type="text" wire:model="detail" placeholder="What is included, and what is not." class="input h-9 w-full text-xs">
                </label>
            </div>

            {{-- The margin as you type it, so a bad price is obvious before it
                 is saved rather than after it is invoiced. --}}
            @php
                $c = (float) ($cost ?: 0); $s = (float) ($sell ?: 0);
                $m = $s - $c;
                $factors = EventInvoiceItem::UNITS[$unit][2] ?? [];
            @endphp
            <div class="mt-2.5 flex flex-wrap items-center gap-x-4 gap-y-1 text-[11px]">
                <span class="text-muted">
                    Margin per {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}:
                    <span class="font-bold {{ $m < 0 ? 'text-risk' : ($m > 0 ? 'text-emerald-700' : 'text-navy-500') }}">
                        {{ $m < 0 ? '−' : '' }}{{ $cur }} {{ number_format(abs($m), 2) }}
                        @if ($s > 0) · {{ round($m / $s * 100) }}% @endif
                    </span>
                </span>
                @if ($m < 0)
                    <span class="rounded-lg bg-red-50 px-2 py-0.5 font-bold text-red-700">Below cost</span>
                @endif
                <span class="text-muted">
                    @if ($factors === [])
                        Priced once per engagement.
                    @else
                        On an invoice this asks for <span class="font-semibold text-navy-700">{{ mb_strtolower(implode(' and ', $factors)) }}</span>.
                    @endif
                </span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="save"
                        class="rounded-xl bg-navy-950 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-navy-800">
                    {{ $editingId ? 'Save' : 'Add it' }}
                </button>
                <button type="button" wire:click="cancel"
                        class="rounded-xl px-3 py-2 text-[12px] font-bold text-navy-400 transition hover:text-navy-700">Cancel</button>

                <label class="ms-2 flex cursor-pointer items-center gap-2 text-[12px] font-semibold text-navy-600">
                    <input type="checkbox" wire:model="active" class="h-3.5 w-3.5 rounded border-navy-300"> In use
                </label>

                @if ($editingId)
                    <button type="button" wire:click="destroy({{ $editingId }})"
                            wire:confirm="Delete this item? Retiring it keeps it on the invoices that used it."
                            class="ms-auto rounded-xl px-3 py-2 text-[12px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">
                        Delete
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- ══ the list ══ --}}
    @if ($items->isEmpty())
        <x-empty icon="currency" title="Nothing priced for this event yet"
                 hint="Add an item, pull some from the house list, or import a filled template." />
    @else
        <div class="space-y-3">
            @foreach ($groups as $group => $rows)
                <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
                    <div class="flex items-center gap-2 border-b border-line bg-navy-50/50 px-4 py-2">
                        <span class="text-[12px] font-black uppercase tracking-[0.14em] text-navy-700">{{ $group }}</span>
                        <span class="text-[11px] font-semibold text-navy-300">{{ $rows->count() }}</span>
                        {{-- Who supplies it, which the category does not say. --}}
                        @foreach ($rows->pluck('section')->filter()->unique() as $sec)
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-navy-500 ring-1 ring-line">
                                {{ $sections[$sec] ?? $sec }}
                            </span>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[880px]">
                            @php $cols = 'grid-cols-[100px_1fr_170px_104px_104px_110px_86px]'; @endphp

                            <div class="grid {{ $cols }} gap-3 border-b border-line/70 px-4 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                                <span>Code</span><span>Item</span><span>Sold by</span>
                                <span class="text-end">Costs us</span><span class="text-end">We charge</span>
                                <span class="text-end">Margin</span><span></span>
                            </div>

                            @foreach ($rows as $item)
                                <div wire:key="ei-{{ $item->id }}"
                                     class="grid {{ $cols }} items-center gap-3 border-b border-line/50 px-4 py-2 transition last:border-0 hover:bg-navy-50/30 {{ $item->active ? '' : 'opacity-50' }}">

                                    <span class="truncate font-mono text-[11px] font-semibold text-navy-500">{{ $item->code ?: '—' }}</span>

                                    <button type="button" @if ($may) wire:click="edit({{ $item->id }})" @endif class="min-w-0 text-start">
                                        <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $item->name }}</span>
                                        @if ($item->detail)
                                            <span class="block truncate text-[10.5px] text-muted">{{ $item->detail }}</span>
                                        @endif
                                    </button>

                                    <span class="text-[11.5px] text-navy-600">{{ $item->unitLabel() }}</span>

                                    <span class="text-end text-[12px] font-semibold tabular-nums {{ $item->cost_cents ? 'text-navy-700' : 'italic text-navy-300' }}">
                                        {{ $item->cost_cents ? $money($item->cost_cents) : 'not costed' }}
                                    </span>

                                    <span class="pf text-end text-[13px] font-black tabular-nums text-navy-900">
                                        {{ $money($item->sell_cents) }}
                                    </span>

                                    <span class="text-end text-[11.5px] font-bold tabular-nums {{ $item->isUnderwater() ? 'text-risk' : 'text-emerald-700' }}">
                                        {{ $item->marginCents() < 0 ? '−' : '' }}{{ $money(abs($item->marginCents())) }}
                                        @if ($item->marginPct() !== null)
                                            {{-- Same minus sign as the figure beside it, not an ASCII hyphen. --}}
                                            <span class="text-navy-400">{{ $item->marginPct() < 0 ? '−' : '' }}{{ abs($item->marginPct()) }}%</span>
                                        @endif
                                    </span>

                                    <span class="text-end">
                                        @if ($may)
                                            <button type="button" wire:click="toggleActive({{ $item->id }})"
                                                    class="rounded-lg px-2 py-1 text-[10.5px] font-bold transition {{ $item->active ? 'text-navy-400 hover:bg-navy-50 hover:text-navy-700' : 'text-emerald-600 hover:bg-emerald-50' }}">
                                                {{ $item->active ? 'Retire' : 'Restore' }}
                                            </button>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>
