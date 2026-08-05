@php
    $may = auth()->user()?->can('manage-contract') ?? false;
    $inv = $invoice;
    $state = $inv->state();
    $out = $inv->outstandingCents();
    $cur = $inv->currencyCode();
    $money = fn ($c) => \App\Support\Money::forDocument($c, $cur);
@endphp

<div class="space-y-4">

    {{-- ══ the bar ══ --}}
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('invoices.index') }}" wire:navigate
           class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-[12px] font-semibold text-navy-600 shadow-sm transition hover:text-navy-900">
            ← Invoices
        </a>

        <div class="min-w-0">
            <h1 class="pf truncate text-[20px] font-black leading-none text-navy-950">{{ $inv->number }}</h1>
            <p class="mt-0.5 truncate text-[11.5px] text-muted">
                {{ $inv->bill_to ?: ($inv->client?->name ?: 'No client') }}
                @if ($inv->event) · {{ $inv->event->name }} @endif
            </p>
        </div>

        <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-bold"
              style="color: {{ $inv->stateHex() }}; background: {{ $inv->stateHex() }}1a">{{ $inv->stateLabel() }}</span>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('invoices.pdf', $inv) }}"
               class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-[12px] font-semibold text-navy-700 shadow-sm transition hover:border-gold-300">
                <x-icon name="document" class="h-3.5 w-3.5 text-navy-400" /> PDF
            </a>

            @if ($may && $state === 'draft')
                <button type="button" wire:click="markSent"
                        class="flex h-9 items-center rounded-xl bg-navy-950 px-3.5 text-[12px] font-bold text-white transition hover:bg-navy-800">
                    Mark sent
                </button>
                <x-confirm title="Delete this draft?"
                           body="Its number will not be reused."
                           confirm="Delete" run="$wire.destroyDraft"
                           class="flex h-9 items-center rounded-xl px-3 text-[12px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">
                    Delete
                </x-confirm>
            @elseif ($may && $state !== 'void')
                <x-confirm title="Void this invoice?"
                           body="The number stays in the book with the reason beside it."
                           confirm="Void" run="$wire.void"
                           class="flex h-9 items-center rounded-xl px-3 text-[12px] font-bold text-navy-400 transition hover:bg-navy-50 hover:text-navy-700">
                    Void
                </x-confirm>
            @endif
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">

        {{-- ══════════ THE FORM ══════════ --}}
        <div class="space-y-3">

            {{-- ── who and when ── --}}
            <div class="card p-4">
                <p class="field-label !mb-3">The document</p>

                <div class="space-y-2.5">
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Billed to</span>
                        <input type="text" wire:model.blur="bill_to" wire:change="saveDetails" @disabled(! $may)
                               placeholder="{{ $inv->client?->name ?: 'Client name as it should appear' }}"
                               class="input h-9 w-full text-xs">
                    </label>

                    <div class="grid grid-cols-2 gap-2.5">
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Event</span>
                            <select wire:model="event_id" wire:change="saveDetails" @disabled(! $may) class="input h-9 w-full text-xs">
                                <option value="">No event</option>
                                @foreach ($events as $e)
                                    <option value="{{ $e->id }}">{{ $e->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Client</span>
                            <select wire:model="client_id" wire:change="saveDetails" @disabled(! $may) class="input h-9 w-full text-xs">
                                <option value="">From the event</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Issued</span>
                            <input type="date" wire:model="issued_on" wire:change="saveDetails" @disabled(! $may) class="input h-9 w-full text-xs">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Due</span>
                            <input type="date" wire:model="due_on" wire:change="saveDetails" @disabled(! $may) class="input h-9 w-full text-xs">
                        </label>
                    </div>

                    <div class="grid grid-cols-2 gap-2.5">
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Currency</span>
                            <input type="text" maxlength="3" wire:model.blur="currency" wire:change="saveDetails" @disabled(! $may)
                                   class="input h-9 w-full uppercase text-xs">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Tax %</span>
                            <input type="number" step="0.5" min="0" max="100" wire:model.blur="tax_pct" wire:change="saveDetails" @disabled(! $may)
                                   class="input h-9 w-full text-xs">
                        </label>
                    </div>

                    <label class="block">
                        <span class="mb-1 flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-wide text-navy-400">
                            Management fee %
                            @if ($inv->contract)
                                <span class="rounded bg-navy-50 px-1 py-0.5 text-[9px] normal-case tracking-normal text-navy-500"
                                      title="A contract installment already includes the fee — charging it again bills it twice.">
                                    already in the schedule
                                </span>
                            @endif
                        </span>
                        <input type="number" step="0.5" min="0" max="100" wire:model.blur="fee_pct" wire:change="saveDetails" @disabled(! $may)
                               class="input h-9 w-full text-xs">
                        @error('fee_pct') <p class="mt-1 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                    </label>
                </div>

                @error('currency') <p class="mt-2 text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
            </div>

            {{-- ── the lines ── --}}
            <div class="card overflow-hidden">
                <div class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <p class="field-label !mb-0 flex-1">Lines</p>
                    @if ($may)
                        <button type="button" wire:click="newLine"
                                class="rounded-lg bg-navy-950 px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-navy-800">
                            ＋ Add a line
                        </button>
                    @endif
                </div>

                {{-- The editor sits where the line does, so you can see what you
                     are changing next to what it sits beside. --}}
                @if ($editingLine !== null)
                    <div class="border-b border-line bg-gold-50/40 p-3">
                        <div class="space-y-2">

                            {{-- ── from the price list ──
                                 An item brings its own price and its own way of
                                 being counted: accommodation asks for rooms and
                                 nights, transport for vehicles and days. See
                                 ServiceItem::UNITS. ── --}}
                            @if ($picked)
                                <div class="rounded-xl border border-gold-200 bg-gold-50/70 p-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-[12px] font-bold text-navy-900">{{ $picked->name }}</span>
                                            <span class="block truncate text-[10.5px] text-muted">
                                                {{ $picked->currency }}
                                                {{ number_format(($picked instanceof \App\Models\EventInvoiceItem ? $picked->sell_cents : $picked->unit_price_cents) / 100, 2) }}
                                                {{ mb_strtolower($picked->unitLabel()) }}
                                                @if ($picked->code) · {{ $picked->code }} @endif
                                                @if ($picked instanceof \App\Models\EventInvoiceItem && $picked->marginPct() !== null)
                                                    · <span class="{{ $picked->isUnderwater() ? 'font-bold text-red-600' : 'text-emerald-700' }}">{{ $picked->marginPct() }}% margin</span>
                                                @endif
                                            </span>
                                        </span>
                                        <button type="button" wire:click="unpick"
                                                class="shrink-0 rounded-lg px-2 py-1 text-[10.5px] font-bold text-navy-400 transition hover:bg-white hover:text-navy-700">
                                            Type it instead
                                        </button>
                                    </div>

                                    @if ($picked->factors())
                                        <div class="mt-2 flex flex-wrap items-end gap-2">
                                            @foreach ($picked->factors() as $i => $label)
                                                <label class="w-[92px]">
                                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">{{ $label }}</span>
                                                    <input type="number" step="1" min="0" wire:model.live="factors.{{ $i }}"
                                                           class="input h-9 w-full text-end text-xs">
                                                </label>
                                                @if (! $loop->last)
                                                    <span class="pb-2 text-[13px] font-bold text-navy-300">×</span>
                                                @endif
                                            @endforeach
                                            <span class="pb-2 text-[11.5px] text-muted">
                                                = {{ $qty }} {{ \Illuminate\Support\Str::plural($picked->unitNoun(), (float) $qty) }}
                                            </span>
                                        </div>
                                    @else
                                        <p class="mt-1.5 text-[10.5px] text-muted">A fixed fee — priced once for the engagement.</p>
                                    @endif
                                </div>
                            @elseif ($catalogue->isNotEmpty() || $catalogueQuery !== '')
                                <details class="rounded-xl border border-line bg-white" data-menu>
                                    <summary class="flex cursor-pointer list-none items-center gap-2 px-2.5 py-2 text-[11.5px] font-bold text-navy-600 [&::-webkit-details-marker]:hidden">
                                        <x-icon name="archive" class="h-3.5 w-3.5 text-navy-400" />
                                        {{ $priceListIsEvent ? "Pick from this event's items" : 'Pick from the house price list' }}
                                        <span class="ms-auto text-navy-300">▾</span>
                                    </summary>
                                    <div class="border-t border-line p-2">
                                        {{-- One box across every section: a line does not care
                                             whether the hotel or a production house supplies it. --}}
                                        <input type="search" wire:model.live.debounce.250ms="catalogueQuery"
                                               placeholder="Room, transfer, lunch, or a section…"
                                               class="input mb-1.5 h-8 w-full text-[11px]">
                                        <div class="scrollbar-none max-h-48 overflow-y-auto">
                                            @forelse ($catalogue as $it)
                                                <button type="button" wire:click="pick({{ $it->id }})" wire:key="cat-{{ $it->id }}"
                                                        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start transition hover:bg-page">
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-[11.5px] font-semibold text-navy-900">{{ $it->name }}</span>
                                                        <span class="block truncate text-[10px] text-muted">
                                                            @if ($it->section)
                                                                <span class="font-semibold text-navy-500">{{ $it->sectionLabel() }}</span> ·
                                                            @endif
                                                            {{ $it->category ?: 'Uncategorised' }} · {{ mb_strtolower($it->unitLabel()) }}
                                                        </span>
                                                    </span>
                                                    <span class="pf shrink-0 text-[11.5px] font-black tabular-nums text-navy-700">
                                                        {{ number_format(($it instanceof \App\Models\EventInvoiceItem ? $it->sell_cents : $it->unit_price_cents) / 100, 2) }}
                                                    </span>
                                                </button>
                                            @empty
                                                <p class="px-2 py-3 text-center text-[11px] italic text-navy-300">
                                                    Nothing matches.
                                                    @if ($priceListIsEvent)
                                                        <a href="{{ route('events.hub', [$inv->event, 'tab' => 'pricing']) }}" class="font-semibold text-gold-700 hover:underline">Price it for this event</a>.
                                                    @else
                                                        <a href="{{ route('catalogue.index') }}" class="font-semibold text-gold-700 hover:underline">Add it to the house list</a>.
                                                    @endif
                                                </p>
                                            @endforelse
                                        </div>
                                    </div>
                                </details>
                            @endif

                            <input type="text" wire:model="description" placeholder="What the client is being charged for"
                                   class="input h-9 w-full text-xs">
                            @error('description') <p class="text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror

                            <div class="flex gap-2">
                                <label class="w-[84px]">
                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Qty</span>
                                    {{-- Read-only while an item is picked: the quantity is the
                                         factors multiplied, and two places to change it is one
                                         too many. --}}
                                    <input type="number" step="0.01" min="0" wire:model="qty" @readonly((bool) $picked)
                                           class="input h-9 w-full text-end text-xs {{ $picked ? 'bg-navy-50 text-navy-500' : '' }}">
                                </label>
                                <label class="flex-1">
                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Unit price</span>
                                    <input type="number" step="0.01" wire:model="unit" placeholder="0.00" class="input h-9 w-full text-end text-xs">
                                </label>
                                <label class="w-[110px]">
                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Amount</span>
                                    <span class="pf flex h-9 items-center justify-end rounded-xl bg-white px-2.5 text-[13px] font-black tabular-nums text-navy-900 ring-1 ring-line">
                                        {{ number_format((float) ($qty ?: 0) * (float) ($unit ?: 0), 2) }}
                                    </span>
                                </label>
                            </div>
                            @error('qty') <p class="text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror
                            @error('unit') <p class="text-[11px] font-semibold text-risk">{{ $message }}</p> @enderror

                            <div class="flex gap-2 pt-0.5">
                                <button type="button" wire:click="saveLine"
                                        class="rounded-lg bg-navy-950 px-3 py-1.5 text-[11.5px] font-bold text-white transition hover:bg-navy-800">
                                    {{ $editingLine ? 'Save the line' : 'Add it' }}
                                </button>
                                <button type="button" wire:click="cancelLine"
                                        class="rounded-lg px-2.5 py-1.5 text-[11.5px] font-bold text-navy-400 transition hover:text-navy-700">
                                    Cancel
                                </button>
                                @if ($editingLine)
                                    <button type="button" wire:click="deleteLine({{ $editingLine }})"
                                            class="ms-auto rounded-lg px-2.5 py-1.5 text-[11.5px] font-bold text-navy-400 transition hover:bg-red-50 hover:text-red-600">
                                        Remove
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @forelse ($inv->lines as $line)
                    <div wire:key="line-{{ $line->id }}"
                         class="flex items-center gap-2 border-b border-line/60 px-3 py-2 last:border-0 hover:bg-navy-50/30">

                        @if ($may)
                            <span class="flex shrink-0 flex-col leading-none">
                                <button type="button" wire:click="moveLine({{ $line->id }}, -1)" @disabled($loop->first)
                                        class="text-[9px] text-navy-300 transition hover:text-navy-700 disabled:opacity-25">▲</button>
                                <button type="button" wire:click="moveLine({{ $line->id }}, 1)" @disabled($loop->last)
                                        class="text-[9px] text-navy-300 transition hover:text-navy-700 disabled:opacity-25">▼</button>
                            </span>
                        @endif

                        <button type="button" @if ($may) wire:click="editLine({{ $line->id }})" @endif
                                class="min-w-0 flex-1 text-start">
                            <span class="block truncate text-[12.5px] font-semibold text-navy-900">{{ $line->description }}</span>
                            <span class="block truncate text-[10.5px] text-muted">
                                {{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }} × {{ number_format($line->unit_cents / 100, 2) }}
                                @if ($line->payment_id)
                                    · <span class="text-navy-500">from the schedule</span>
                                @endif
                            </span>
                        </button>

                        <span class="pf shrink-0 text-[13px] font-black tabular-nums text-navy-900">
                            {{ number_format($line->amountCents() / 100, 2) }}
                        </span>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-[12px] italic text-navy-300">
                        Nothing on this invoice yet. Add a line to start.
                    </p>
                @endforelse

                {{-- ── totals ── --}}
                <div class="space-y-1 border-t border-line bg-page/50 px-4 py-3 text-xs">
                    <div class="flex justify-between"><span class="text-muted">Subtotal</span>
                        <span class="font-bold text-navy-900">{{ $money($inv->subtotalCents()) }}</span></div>
                    @if ($inv->fee_pct)
                        <div class="flex justify-between"><span class="text-muted">Management fee ({{ rtrim(rtrim(number_format($inv->fee_pct, 2), '0'), '.') }}%)</span>
                            <span class="font-bold text-gold-700">{{ $money($inv->feeCents()) }}</span></div>
                    @endif
                    @if ($inv->tax_pct)
                        <div class="flex justify-between"><span class="text-muted">Tax ({{ rtrim(rtrim(number_format($inv->tax_pct, 2), '0'), '.') }}%)</span>
                            <span class="font-bold text-navy-900">{{ $money($inv->taxCents()) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-line pt-1.5">
                        <span class="text-eyebrow font-bold uppercase tracking-wide text-navy-900">Total</span>
                        <span class="pf text-[15px] font-black text-navy-950">{{ $money($inv->totalCents()) }}</span></div>
                    @if ($inv->paid_cents > 0)
                        <div class="flex justify-between"><span class="text-muted">Received</span>
                            <span class="font-bold text-emerald-700">− {{ $money($inv->paid_cents) }}</span></div>
                        <div class="flex justify-between"><span class="text-muted">Balance due</span>
                            <span class="font-bold {{ $out > 0 ? 'text-risk' : 'text-emerald-700' }}">{{ $money($out) }}</span></div>
                    @endif
                </div>
            </div>

            {{-- ── money in ── --}}
            @if ($may && $state !== 'draft' && $state !== 'void')
                <div class="card p-4">
                    <p class="field-label !mb-2">Money received</p>
                    <div class="flex gap-2">
                        <input type="text" inputmode="decimal" wire:model="amount"
                               placeholder="{{ number_format($out / 100, 2) }} — blank settles in full"
                               class="input h-9 flex-1 text-xs">
                        <button type="button" wire:click="record"
                                class="rounded-xl bg-navy-950 px-3.5 text-[12px] font-bold text-white transition hover:bg-navy-800">
                            Record
                        </button>
                        @if ($inv->paid_cents > 0)
                            <button type="button" wire:click="clearPaid"
                                    class="rounded-xl px-2.5 text-[12px] font-bold text-navy-400 transition hover:bg-navy-50 hover:text-navy-700">
                                Undo
                            </button>
                        @endif
                    </div>
                    @if ($inv->contract)
                        <p class="mt-2 text-[10.5px] text-muted">
                            Recorded here, it lands on {{ $inv->contract->reference ?: 'the agreement' }}’s schedule too.
                        </p>
                    @endif
                </div>
            @endif

            {{-- ── words ── --}}
            <div class="card p-4">
                <p class="field-label !mb-3">Terms &amp; notes</p>
                <div class="space-y-2.5">
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Terms</span>
                        <textarea wire:model.blur="terms" wire:change="saveDetails" @disabled(! $may) rows="3"
                                  placeholder="Payment within 30 days by bank transfer."
                                  class="input w-full text-xs"></textarea>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-navy-400">Note</span>
                        <textarea wire:model.blur="notes" wire:change="saveDetails" @disabled(! $may) rows="2"
                                  class="input w-full text-xs"></textarea>
                    </label>
                </div>
            </div>
        </div>

        {{-- ══════════ THE PAPER ══════════
             The same partial the PDF renders, so the preview IS the invoice.
             Scaled down to fit the column and pinned while the form scrolls. ══ --}}
        <div class="xl:sticky xl:top-4 xl:self-start">
            <p class="field-label !mb-2">What the client receives</p>
            <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-[0_18px_50px_-30px_rgba(11,31,58,0.6)]">
                <div class="origin-top" style="width: 794px; transform: scale(var(--paper-scale, 1));"
                     x-data="{
                         fit() {
                             const w = this.$el.parentElement.clientWidth;
                             this.$el.style.setProperty('--paper-scale', Math.min(1, w / 794));
                             this.$el.parentElement.style.height = (this.$el.offsetHeight * Math.min(1, w / 794)) + 'px';
                         }
                     }"
                     x-init="fit(); new ResizeObserver(() => fit()).observe($el.parentElement)"
                     wire:ignore.self>
                    @include('invoices.paper', [
                        'invoice' => $inv,
                        'company' => $company,
                        'theme' => $theme,
                        'screen' => true,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
