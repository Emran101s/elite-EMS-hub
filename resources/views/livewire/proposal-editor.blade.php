@php
    $may = auth()->user()?->can('manage-contract') ?? false;
    $p = $proposal;
    $state = $p->state();
    $cur = $p->currencyCode();
    $money = fn ($c) => \App\Support\Money::forDocument($c, $cur);
@endphp

<div class="bg-[radial-gradient(120%_80%_at_100%_0%,rgba(212,175,55,0.06),transparent_45%),radial-gradient(90%_60%_at_0%_100%,rgba(11,31,58,0.045),transparent_40%)] bg-page space-y-4 rounded-[24px]">

    {{-- Soft Command commercial document chrome --}}
    <div class="flex flex-wrap items-center gap-3">
        <a href="{{ route('proposals.index') }}" class="inline-flex rounded-full border border-line px-4 py-2 text-xs font-semibold text-ink transition hover:border-gold-300">← Proposals</a>

        <div class="min-w-0">
            <p class="text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Commercial Command · Offer</p>
            <h1 class="font-bold text-ink truncate text-[20px]">{{ $p->number }}</h1>
            <p class="mt-0.5 truncate text-[11.5px] text-muted">
                {{ $p->client?->name ?: 'No client' }}
                @if ($p->daysLeft() !== null) · {{ $p->daysLeft() < 0 ? abs($p->daysLeft()).'d ago' : $p->daysLeft().'d left' }} @endif
            </p>
        </div>

        <x-eo.status-pill tone="live">{{ $p->stateLabel() }}</x-eo.status-pill>
        <span class="chip-gold">Document workspace</span>

        <div class="ms-auto flex flex-wrap items-center gap-2">
            <a href="{{ route('proposals.pdf', $p) }}"
               class="flex h-9 items-center gap-1.5 rounded-xl border border-line bg-white px-3 text-[12px] font-semibold text-ink shadow-sm transition hover:border-gold-400">
                <x-icon name="document" class="h-3.5 w-3.5 text-muted" /> PDF
            </a>

            @if ($may && $state === 'draft')
                <button type="button" wire:click="send" class="h-9 rounded-full bg-gold-500 px-3.5 text-[12px] font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">
                    Send it
                </button>
                <x-confirm title="Delete this draft?" confirm="Delete" run="$wire.destroyDraft"
                           class="flex h-9 items-center rounded-xl px-3 text-[12px] font-bold text-muted transition hover:bg-danger-soft hover:text-danger-ink">
                    Delete
                </x-confirm>

            @elseif ($may && $state === 'expired')
                <button type="button" wire:click="extend"
                        class="flex h-9 items-center rounded-xl bg-amber-500 px-3.5 text-[12px] font-bold text-white transition hover:bg-amber-600">
                    Extend 30 days
                </button>

            @elseif ($may && $state === 'sent')
                <x-confirm title="Accepted?"
                           body="This wins the deal and opens the event."
                           confirm="Accept" tone="neutral" run="$wire.accept"
                           class="flex h-9 items-center rounded-xl bg-emerald-600 px-3.5 text-[12px] font-bold text-white transition hover:bg-emerald-700">
                    Accepted
                </x-confirm>
                <input type="text" wire:model="reason" placeholder="Reason…"
                       class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-[110px] !rounded-xl text-[11.5px]">
                <button type="button" wire:click="decline"
                        class="flex h-9 items-center rounded-xl px-3 text-[12px] font-bold text-muted transition hover:bg-danger-soft hover:text-danger-ink">
                    Lost
                </button>

            @elseif ($state === 'accepted' && $p->event)
                <a href="{{ route('events.hub', $p->event) }}"
                   class="flex h-9 items-center rounded-xl bg-emerald-50 px-3.5 text-[12px] font-bold text-emerald-700 transition hover:bg-emerald-100">
                    Open the event →
                </a>
            @endif
        </div>
    </div>

    <div class="grid gap-4 xl:grid-cols-[minmax(0,420px)_minmax(0,1fr)]">

        {{-- ══════════ THE FORM ══════════ --}}
        <div class="min-w-0 space-y-3">

            <div class="rounded-lg border border-line bg-white shadow-raise p-5">
                <p class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !mb-3">The offer</p>

                <div class="space-y-2.5">
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Title</span>
                        <input type="text" wire:model.blur="title" wire:change="saveDetails" @disabled(! $may)
                               class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                        @error('title') <p class="mt-1 text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror
                    </label>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Client</span>
                            <select wire:model.live="client_id" wire:change="saveDetails" @disabled(! $may) class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                                <option value="">No client</option>
                                @foreach ($clients as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Contact</span>
                            <select wire:model="contact_id" wire:change="saveDetails" @disabled(! $may || $contacts->isEmpty())
                                    class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                                <option value="">{{ $contacts->isEmpty() ? 'Pick a client first' : 'No contact' }}</option>
                                @foreach ($contacts as $c)
                                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-2">
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Issued</span>
                            <input type="date" wire:model="issued_on" wire:change="saveDetails" @disabled(! $may) class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Valid until</span>
                            <input type="date" wire:model="valid_until" wire:change="saveDetails" @disabled(! $may) class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                        </label>
                    </div>

                    <div class="grid grid-cols-1 gap-2.5 sm:grid-cols-3">
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Currency</span>
                            <input type="text" maxlength="3" wire:model.blur="currency" wire:change="saveDetails" @disabled(! $may)
                                   class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full uppercase text-xs">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Fee %</span>
                            <input type="number" step="0.5" min="0" max="100" wire:model.blur="fee_pct" wire:change="saveDetails" @disabled(! $may)
                                   class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                        </label>
                        <label class="block">
                            <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Tax %</span>
                            <input type="number" step="0.5" min="0" max="100" wire:model.blur="tax_pct" wire:change="saveDetails" @disabled(! $may)
                                   class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                        </label>
                    </div>
                </div>

                {{-- Offered, not assumed: an offer that starts from a deal's
                     value is already priced whole, and adding a fee to that
                     inflates it. Once it is priced from real lines the fee
                     belongs — and this is the reminder that it does. --}}
                @if ($may && ! (float) $p->fee_pct)
                    <button type="button" wire:click="applyHouseFee"
                            class="mt-3 flex w-full items-center gap-2 rounded-xl border border-gold-400/30 bg-gold-50/60 px-3 py-2 text-start transition hover:bg-gold-50">
                        <span class="text-[13px] text-gold-700">⚑</span>
                        <span class="min-w-0 flex-1">
                            <span class="block text-[11.5px] font-bold text-ink">No management fee on this offer</span>
                            <span class="block text-[10.5px] text-muted">Add the house rate — leave it off if the price already includes it.</span>
                        </span>
                        <span class="shrink-0 rounded-lg bg-gold-500 px-2 py-1 text-[10.5px] font-bold text-white">Add it</span>
                    </button>
                @endif
            </div>

            {{-- ── the lines ── --}}
            <div class="rounded-lg border border-line bg-white shadow-raise overflow-hidden">
                <div class="flex items-center gap-2 border-b border-line px-4 py-2.5">
                    <p class="mb-0 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted flex-1">What is being offered</p>
                    @if ($may)
                        <button type="button" wire:click="newLine"
                                class="rounded-lg bg-navy-900 px-2.5 py-1 text-[11px] font-bold text-white transition hover:bg-navy-950">
                            ＋ Add a line
                        </button>
                    @endif
                </div>

                @if ($editingLine !== null)
                    <div class="border-b border-line bg-gold-50/40 p-3">
                        <div class="space-y-2">

                            {{-- The same price list the invoice editor uses, so a
                                 room quoted now and billed in six months comes
                                 from one price. --}}
                            @if ($picked)
                                <div class="rounded-xl border border-gold-400/20 bg-gold-50/50 p-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="min-w-0 flex-1">
                                            <span class="block truncate text-[12px] font-bold text-ink">{{ $picked->name }}</span>
                                            <span class="block truncate text-[10.5px] text-muted">
                                                {{ $picked->currency }} {{ number_format($picked->unit_price_cents / 100, 2) }}
                                                {{ mb_strtolower($picked->unitLabel()) }}
                                            </span>
                                        </span>
                                        <button type="button" wire:click="unpick"
                                                class="shrink-0 rounded-lg px-2 py-1 text-[10.5px] font-bold text-muted transition hover:bg-white hover:text-ink">
                                            Type it instead
                                        </button>
                                    </div>

                                    @if ($picked->factors())
                                        <div class="mt-2 flex flex-wrap items-end gap-2">
                                            @foreach ($picked->factors() as $i => $label)
                                                <label class="w-[92px]">
                                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">{{ $label }}</span>
                                                    <input type="number" step="1" min="0" wire:model.live="factors.{{ $i }}"
                                                           class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-end text-xs">
                                                </label>
                                                @if (! $loop->last)<span class="pb-2 text-[13px] font-bold text-muted">×</span>@endif
                                            @endforeach
                                            <span class="pb-2 text-[11.5px] text-muted">
                                                = {{ $qty }} {{ \Illuminate\Support\Str::plural($picked->unitNoun(), (float) $qty) }}
                                            </span>
                                        </div>
                                    @endif
                                </div>
                            @elseif ($catalogue->isNotEmpty() || $catalogueQuery !== '')
                                <details class="rounded-xl border border-line bg-white" data-menu>
                                    <summary class="flex cursor-pointer list-none items-center gap-2 px-2.5 py-2 text-[11.5px] font-bold text-ink [&::-webkit-details-marker]:hidden">
                                        <x-icon name="archive" class="h-3.5 w-3.5 text-muted" />
                                        Pick from the price list
                                        <span class="ms-auto text-muted">▾</span>
                                    </summary>
                                    <div class="border-t border-line p-2">
                                        {{-- One box across every section — see the invoice editor. --}}
                                        <input type="search" wire:model.live.debounce.250ms="catalogueQuery"
                                               placeholder="Room, transfer, lunch, or a section…" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none mb-1.5 h-8 w-full text-[11px]">
                                        <div class="scrollbar-none max-h-48 overflow-y-auto">
                                            @forelse ($catalogue as $it)
                                                <button type="button" wire:click="pick({{ $it->id }})" wire:key="cat-{{ $it->id }}"
                                                        class="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-start transition hover:bg-page">
                                                    <span class="min-w-0 flex-1">
                                                        <span class="block truncate text-[11.5px] font-semibold text-ink">{{ $it->name }}</span>
                                                        <span class="block truncate text-[10px] text-muted">
                                                            @if ($it->section)
                                                                <span class="font-semibold text-muted">{{ $it->sectionLabel() }}</span> ·
                                                            @endif
                                                            {{ $it->category ?: 'Uncategorised' }} · {{ mb_strtolower($it->unitLabel()) }}
                                                        </span>
                                                    </span>
                                                    <span class="shrink-0 text-[11.5px] font-black tabular-nums text-ink">
                                                        {{ number_format($it->unit_price_cents / 100, 2) }}
                                                    </span>
                                                </button>
                                            @empty
                                                <p class="px-2 py-3 text-center text-[11px] italic text-muted">
                                                    Nothing matches. <a href="{{ route('catalogue.index') }}" class="font-semibold text-gold-700 hover:underline">Add it to the price list</a>.
                                                </p>
                                            @endforelse
                                        </div>
                                    </div>
                                </details>
                            @endif

                            <input type="text" wire:model="description" placeholder="What is being offered"
                                   class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">
                            @error('description') <p class="text-[11px] font-semibold text-danger-ink">{{ $message }}</p> @enderror

                            <input type="text" wire:model="detail" placeholder="What it includes — shown under the line"
                                   class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-xs">

                            <div class="flex flex-wrap gap-2">
                                <label class="w-20 sm:w-[84px]">
                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Qty</span>
                                    <input type="number" step="0.01" min="0" wire:model="qty" @readonly((bool) $picked)
                                           class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-end text-xs {{ $picked ? 'bg-page text-muted' : '' }}">
                                </label>
                                <label class="min-w-[130px] flex-1">
                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Unit price</span>
                                    <input type="number" step="0.01" wire:model="unit" placeholder="0.00" class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none h-9 w-full text-end text-xs">
                                </label>
                                <label class="w-24 sm:w-[110px]">
                                    <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Amount</span>
                                    <span class="flex h-9 items-center justify-end rounded-xl bg-white px-2.5 text-[13px] font-black tabular-nums text-ink ring-1 ring-line">
                                        {{ number_format((float) ($qty ?: 0) * (float) ($unit ?: 0), 2) }}
                                    </span>
                                </label>
                            </div>

                            {{-- Optional means quoted and not counted — in the
                                 offer so the client can say yes, out of the
                                 total so the headline price is the one they are
                                 being asked to agree to. --}}
                            <label class="flex cursor-pointer items-center gap-2 text-[11.5px] font-semibold text-ink">
                                <input type="checkbox" wire:model="optional" class="h-3.5 w-3.5 rounded border-line">
                                An optional extra — quoted, and left out of the total
                            </label>

                            <div class="flex gap-2 pt-0.5">
                                <button type="button" wire:click="saveLine"
                                        class="rounded-lg bg-navy-900 px-3 py-1.5 text-[11.5px] font-bold text-white transition hover:bg-navy-950">
                                    {{ $editingLine ? 'Save the line' : 'Add it' }}
                                </button>
                                <button type="button" wire:click="cancelLine"
                                        class="rounded-lg px-2.5 py-1.5 text-[11.5px] font-bold text-muted transition hover:text-ink">Cancel</button>
                                @if ($editingLine)
                                    <button type="button" wire:click="deleteLine({{ $editingLine }})"
                                            class="ms-auto rounded-lg px-2.5 py-1.5 text-[11.5px] font-bold text-muted transition hover:bg-danger-soft hover:text-danger-ink">
                                        Remove
                                    </button>
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                @forelse ($p->lines as $line)
                    <div wire:key="line-{{ $line->id }}"
                         class="flex items-center gap-2 border-b border-line/60 px-3 py-2 last:border-0 hover:bg-page/30 {{ $line->optional ? 'bg-amber-50/40' : '' }}">

                        @if ($may)
                            <span class="flex shrink-0 flex-col leading-none">
                                <button type="button" wire:click="moveLine({{ $line->id }}, -1)" @disabled($loop->first)
                                        class="text-[9px] text-muted transition hover:text-ink disabled:opacity-25">▲</button>
                                <button type="button" wire:click="moveLine({{ $line->id }}, 1)" @disabled($loop->last)
                                        class="text-[9px] text-muted transition hover:text-ink disabled:opacity-25">▼</button>
                            </span>
                        @endif

                        <button type="button" @if ($may) wire:click="editLine({{ $line->id }})" @endif class="min-w-0 flex-1 text-start">
                            <span class="block truncate text-[12.5px] font-semibold text-ink">
                                {{ $line->description }}
                                @if ($line->optional)
                                    <span class="ms-1 rounded bg-amber-100 px-1 py-0.5 text-[9px] font-bold uppercase tracking-wide text-amber-800">Optional</span>
                                @endif
                            </span>
                            <span class="block truncate text-[10.5px] text-muted">
                                {{ rtrim(rtrim(number_format($line->qty, 2), '0'), '.') }} × {{ number_format($line->unit_cents / 100, 2) }}
                                @if ($line->detail) · {{ $line->detail }} @endif
                            </span>
                        </button>

                        @if ($may)
                            <button type="button" wire:click="toggleOptional({{ $line->id }})"
                                    title="{{ $line->optional ? 'Count it in the total' : 'Quote it without counting it' }}"
                                    class="shrink-0 rounded-lg px-1.5 py-1 text-[10px] font-bold text-muted transition hover:bg-page hover:text-ink">
                                {{ $line->optional ? '＋' : '−' }}
                            </button>
                        @endif

                        <span class="shrink-0 text-[13px] font-black tabular-nums {{ $line->optional ? 'text-amber-700' : 'text-ink' }}">
                            {{ number_format($line->amountCents() / 100, 2) }}
                        </span>
                    </div>
                @empty
                    <p class="px-4 py-6 text-center text-[12px] italic text-muted">
                        Nothing quoted yet. Add a line to start.
                    </p>
                @endforelse

                {{-- ── totals ── --}}
                <div class="space-y-1 border-t border-line bg-page/50 px-4 py-3 text-xs">
                    <div class="flex justify-between"><span class="text-muted">Subtotal</span>
                        <span class="font-bold text-ink">{{ $money($p->subtotalCents()) }}</span></div>
                    @if ($p->fee_pct)
                        <div class="flex justify-between"><span class="text-muted">Management fee ({{ rtrim(rtrim(number_format($p->fee_pct, 2), '0'), '.') }}%)</span>
                            <span class="font-bold text-gold-700">{{ $money($p->feeCents()) }}</span></div>
                    @endif
                    @if ($p->tax_pct)
                        <div class="flex justify-between"><span class="text-muted">Tax ({{ rtrim(rtrim(number_format($p->tax_pct, 2), '0'), '.') }}%)</span>
                            <span class="font-bold text-ink">{{ $money($p->taxCents()) }}</span></div>
                    @endif
                    <div class="flex justify-between border-t border-line pt-1.5">
                        <span class="text-eyebrow font-bold uppercase tracking-wide text-ink">Total</span>
                        <span class="text-[15px] font-black text-ink">{{ $money($p->totalCents()) }}</span></div>
                    @if ($p->optionalCents())
                        <div class="flex justify-between"><span class="text-muted">Optional extras, quoted</span>
                            <span class="font-bold text-amber-700">{{ $money($p->optionalCents()) }}</span></div>
                    @endif
                </div>
            </div>

            {{-- ── words ── --}}
            <div class="rounded-lg border border-line bg-white shadow-raise p-5">
                <p class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !mb-3">Covering note &amp; terms</p>
                <div class="space-y-2.5">
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Summary</span>
                        <textarea wire:model.blur="summary" wire:change="saveDetails" @disabled(! $may) rows="4"
                                  placeholder="Thank you for the opportunity to propose on…"
                                  class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none w-full text-xs"></textarea>
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-eyebrow font-bold uppercase tracking-wide text-muted">Terms</span>
                        <textarea wire:model.blur="terms" wire:change="saveDetails" @disabled(! $may) rows="3"
                                  placeholder="Valid for 30 days. A 15% deposit confirms the booking."
                                  class="w-full rounded-lg border border-line bg-white px-3 py-2 text-[13px] text-ink placeholder:text-muted focus:border-navy-300 focus:outline-none w-full text-xs"></textarea>
                    </label>
                </div>
            </div>
        </div>

        {{-- ══════════ THE PAPER ══════════ --}}
        <div class="min-w-0 xl:sticky xl:top-4 xl:self-start">
            <p class="mb-1.5 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted !mb-2">What the client receives</p>
            <div class="overflow-hidden rounded-2xl border border-line bg-white shadow-float">
                <div class="origin-top" style="width: 794px; transform: scale(var(--paper-scale, 1));"
                     x-data="{
                         fit() {
                             const w = this.$el.parentElement.clientWidth;
                             const s = Math.min(1, w / 794);
                             this.$el.style.setProperty('--paper-scale', s);
                             this.$el.parentElement.style.height = (this.$el.offsetHeight * s) + 'px';
                         }
                     }"
                     x-init="fit(); new ResizeObserver(() => fit()).observe($el.parentElement)"
                     wire:ignore.self>
                    @include('proposals.paper', [
                        'proposal' => $p,
                        'company' => $company,
                        'theme' => $theme,
                        'screen' => true,
                    ])
                </div>
            </div>
        </div>
    </div>
</div>
