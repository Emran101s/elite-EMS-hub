@php
    use App\Models\ServiceItem;

    $may = auth()->user()?->can('write') ?? false;
@endphp

@php
    // The section the tabs are on, in the words the screen uses.
    $openSection = $section === 'all' ? null : ($section === 'none' ? 'Unsectioned' : ($sections[$section] ?? $section));
@endphp

<div class="space-y-4">

    {{-- ══ sections: where a service comes from ══ --}}
    <div class="flex flex-wrap items-center gap-1.5 border-b border-line pb-2.5">
        <button type="button" wire:click="pickSection('all')"
                @class(['flex items-center gap-1.5 rounded-2xl px-3 py-1.5 text-[12px] font-bold transition',
                    'bg-navy-950 text-white shadow-sm' => $section === 'all',
                    'text-navy-500 hover:bg-navy-50 hover:text-navy-900' => $section !== 'all'])>
            Everything
            <span @class(['text-[10.5px] font-black tabular-nums', 'text-gold-400' => $section === 'all', 'text-navy-300' => $section !== 'all'])>{{ $counts->sum() }}</span>
        </button>

        @foreach ($sections as $key => $label)
            <button type="button" wire:click="pickSection(@js($key))"
                    @class(['flex items-center gap-1.5 rounded-2xl px-3 py-1.5 text-[12px] font-bold transition',
                        'bg-navy-950 text-white shadow-sm' => $section === $key,
                        'text-navy-500 hover:bg-navy-50 hover:text-navy-900' => $section !== $key])>
                {{ $label }}
                <span @class(['text-[10.5px] font-black tabular-nums', 'text-gold-400' => $section === $key, 'text-navy-300' => $section !== $key])>{{ $counts[$key] ?? 0 }}</span>
            </button>
        @endforeach

        {{-- Only when something is actually unfiled — an empty tab that is
             always there just reads as a section nobody named. --}}
        @if (($counts['none'] ?? 0) > 0)
            <button type="button" wire:click="pickSection('none')"
                    @class(['flex items-center gap-1.5 rounded-2xl px-3 py-1.5 text-[12px] font-bold transition',
                        'bg-navy-950 text-white shadow-sm' => $section === 'none',
                        'text-navy-400 hover:bg-navy-50 hover:text-navy-900' => $section !== 'none'])>
                Unsectioned
                <span @class(['text-[10.5px] font-black tabular-nums', 'text-gold-400' => $section === 'none', 'text-navy-300' => $section !== 'none'])>{{ $counts['none'] }}</span>
            </button>
        @endif

        <a href="{{ route('taxonomies.index', ['list' => 'service_section']) }}" wire:navigate
           class="ms-auto text-[11px] font-semibold text-navy-400 transition hover:text-gold-700">Edit sections</a>
    </div>

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-2">
        <div class="relative">
            <x-icon name="search" class="pointer-events-none absolute left-3 top-1/2 h-3.5 w-3.5 -translate-y-1/2 text-navy-300" />
            <input type="search" wire:model.live.debounce.300ms="q" placeholder="Item, code, category…"
                   class="input h-10 w-56 !rounded-2xl !py-0 !ps-9 text-xs xl:w-72">
        </div>

        <details class="relative" data-menu>
            <summary class="flex h-10 cursor-pointer list-none items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300 [&::-webkit-details-marker]:hidden">
                <x-icon name="list" class="h-3.5 w-3.5 text-navy-400" />
                {{ $category === 'all' ? 'All categories' : $category }}
                @if ($category !== 'all')<span class="h-1.5 w-1.5 rounded-full bg-gold-500"></span>@endif
            </summary>
            <div class="absolute z-30 mt-2 max-h-72 w-56 overflow-y-auto rounded-2xl border border-line bg-white p-1.5 shadow-xl">
                <button type="button" wire:click="$set('category', 'all')"
                        @class(['flex w-full rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                            'bg-navy-950 text-white' => $category === 'all', 'text-navy-600 hover:bg-page' => $category !== 'all'])>All categories</button>
                @foreach ($categories as $c)
                    <button type="button" wire:click="$set('category', @js($c))"
                            @class(['flex w-full truncate rounded-xl px-3 py-2 text-start text-[12px] font-semibold transition',
                                'bg-navy-950 text-white' => $category === $c, 'text-navy-600 hover:bg-page' => $category !== $c])>{{ $c }}</button>
                @endforeach
            </div>
        </details>

        <label class="flex h-10 cursor-pointer items-center gap-2 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-600 shadow-sm">
            <input type="checkbox" wire:model.live="showInactive" class="h-3.5 w-3.5 rounded border-navy-300">
            Show retired
        </label>

        <p class="text-[11.5px] text-muted">{{ $items->count() }} of {{ $total }}</p>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('catalogue.template', $section === 'all' || $section === 'none' ? [] : ['section' => $section]) }}"
               class="flex h-10 items-center gap-1.5 rounded-2xl border border-line bg-white px-3.5 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300">
                <x-icon name="archive" class="h-3.5 w-3.5 text-navy-400" /> Template
            </a>

            @if ($may)
                <button type="button" wire:click="newItem"
                        class="flex h-10 items-center rounded-2xl bg-navy-950 px-4 text-[12px] font-bold text-white shadow-[0_10px_24px_-14px_rgba(11,31,58,0.9)] transition hover:bg-navy-800">
                    ＋ New item
                </button>
            @endif
        </div>
    </div>

    {{-- ══ import ══ --}}
    @if ($may)
        <div class="card flex flex-wrap items-center gap-3 p-3.5">
            <span class="grid h-8 w-8 shrink-0 place-items-center rounded-lg bg-navy-950 text-gold-400">
                <x-icon name="archive" class="h-4 w-4" />
            </span>
            <span class="min-w-0">
                <span class="block text-[12.5px] font-bold text-navy-900">
                    {{ $openSection && $section !== 'none' ? 'Import into '.mb_strtolower($openSection) : 'Import a filled price list' }}
                </span>
                <span class="block text-[11px] text-muted">
                    A row with a code you already use updates that item rather than adding a second one.
                    @if ($openSection && $section !== 'none')
                        Every row lands in <span class="font-semibold text-navy-700">{{ $openSection }}</span> unless the sheet names another section.
                    @endif
                </span>
            </span>

            <input type="file" wire:model="importFile" accept=".xlsx,.xls,.csv"
                   class="ms-auto max-w-[240px] text-[11px] file:mr-2 file:rounded-lg file:border-0 file:bg-navy-50 file:px-2.5 file:py-1.5 file:text-[11px] file:font-bold file:text-navy-700">

            <button type="button" wire:click="import" @disabled(! $importFile)
                    class="rounded-xl bg-navy-950 px-3.5 py-2 text-[12px] font-bold text-white transition hover:bg-navy-800 disabled:opacity-30">
                Import
            </button>

            @error('importFile') <p class="w-full text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
            @if ($importMsg)
                <p class="w-full text-[11.5px] font-semibold text-emerald-700">{{ $importMsg }}</p>
            @endif
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
                    <input type="text" wire:model="itemCategory" list="cat-list" placeholder="Accommodation" class="input h-9 w-full text-xs">
                    <datalist id="cat-list">
                        @foreach ($categories as $c)<option value="{{ $c }}">@endforeach
                    </datalist>
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
                        @foreach (ServiceItem::UNITS as $key => [$label, $noun, $factors])
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                        Price per {{ ServiceItem::UNITS[$unit][1] ?? 'item' }}
                    </span>
                    <input type="number" step="0.01" min="0" wire:model="price" placeholder="0.00" class="input h-9 w-full text-end text-xs">
                    @error('price') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Currency</span>
                    <input type="text" maxlength="3" wire:model="currency" class="input h-9 w-full uppercase text-xs">
                </label>

                <label class="block">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Tax %</span>
                    <input type="number" step="0.5" min="0" max="100" wire:model="tax" placeholder="From the document"
                           class="input h-9 w-full text-xs">
                    @error('tax') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                </label>

                <label class="block sm:col-span-2 xl:col-span-4">
                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Detail</span>
                    <input type="text" wire:model="detail" placeholder="What is included, and what is not."
                           class="input h-9 w-full text-xs">
                </label>
            </div>

            {{-- What the unit means, in the words the invoice will use. --}}
            @php $factors = ServiceItem::UNITS[$unit][2] ?? []; @endphp
            <p class="mt-2.5 text-[11px] text-muted">
                @if ($factors === [])
                    Priced once per engagement — the invoice will not ask for a quantity.
                @else
                    On an invoice this asks for <span class="font-semibold text-navy-700">{{ mb_strtolower(implode(' and ', $factors)) }}</span>
                    and multiplies them.
                @endif
            </p>

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
                            wire:confirm="Delete this item? Retiring it instead keeps it on the invoices that used it."
                            class="ms-auto rounded-xl px-3 py-2 text-[12px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">
                        Delete
                    </button>
                @endif
            </div>
        </div>
    @endif

    {{-- ══ the list ══ --}}
    @if ($items->isEmpty())
        <x-empty icon="archive"
                 title="{{ $openSection ? 'Nothing in '.mb_strtolower($openSection).' yet' : 'Nothing priced yet' }}"
                 hint="Add an item, or download the template, fill it in and import it." />
    @else
        <div class="space-y-3">
            @foreach ($groups as $group => $rows)
                <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-sm">
                    <div class="flex items-center gap-2 border-b border-line bg-navy-50/50 px-4 py-2">
                        <span class="text-[12px] font-black uppercase tracking-[0.14em] text-navy-700">{{ $group }}</span>
                        <span class="text-[11px] font-semibold text-navy-300">{{ $rows->count() }}</span>
                        {{-- Browsing everything, the section is the thing you cannot
                             work out from the category. Inside one, it is noise. --}}
                        @if ($section === 'all')
                            @foreach ($rows->pluck('section')->unique() as $s)
                                <span class="rounded-full bg-navy-50 px-2 py-0.5 text-[10px] font-bold text-navy-500">
                                    {{ $s ? ($sections[$s] ?? $s) : 'Unsectioned' }}
                                </span>
                            @endforeach
                        @endif
                    </div>

                    <div class="overflow-x-auto">
                        <div class="min-w-[820px]">
                            @php $cols = 'grid-cols-[104px_1fr_190px_120px_86px_92px]'; @endphp

                            <div class="grid {{ $cols }} gap-3 border-b border-line/70 px-4 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                                <span>Code</span><span>Item</span><span>Sold by</span>
                                <span class="text-end">Price</span><span class="text-end">Tax</span><span></span>
                            </div>

                            @foreach ($rows as $item)
                                <div wire:key="si-{{ $item->id }}"
                                     class="grid {{ $cols }} items-center gap-3 border-b border-line/50 px-4 py-2 transition last:border-0 hover:bg-navy-50/30 {{ $item->active ? '' : 'opacity-50' }}">

                                    <span class="truncate font-mono text-[11px] font-semibold text-navy-500">{{ $item->code ?: '—' }}</span>

                                    <button type="button" @if ($may) wire:click="edit({{ $item->id }})" @endif class="min-w-0 text-start">
                                        <span class="block truncate text-[12.5px] font-bold text-navy-900">{{ $item->name }}</span>
                                        @if ($item->detail)
                                            <span class="block truncate text-[10.5px] text-muted">{{ $item->detail }}</span>
                                        @endif
                                    </button>

                                    <span class="text-[11.5px] text-navy-600">{{ $item->unitLabel() }}</span>

                                    <span class="pf text-end text-[13px] font-black tabular-nums text-navy-900">
                                        {{ $item->currency }} {{ number_format($item->unit_price_cents / 100, 2) }}
                                    </span>

                                    <span class="text-end text-[11px] {{ $item->tax_pct === null ? 'italic text-navy-300' : 'font-semibold text-navy-600' }}">
                                        {{ $item->tax_pct === null ? 'doc' : rtrim(rtrim(number_format($item->tax_pct, 1), '0'), '.').'%' }}
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
