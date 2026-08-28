@php
    use App\Models\EventInvoiceItem;

    $may = auth()->user()?->can('manage-budget') ?? false;
    $cur = $event->currency ?: 'JOD';
    $money = fn ($c) => number_format($c / 100, 3);
    $margin = $totals['sell'] - $totals['cost'];
    $marginPct = $totals['sell'] > 0 ? (int) round($margin / $totals['sell'] * 100) : null;
@endphp

<div class="cx-canvas space-y-2.5">

    {{-- The Priced-to-sell / Costs-us / Margin trio is gone: the Universal
         Module Header renders those exact three figures ~90px above this,
         and showed them first. Precision was the argument for keeping both,
         but a duplicated summary costs more than three decimal places buy. --}}

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-2.5 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-muted" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Item, code, category…"
                   class="h-9 w-52 rounded-full border border-line bg-white pl-8 pr-3 text-xs text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none xl:w-64">
        </div>

        <label class="flex h-9 cursor-pointer items-center gap-2 rounded-full border border-line bg-white px-3 text-eyebrow font-semibold text-muted shadow-sm">
            <input type="checkbox" wire:model.live="showInactive" class="h-3.5 w-3.5 rounded border-line"> Show retired
        </label>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('events.pricing.template', $event) }}" class="cx-btn cx-btn-ghost" style="height:34px">
                <x-icon name="archive" class="h-3.5 w-3.5 text-muted" /> Template
            </a>

            @if ($may)
                <button type="button" wire:click="toggleCatalogue" class="cx-chip {{ $showCatalogue ? 'is-on' : '' }}" style="height:34px">
                    <x-icon name="list" class="me-1 inline h-3.5 w-3.5 align-middle" /> House list
                </button>

                <button type="button" wire:click="newItem" class="cx-btn cx-btn-accent" style="height:34px">＋ New item</button>
            @endif
        </div>
    </div>

    {{-- ══ the house list, pulled one at a time ══ --}}
    @if ($showCatalogue)
        <div class="cx-lcard" style="border-color: var(--cx-accent)">
            <div class="flex flex-wrap items-center gap-2 border-b border-line px-3.5 py-2" style="background: var(--cx-accent-wash)">
                <span class="min-w-0">
                    <span class="block text-[12.5px] font-bold text-ink">The house price list</span>
                    <span class="block text-[11px] text-muted">
                        Copied at the house price, for you to reprice. Nothing is pulled in automatically —
                        a stale copy of a price nobody agreed is worse than no copy.
                    </span>
                </span>
                <input type="search" wire:model.live.debounce.250ms="catalogueQuery" placeholder="Item, code, or a section…"
                       class="ms-auto h-8 w-52 rounded-lg border border-line bg-white px-2.5 text-[11px] text-ink focus:border-navy-300 focus:outline-none">
            </div>

            {{-- The sections, as one-click searches. The house list is long
                 enough that "what does the hotel provide" is a real question. --}}
            <div class="flex flex-wrap items-center gap-1.5 border-b border-line bg-white px-4 py-2">
                <button type="button" wire:click="$set('catalogueQuery', '')"
                        class="cx-chip {{ $catalogueQuery === '' ? 'is-on' : '' }}">All</button>
                @foreach ($sections as $label)
                    <button type="button" wire:click="$set('catalogueQuery', @js($label))"
                            class="cx-chip {{ $catalogueQuery === $label ? 'is-on' : '' }}">{{ $label }}</button>
                @endforeach
            </div>

            <div class="scrollbar-none max-h-64 overflow-y-auto">
                @forelse ($catalogue as $it)
                    @php $already = in_array($it->id, $taken, true); @endphp
                    <div wire:key="src-{{ $it->id }}"
                         class="flex items-center gap-3 border-b border-line/50 px-4 py-2 last:border-0 {{ $already ? 'opacity-45' : '' }}">
                        <span class="min-w-0 flex-1">
                            <span class="block truncate text-[12px] font-semibold text-ink">{{ $it->name }}</span>
                            <span class="block truncate text-[10.5px] text-muted">
                                @if ($it->section)<span class="font-semibold text-muted">{{ $it->sectionLabel() }}</span> · @endif
                                {{ $it->category ?: 'Uncategorised' }} · {{ mb_strtolower($it->unitLabel()) }}
                            </span>
                        </span>
                        <span class="shrink-0 text-[12px] font-black tabular-nums text-ink">
                            {{ number_format($it->unit_price_cents / 100, 2) }}
                        </span>
                        @if ($already)
                            <span class="shrink-0 text-[10.5px] italic text-muted">Already here</span>
                        @else
                            <button type="button" wire:click="pullFromHouse({{ $it->id }})"
                                    class="shrink-0 rounded-lg bg-gold-500 px-2.5 py-1 text-[10.5px] font-bold text-navy-900 transition hover:bg-gold-400">
                                Add it
                            </button>
                        @endif
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-[12px] italic text-muted">
                        Nothing in the house list matches. <a href="{{ route('catalogue.index') }}" class="font-semibold text-gold-700 hover:underline">Manage it in Settings</a>.
                    </p>
                @endforelse
            </div>
        </div>
    @endif

    {{-- ══ import ══ --}}
    @if ($may)
        <div class="cx-lcard flex flex-wrap items-center gap-3 p-3">
            <span class="cx-cathex shrink-0" style="width:28px;height:31px;background:var(--cx-espresso-1);color:var(--cx-accent)">
                <x-icon name="archive" class="h-3.5 w-3.5" />
            </span>
            <span class="min-w-0">
                <span class="block text-[12.5px] font-bold text-ink">Import this event's prices</span>
                <span class="block text-[11px] text-muted">A code already priced here is repriced, not duplicated.</span>
            </span>

            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv"
                   class="ms-auto max-w-[240px] text-[11px] text-ink file:mr-2 file:rounded-lg file:border-0 file:bg-page file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-ink">

            <button type="button" wire:click="import" @disabled(! $importFile)
                    class="cx-btn cx-btn-accent disabled:opacity-30" style="height:34px">
                Import
            </button>

            @error('importFile') <p class="w-full text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
            @if ($importMsg)<p class="w-full text-[11.5px] font-semibold text-success-ink">{{ $importMsg }}</p>@endif
        </div>
    @endif

    {{-- ══ the editor ══ --}}
    @if ($editingId !== null)
        <div class="cx-lcard p-3.5" style="border-color: var(--cx-accent); background: var(--cx-accent-wash)">
            <p class="mb-3 text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ $editingId ? 'Edit the item' : 'A new item' }}</p>

            <div class="grid gap-2.5 sm:grid-cols-2 xl:grid-cols-4">
                <label class="block xl:col-span-2">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Item</span>
                    <input type="text" wire:model="name" placeholder="Double room, 5★" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                    @error('name') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Code</span>
                    <input type="text" wire:model="code" placeholder="ACC-DBL" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                    @error('code') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Category</span>
                    <input type="text" wire:model="itemCategory" placeholder="Accommodation" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Section</span>
                    <select wire:model="itemSection" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                        <option value="">Unsectioned</option>
                        @foreach ($sections as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('itemSection') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Sold by</span>
                    <select wire:model.live="unit" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                        @foreach (EventInvoiceItem::UNITS as $key => [$label, $noun, $factors])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">
                        Costs us / {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}
                    </span>
                    <input type="number" step="0.001" min="0" wire:model.live="cost" placeholder="0.00" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-end text-xs text-ink focus:border-navy-300 focus:outline-none">
                    @error('cost') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">
                        We charge / {{ EventInvoiceItem::UNITS[$unit][1] ?? 'item' }}
                    </span>
                    <input type="number" step="0.001" min="0" wire:model.live="sell" placeholder="0.00" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-end text-xs text-ink focus:border-navy-300 focus:outline-none">
                    @error('sell') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Tax %</span>
                    <input type="number" step="0.5" min="0" max="100" wire:model="tax" placeholder="From the invoice" class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
                </label>

                <label class="block sm:col-span-2 xl:col-span-4">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Detail</span>
                    <input type="text" wire:model="detail" placeholder="What is included, and what is not." class="h-9 w-full rounded-lg border border-line bg-white px-2.5 text-xs text-ink focus:border-navy-300 focus:outline-none">
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
                    <span class="font-bold {{ $m < 0 ? 'text-danger-ink' : ($m > 0 ? 'text-success-ink' : 'text-muted') }}">
                        {{ $m < 0 ? '−' : '' }}{{ $cur }} {{ number_format(abs($m), 2) }}
                        @if ($s > 0) · {{ round($m / $s * 100) }}% @endif
                    </span>
                </span>
                @if ($m < 0)
                    <span class="rounded-lg bg-danger-soft px-2 py-0.5 font-bold text-danger-ink">Below cost</span>
                @endif
                <span class="text-muted">
                    @if ($factors === [])
                        Priced once per engagement.
                    @else
                        On an invoice this asks for <span class="font-semibold text-ink">{{ mb_strtolower(implode(' and ', $factors)) }}</span>.
                    @endif
                </span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2">
                <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                        class="rounded-full bg-navy-900 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-navy-800">
                    <x-busy target="save" busy="Saving…">{{ $editingId ? 'Save' : 'Add it' }}</x-busy>
                </button>
                <button type="button" wire:click="cancel"
                        class="rounded-full px-3 py-2 text-[12px] font-bold text-muted transition hover:text-ink">Cancel</button>

                <label class="ms-2 flex cursor-pointer items-center gap-2 text-[12px] font-semibold text-muted">
                    <input type="checkbox" wire:model="active" class="h-3.5 w-3.5 rounded border-line"> In use
                </label>

                @if ($editingId)
                    <x-confirm title="Delete this item?"
                               body="Retiring it keeps it on the invoices that used it."
                               confirm="Delete"
                               run="$wire.destroy({{ $editingId }})"
                               class="ms-auto rounded-full px-3 py-2 text-[12px] font-bold text-muted transition hover:bg-danger-soft hover:text-danger-ink">
                        Delete
                    </x-confirm>
                @endif
            </div>
        </div>
    @endif

    {{-- ══ the list ══ --}}
    @if ($items->isEmpty())
        <div class="cx-empty">
            <h3>Nothing priced for this event yet</h3>
            <p>Add an item, pull some from the house list, or import a filled template.</p>
        </div>
    @else
        {{-- ══ ONE price list, not eight ══
             Each category used to be its own card with its own copy of the
             column header, so a 17-item list carried eight identical
             "CODE / ITEM / SOLD BY / COSTS US / WE CHARGE / MARGIN" rows.
             The headings are the noise; the prices are the content. One
             table now, headed once, with each category as a rule across it.

             The columns are re-proportioned too: Item is what you read and
             it was truncating to "Bed & breakfa…" while "Sold by" — a short
             phrase like "Per person" — held 170px. --}}
        {{-- The last column holds a "Retire"/"Restore" text button as well as the
     edit and delete icons; at 58px its label overflowed into the margin
     figure beside it. Sized to what it actually contains. --}}
        @php $cols = 'grid-cols-[80px_minmax(0,1.5fr)_100px_84px_84px_92px_108px]'; @endphp
        <div class="cx-lcard">
          <div class="overflow-x-auto">
            <div class="min-w-[720px]">
              <div class="grid {{ $cols }} gap-3 border-b border-line px-3.5 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-muted" style="background: var(--cx-surface-2)">
                  <span>Code</span><span>Item</span><span>Sold by</span>
                  <span class="text-end">Costs us</span><span class="text-end">We charge</span>
                  <span class="text-end">Margin</span><span></span>
              </div>
            @foreach ($groups as $group => $rows)
                <div>
                    <div class="flex items-center gap-2 border-b border-line px-3.5 py-1.5" style="background: var(--cx-surface-3)">
                        <span class="text-eyebrow font-black uppercase tracking-[0.14em] text-ink">{{ $group }}</span>
                        <span class="text-eyebrow font-semibold text-muted">{{ $rows->count() }}</span>
                        {{-- Who supplies it, which the category does not say. --}}
                        @foreach ($rows->pluck('section')->filter()->unique() as $sec)
                            <span class="rounded-full bg-white px-2 py-0.5 text-[10px] font-bold text-muted ring-1 ring-line">
                                {{ $sections[$sec] ?? $sec }}
                            </span>
                        @endforeach
                    </div>

                            @foreach ($rows as $item)
                                <div wire:key="ei-{{ $item->id }}"
                                     class="group grid {{ $cols }} items-center gap-3 border-b border-line/50 px-3.5 py-1.5 transition last:border-0 hover:bg-page {{ $item->active ? '' : 'opacity-50' }}">

                                    <span class="truncate font-mono text-[11px] font-semibold text-muted">{{ $item->code ?: '—' }}</span>

                                    <button type="button" @if ($may) wire:click="edit({{ $item->id }})" @endif class="min-w-0 text-start">
                                        <span class="block truncate text-[12.5px] font-bold text-ink">{{ $item->name }}</span>
                                        @if ($item->detail)
                                            <span class="block truncate text-[10.5px] text-muted">{{ $item->detail }}</span>
                                        @endif
                                    </button>

                                    <span class="text-[11.5px] text-muted">{{ $item->unitLabel() }}</span>

                                    <span class="text-end text-[12px] font-semibold tabular-nums {{ $item->cost_cents ? 'text-ink' : 'italic text-muted' }}">
                                        {{ $item->cost_cents ? $money($item->cost_cents) : 'not costed' }}
                                    </span>

                                    <span class="text-end text-[13px] font-black tabular-nums text-ink">
                                        {{ $money($item->sell_cents) }}
                                    </span>

                                    <span class="text-end text-[11.5px] font-bold tabular-nums {{ $item->isUnderwater() ? 'text-danger-ink' : 'text-success-ink' }}">
                                        {{ $item->marginCents() < 0 ? '−' : '' }}{{ $money(abs($item->marginCents())) }}
                                        @if ($item->marginPct() !== null)
                                            {{-- Same minus sign as the figure beside it, not an ASCII hyphen. --}}
                                            <span class="text-muted">{{ $item->marginPct() < 0 ? '−' : '' }}{{ abs($item->marginPct()) }}%</span>
                                        @endif
                                    </span>

                                    <span class="flex items-center justify-end gap-1 text-end">
                                        @if ($may)
                                            <button type="button" wire:click="toggleActive({{ $item->id }})"
                                                    class="rounded-lg px-2 py-1 text-[10.5px] font-bold transition {{ $item->active ? 'text-muted hover:bg-page hover:text-ink' : 'text-success-ink hover:bg-success-soft' }}">
                                                {{ $item->active ? 'Retire' : 'Restore' }}
                                            </button>
                                            <button type="button" wire:click="edit({{ $item->id }})" title="Edit"
                                                    class="rounded-lg px-1.5 py-1 text-[11px] font-bold text-muted opacity-100 transition sm:opacity-0 hover:bg-page hover:text-ink sm:group-hover:opacity-100">✎</button>
                                            <x-confirm title="Delete “{{ $item->name }}” from this event's price list?"
                                                       body="This doesn't affect invoices already raised from it."
                                                       confirm="Delete"
                                                       run="$wire.destroy({{ $item->id }})"
                                                       class="rounded-lg bg-danger-soft px-1.5 py-1 text-[11px] font-bold text-danger-ink opacity-100 transition sm:opacity-0 hover:bg-danger-soft/70 sm:group-hover:opacity-100">✕</x-confirm>
                                        @endif
                                    </span>
                                </div>
                            @endforeach
                </div>
            @endforeach
            </div>
          </div>
        </div>
    @endif
</div>
