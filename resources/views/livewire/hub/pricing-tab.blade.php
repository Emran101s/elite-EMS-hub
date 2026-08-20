@php
    use App\Models\EventInvoiceItem;

    $may = auth()->user()?->can('manage-budget') ?? false;
    $cur = $event->currency ?: 'JOD';
    $money = fn ($c) => number_format($c / 100, 3);
    $margin = $totals['sell'] - $totals['cost'];
    $marginPct = $totals['sell'] > 0 ? (int) round($margin / $totals['sell'] * 100) : null;
@endphp

<div class="space-y-3">

    {{-- "Items" is dropped — it's an exact duplicate of the Universal Module
         Header's own count. The three money figures stay: the header shows
         them rounded to whole units, this strip carries the full 3-decimal
         precision these line items were built for, plus the margin's %
         bar, neither of which the header has room to show. --}}
    <x-stat-strip class="mb-0" :stats="[
        ['Priced to sell', \App\Support\Money::forDocument($totals['sell'], $cur, 3), 'currency', null, null, $underwater->isNotEmpty() ? $underwater->count().' below cost' : null, $underwater->isNotEmpty() ? 'text-eo-risk' : 'text-eo-text'],
        ['Costs us', \App\Support\Money::forDocument($totals['cost'], $cur, 3), 'list', null, null, null],
        ['Margin', ($margin < 0 ? '−' : '').\App\Support\Money::forDocument(abs($margin), $cur, 3), 'chart', $marginPct !== null ? max(0, min(100, abs($marginPct))) : null, $margin < 0 ? 'bg-eo-risk' : 'bg-track', $marginPct !== null ? $marginPct.'%' : null, $margin < 0 ? 'text-eo-risk' : 'text-emerald-700'],
    ]" />

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-eo-muted" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Item, code, category…"
                   class="eo-input h-9 w-52 !py-0 !ps-8 text-xs xl:w-64">
        </div>

        <label class="flex h-9 cursor-pointer items-center gap-2 rounded-xl border border-eo-line bg-white px-3 text-eyebrow font-semibold text-eo-muted shadow-sm">
            <input type="checkbox" wire:model.live="showInactive" class="h-3.5 w-3.5 rounded border-eo-line"> Show retired
        </label>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('events.pricing.template', $event) }}"
               class="flex h-9 items-center gap-1.5 rounded-xl border border-eo-line bg-white px-3 text-xs font-semibold text-eo-text shadow-sm transition hover:border-eo-teal">
                <x-icon name="archive" class="h-3.5 w-3.5 text-eo-muted" /> Template
            </a>

            @if ($may)
                <button type="button" wire:click="toggleCatalogue"
                        @class(['flex h-9 items-center gap-1.5 rounded-xl border px-3 text-xs font-semibold shadow-sm transition',
                            'border-eo-teal/40 bg-eo-teal-soft text-eo-teal-deep' => $showCatalogue,
                            'border-eo-line bg-white text-eo-text hover:border-eo-teal' => ! $showCatalogue])>
                    <x-icon name="list" class="h-3.5 w-3.5" /> House list
                </button>

                <button type="button" wire:click="newItem" class="eo-btn-primary h-9 px-3.5 text-xs">＋ New item</button>
            @endif
        </div>
    </div>

    {{-- ══ the house list, pulled one at a time ══ --}}
    @if ($showCatalogue)
        <div class="eo-soft-card overflow-hidden border-eo-teal/30">
            <div class="flex flex-wrap items-center gap-2 border-b border-eo-line bg-eo-teal-soft/50 px-4 py-2.5">
                <span class="min-w-0">
                    <span class="block text-[12.5px] font-bold text-eo-text">The house price list</span>
                    <span class="block text-[11px] text-eo-muted">
                        Copied at the house price, for you to reprice. Nothing is pulled in automatically —
                        a stale copy of a price nobody agreed is worse than no copy.
                    </span>
                </span>
                <input type="search" wire:model.live.debounce.250ms="catalogueQuery" placeholder="Item, code, or a section…"
                       class="eo-input ms-auto h-8 w-52 text-[11px]">
            </div>

            {{-- The sections, as one-click searches. The house list is long
                 enough that "what does the hotel provide" is a real question. --}}
            <div class="flex flex-wrap items-center gap-1.5 border-b border-eo-line bg-white px-4 py-2">
                <button type="button" wire:click="$set('catalogueQuery', '')"
                        @class(['rounded-full px-2.5 py-1 text-[10.5px] font-bold transition',
                            'bg-eo-navy-deep text-white' => $catalogueQuery === '',
                            'bg-eo-bg text-eo-muted hover:text-eo-text' => $catalogueQuery !== ''])>All</button>
                @foreach ($sections as $label)
                    <button type="button" wire:click="$set('catalogueQuery', @js($label))"
                            @class(['rounded-full px-2.5 py-1 text-[10.5px] font-bold transition',
                                'bg-eo-navy-deep text-white' => $catalogueQuery === $label,
                                'bg-eo-bg text-eo-muted hover:text-eo-text' => $catalogueQuery !== $label])>{{ $label }}</button>
                @endforeach
            </div>

            <div class="scrollbar-none max-h-64 overflow-y-auto">
                @forelse ($catalogue as $it)
                    @php $already = in_array($it->id, $taken, true); @endphp
                    <div wire:key="src-{{ $it->id }}"
                         class="flex items-center gap-3 border-b border-eo-line/50 px-4 py-2 last:border-0 {{ $already ? 'opacity-45' : '' }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12px] font-semibold text-eo-text">{{ $it->name }}</span>
                            <span class="block truncate text-[10.5px] text-eo-muted">
                                @if ($it->section)<span class="font-semibold text-eo-muted">{{ $it->sectionLabel() }}</span> · @endif
                                {{ $it->category ?: 'Uncategorised' }} · {{ mb_strtolower($it->unitLabel()) }}
                            </span>
                        </span>
                        <span class="shrink-0 text-[12px] font-black tabular-nums text-eo-text">
                            {{ number_format($it->unit_price_cents / 100, 2) }}
                        </span>
                        @if ($already)
                            <span class="shrink-0 text-[10.5px] italic text-eo-muted">Already here</span>
                        @else
                            <button type="button" wire:click="pullFromHouse({{ $it->id }})"
                                    class="shrink-0 rounded-lg bg-eo-teal px-2.5 py-1 text-[10.5px] font-bold text-eo-text transition hover:bg-eo-teal-deep">
                                Add it
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-[12px] italic text-eo-muted">
                        Nothing in the house list matches. <a href="{{ route('catalogue.index') }}" class="font-semibold text-eo-teal-ink hover:underline">Manage it in Settings</a>.
                    </p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ══ import ══ --}}
    @if ($may)
        <div class="eo-soft-card flex flex-wrap items-center gap-3 p-3.5">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-eo-navy-deep text-eo-teal-lit">
                <x-icon name="archive" class="h-4 w-4" />
            </span>
            <span class="min-w-0">
                <span class="block text-[12.5px] font-bold text-eo-text">Import this event's prices</span>
                <span class="block text-[11px] text-eo-muted">A code already priced here is repriced, not duplicated.</span>
            </span>

            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv"
                   class="ms-auto max-w-[240px] text-[11px] file:mr-2 file:rounded-lg file:border-0 file:bg-eo-bg file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-eo-text">

            <button type="button" wire:click="import" @disabled(! $importFile)
                    class="rounded-xl bg-eo-navy-deep px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-eo-navy-mid disabled:opacity-30">
                Import
            </button>

            @error('importFile') <p class="w-full text-[11px] font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
            @if ($importMsg)<p class="w-full text-[11.5px] font-semibold text-emerald-700">{{ $importMsg }}</p>@endif
        </div>
    @endif

    {{-- ══ the editor ══ --}}
    @if ($editingId !== null)
        <div class="eo-soft-card border-eo-teal/40 bg-eo-teal-soft/40 p-4">
            <p class="eo-label !mb-3">{{ $editingId ? 'Edit the item' : 'A new item' }}</p>

            <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Item</span>
                    <input type="text" wire:model="name" placeholder="Double room, 5★" class="eo-input h-9 w-full text-xs">
                    @error('name') <p class="mt-1 text-[11px] font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Code</span>
                    <input type="text" wire:model="code" placeholder="ACC-DBL" class="eo-input h-9 w-full text-xs">
                    @error('code') <p class="mt-1 text-[11px] font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Category</span>
                    <input type="text" wire:model="itemCategory" placeholder="Accommodation" class="eo-input h-9 w-full text-xs">
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Section</span>
                    <select wire:model="itemSection" class="eo-input h-9 w-full text-xs">
                        <option value="">Unsectioned</option>
                        @foreach ($sections as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('itemSection') <p class="mt-1 text-[11px] font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Sold by</span>
                    <select wire:model.live="unit" class="eo-input h-9 w-full text-xs">
                        @foreach (EventInvoiceItem::UNITS as $key => [$label, $noun, $factors])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">
                        Costs us / {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}
                    </span>
                    <input type="number" step="0.001" min="0" wire:model.live="cost" placeholder="0.00" class="eo-input h-9 w-full text-end text-xs">
                    @error('cost') <p class="mt-1 text-[11px] font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">
                        We charge / {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}
                    </span>
                    <input type="number" step="0.001" min="0" wire:model.live="sell" placeholder="0.00" class="eo-input h-9 w-full text-end text-xs">
                    @error('sell') <p class="mt-1 text-[11px] font-semibold text-eo-risk-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Tax %</span>
                    <input type="number" step="0.5" min="0" max="100" wire:model="tax" placeholder="From the invoice" class="eo-input h-9 w-full text-xs">
                </label>

                <label class="block sm:col-span-2 xl:col-span-4">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Detail</span>
                    <input type="text" wire:model="detail" placeholder="What is included, and what is not." class="eo-input h-9 w-full text-xs">
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
                <span class="text-eo-muted">
                    Margin per {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}:
                    <span class="font-bold {{ $m < 0 ? 'text-eo-risk-ink' : ($m > 0 ? 'text-emerald-700' : 'text-eo-muted') }}">
                        {{ $m < 0 ? '−' : '' }}{{ $cur }} {{ number_format(abs($m), 2) }}
                        @if ($s > 0) · {{ round($m / $s * 100) }}% @endif
                    </span>
                </span>
                @if ($m < 0)
                    <span class="rounded-lg bg-red-50 px-2 py-0.5 font-bold text-red-700">Below cost</span>
                @endif
                <span class="text-eo-muted">
                    @if ($factors === [])
                        Priced once per engagement.
                    @else
                        On an invoice this asks for <span class="font-semibold text-eo-text">{{ mb_strtolower(implode(' and ', $factors)) }}</span>.
                    @endif
                </span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="rounded-xl bg-eo-navy-deep px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-eo-navy-mid">
                    <x-busy target="save" busy="Saving…">{{ $editingId ? 'Save' : 'Add it' }}</x-busy>
                </button>
                <button type="button" wire:click="cancel"
                        class="rounded-xl px-3 py-2 text-[12px] font-bold text-eo-muted transition hover:text-eo-text">Cancel</button>

                <label class="ms-2 flex cursor-pointer items-center gap-2 text-[12px] font-semibold text-eo-muted">
                    <input type="checkbox" wire:model="active" class="h-3.5 w-3.5 rounded border-eo-line"> In use
                </label>

                @if ($editingId)
                    <x-confirm title="Delete this item?"
                               body="Retiring it keeps it on the invoices that used it."
                               confirm="Delete"
                               run="$wire.destroy({{ $editingId }})"
                               class="ms-auto rounded-xl px-3 py-2 text-[12px] font-bold text-eo-muted transition hover:bg-red-50 hover:text-red-600">
                        Delete
                    </x-confirm>
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
                <div class="overflow-hidden rounded-2xl border border-eo-line bg-white/90 shadow-sm backdrop-blur-xl">
                    <div class="flex items-center gap-2 border-b border-eo-line bg-eo-workspace/50 px-3.5 py-1.5">
                        <span class="text-eyebrow font-black uppercase tracking-[0.14em] text-eo-text">{{ $group }}</span>
                        <span class="text-eyebrow font-semibold text-eo-muted">{{ $rows->count() }}</span>
                        {{-- Who supplies it, which the category does not say. --}}
                        @foreach ($rows->pluck('section')->filter()->unique() as $sec)
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-eo-muted ring-1 ring-line">
                                {{ $sections[$sec] ?? $sec }}
                            </span>
                        @endforeach
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[880px]">
                            @php $cols = 'grid-cols-[100px_1fr_170px_104px_104px_110px_86px]'; @endphp

                            <div class="grid {{ $cols }} gap-3 border-b border-eo-line/70 px-3.5 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-eo-muted">
                                <span>Code</span><span>Item</span><span>Sold by</span>
                                <span class="text-end">Costs us</span><span class="text-end">We charge</span>
                                <span class="text-end">Margin</span><span></span>
                            </div>

                            @foreach ($rows as $item)
                                <div wire:key="ei-{{ $item->id }}"
                                     class="group grid {{ $cols }} items-center gap-3 border-b border-eo-line/50 px-3.5 py-1.5 transition last:border-0 hover:bg-eo-workspace/40 {{ $item->active ? '' : 'opacity-50' }}">

                                    <span class="truncate font-mono text-[11px] font-semibold text-eo-muted">{{ $item->code ?: '—' }}</span>

                                    <button type="button" @if ($may) wire:click="edit({{ $item->id }})" @endif class="min-w-0 text-start">
                                        <span class="block truncate text-[12.5px] font-bold text-eo-text">{{ $item->name }}</span>
                                        @if ($item->detail)
                                            <span class="block truncate text-[10.5px] text-eo-muted">{{ $item->detail }}</span>
                                        @endif
                                    </button>

                                    <span class="text-[11.5px] text-eo-muted">{{ $item->unitLabel() }}</span>

                                    <span class="text-end text-[12px] font-semibold tabular-nums {{ $item->cost_cents ? 'text-eo-text' : 'italic text-eo-muted' }}">
                                        {{ $item->cost_cents ? $money($item->cost_cents) : 'not costed' }}
                                    </span>

                                    <span class="text-end text-[13px] font-black tabular-nums text-eo-text">
                                        {{ $money($item->sell_cents) }}
                                    </span>

                                    <span class="text-end text-[11.5px] font-bold tabular-nums {{ $item->isUnderwater() ? 'text-eo-risk-ink' : 'text-emerald-700' }}">
                                        {{ $item->marginCents() < 0 ? '−' : '' }}{{ $money(abs($item->marginCents())) }}
                                        @if ($item->marginPct() !== null)
                                            {{-- Same minus sign as the figure beside it, not an ASCII hyphen. --}}
                                            <span class="text-eo-muted">{{ $item->marginPct() < 0 ? '−' : '' }}{{ abs($item->marginPct()) }}%</span>
                                        @endif
                                    </span>

                                    <span class="flex items-center justify-end gap-1 text-end">
                                        @if ($may)
                                            <button type="button" wire:click="toggleActive({{ $item->id }})"
                                                    class="rounded-lg px-2 py-1 text-[10.5px] font-bold transition {{ $item->active ? 'text-eo-muted hover:bg-eo-bg hover:text-eo-text' : 'text-emerald-600 hover:bg-emerald-50' }}">
                                                {{ $item->active ? 'Retire' : 'Restore' }}
                                            </button>
                                            <button type="button" wire:click="edit({{ $item->id }})" title="Edit"
                                                    class="rounded-lg px-1.5 py-1 text-[11px] font-bold text-eo-muted opacity-100 transition sm:opacity-0 hover:bg-eo-bg hover:text-eo-text sm:group-hover:opacity-100">✎</button>
                                            <x-confirm title="Delete “{{ $item->name }}” from this event's price list?"
                                                       body="This doesn't affect invoices already raised from it."
                                                       confirm="Delete"
                                                       run="$wire.destroy({{ $item->id }})"
                                                       class="rounded-lg bg-eo-risk/10 px-1.5 py-1 text-[11px] font-bold text-red-700 opacity-100 transition sm:opacity-0 hover:bg-eo-risk/20 sm:group-hover:opacity-100">✕</x-confirm>
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
