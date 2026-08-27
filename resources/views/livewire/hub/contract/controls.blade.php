            {{-- ══════════ LEFT · the controls, as collapsible modules ══════════ --}}
            <x-accordion>

                @if ($type !== 'client')
                    {{-- Document module --}}
                    <x-accordion-section id="document" num="01" title="Document" summary="Title, counterparty and language">
                        <div class="space-y-3">
                            <input type="text" wire:model.live.debounce.500ms="title" placeholder="{{ $tLabel }}" class="{{ $in }} !text-base !font-bold">
                            @php $cp = $data['counterparty'] ?? []; @endphp

                            @if (in_array($type, ['vendor', 'speaker', 'sponsorship'], true))
                                {{-- Pick the counterparty from the event's own list — the
                                     name, contact, fee and topic flow into the agreement. --}}
                                <div>
                                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">
                                        {{ ['vendor' => 'Supplier', 'speaker' => 'Speaker', 'sponsorship' => 'Sponsor'][$type] }} — from the event's list
                                    </label>
                                    <select wire:change="setParty($event.target.value)" class="h-10 w-full rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                                        <option value="">— choose —</option>
                                        @foreach ($editPartyOptions as $opt)
                                            <option value="{{ $opt->id }}" @selected($contract->party_id === $opt->id)>{{ $opt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($type === 'letter')
                                <div>
                                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Recipient</label>
                                    <input type="text" wire:model.live.debounce.500ms="data.counterparty.name_en"
                                           placeholder="HE the Minister of Culture…" class="{{ $in }}">
                                </div>
                            @endif

                            {{-- type-specific details that print into the agreement --}}
                            @if ($type === 'speaker')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Session / topic</label>
                                        <input type="text" wire:model.live.debounce.500ms="data.counterparty.detail" placeholder="Keynote — AI in Events" class="{{ $in }}">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Honorarium ({{ $cur }})</label>
                                        <input type="number" min="0" step="0.01" value="{{ ($cp['fee_cents'] ?? null) !== null ? number_format(($cp['fee_cents'] ?? 0) / 100, 2, '.', '') : '' }}"
                                               wire:change="setPartyFee($event.target.value)" placeholder="—" class="{{ $in }} text-right">
                                    </div>
                                </div>
                            @elseif ($type === 'sponsorship')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Package / tier</label>
                                        <input type="text" wire:model.live.debounce.500ms="data.counterparty.package" placeholder="Gold" class="{{ $in }}">
                                    </div>
                                    <div>
                                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Sponsorship amount ({{ $cur }})</label>
                                        <input type="number" min="0" step="0.01" value="{{ ($cp['fee_cents'] ?? null) !== null ? number_format(($cp['fee_cents'] ?? 0) / 100, 2, '.', '') : '' }}"
                                               wire:change="setPartyFee($event.target.value)" placeholder="—" class="{{ $in }} text-right">
                                    </div>
                                </div>
                            @elseif ($type === 'vendor')
                                <div>
                                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Service category</label>
                                    <input type="text" wire:model.live.debounce.500ms="data.counterparty.detail" placeholder="production, catering, transport…" class="{{ $in }}">
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center gap-2 border-t border-line pt-2.5">
                                <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">Language</span>
                                @foreach (['en' => 'English', 'bilingual' => 'Bilingual EN/AR'] as $lk => $ll)
                                    <button type="button" wire:click="setLanguage('{{ $lk }}')"
                                            @class(['rounded-lg px-2.5 py-1 text-eyebrow font-bold transition', 'bg-navy-900 text-white' => $language === $lk, 'bg-page text-muted hover:bg-page' => $language !== $lk])>{{ $ll }}</button>
                                @endforeach

                                @can('manage-contract')
                                    <x-confirm title="Rewrite the body from the standard {{ strtolower($tLabel) }} template with the current details?"
                                               body="Your edits to the body will be replaced."
                                               confirm="Rewrite" run="$wire.refillFromTemplate"
                                               class="ml-auto text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-gold-700">↻ Refill body</x-confirm>
                                @endcan
                            </div>
                        </div>
                    </x-accordion-section>
                @endif

                @if ($type === 'client')
                    {{-- Parties module --}}
                    @php
                        // A share divides a cost between funders. With one Client
                        // there is nothing to divide, so the column, the total and
                        // the "must equal 100%" rule all disappear rather than
                        // demanding a number that means nothing.
                        $split = \App\Support\ContractClauses::sharesApply($data);
                    @endphp
                    <x-accordion-section id="parties" num="01" title="Parties"
                                         summary="{{ $split ? 'The client entities and their shares' : 'Who the client is' }}">
                        <div class="space-y-2.5">
                            @php $shareTotal = collect($data['second_parties'] ?? [])->sum(fn ($p) => (float) ($p['share'] ?? 0)); @endphp
                            @foreach ($data['second_parties'] ?? [] as $i => $sp)
                                <div wire:key="sp-{{ $i }}" class="group/sp space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <p class="text-eyebrow font-bold uppercase tracking-wide text-muted">{{ $split ? 'Second party '.($i + 1) : 'Second party' }}</p>
                                        @if (count($data['second_parties']) > 1)
                                            <button type="button" wire:click="removeSecondParty({{ $i }})"
                                                    class="text-eyebrow font-bold uppercase tracking-wide text-muted opacity-100 transition sm:opacity-0 hover:text-red-700 sm:group-hover/sp:opacity-100">Remove</button>
                                        @endif
                                    </div>
                                    <div class="grid gap-1.5 {{ $split ? 'grid-cols-[1fr_5rem]' : 'grid-cols-1' }}">
                                        <input type="text" wire:model.live.debounce.500ms="data.second_parties.{{ $i }}.name_en" placeholder="Entity name (English)" class="{{ $in }}">
                                        @if ($split)
                                            <div class="relative">
                                                <input type="number" min="0" max="100" wire:model.live.debounce.300ms="data.second_parties.{{ $i }}.share" class="{{ $in }} pr-6 text-center !font-bold">
                                                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-eyebrow font-semibold text-muted">%</span>
                                            </div>
                                        @endif
                                    </div>
                                    <input type="text" dir="rtl" wire:model.live.debounce.500ms="data.second_parties.{{ $i }}.name_ar" placeholder="اسم الجهة بالعربية" class="{{ $inAr }}">

                                    {{-- The recital names the Client from the English
                                         field, and the English text is the controlling
                                         one. Without it the row cannot be bound, so it
                                         is told rather than silently dropped. --}}
                                    @if (trim((string) ($sp['name_en'] ?? '')) === '')
                                        <p class="text-eyebrow text-muted">
                                            No English name — this row will not appear on the document.
                                            @if (trim((string) ($sp['name_ar'] ?? '')) !== '')
                                                Add one, or remove the row.
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between border-t border-line pt-2.5">
                                @if ($split)
                                    @php $shareOk = abs($shareTotal - 100) < 0.001; @endphp
                                    <span class="text-micro font-bold {{ $shareOk ? 'text-emerald-600' : 'text-danger-ink' }}">
                                        {{ rtrim(rtrim(number_format($shareTotal, 2), '0'), '.') }}%
                                        @unless ($shareOk)<span class="font-semibold"> — must equal 100%</span>@endunless
                                    </span>
                                @else
                                    <span class="text-micro text-muted">One client — no cost split, so no shares.</span>
                                @endif
                                <button type="button" wire:click="addSecondParty" class="btn-ghost btn-xs">＋ Add party</button>
                            </div>
                        </div>
                    </x-accordion-section>

                    {{-- Value & schedule module --}}
                    <x-accordion-section id="value-and-payments" num="02" title="Value & Payments" summary="{{ $fmt($est) }} · {{ count($f['payment_schedule'] ?? []) }} installments">
                        <div class="space-y-3">
                            <div class="rounded-xl bg-gold-50/40 p-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-eyebrow font-bold text-muted">{{ $cur }}</span>
                                    <input type="number" min="0" step="0.001" value="{{ number_format($est / 100, 3, '.', '') }}"
                                           wire:change="setContractValue($event.target.value)"
                                           class="{{ $in }} flex-1 !bg-white text-right !text-base !font-black">
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <div class="flex rounded-lg border border-line bg-white p-0.5">
                                        @foreach (['fixed' => 'Fixed price', 'estimate' => 'Estimate'] as $mode => $ml)
                                            <button type="button" wire:click="setValueMode('{{ $mode }}')"
                                                    @class(['rounded-md px-2 py-1 text-eyebrow font-bold transition', 'bg-navy-900 text-white' => ($f['value_mode'] ?? 'fixed') === $mode, 'text-muted hover:text-ink' => ($f['value_mode'] ?? 'fixed') !== $mode])>{{ $ml }}</button>
                                        @endforeach
                                    </div>
                                    {{-- The figure is on the button, so a pull is never a
                                         mystery: you can see what is coming before it comes. --}}
                                    @php $fromBudget = $event->costForecast(); @endphp
                                    <x-confirm title="Copy {{ $fromBudget['forecast'] ? $event->money($fromBudget['forecast']) : 'the budget' }} into the contract value?"
                                               body="This overwrites the figure once — it does not link them."
                                               confirm="Copy" tone="neutral" run="$wire.syncBudget"
                                               class="text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-gold-700">
                                        ↻ From budget @if ($fromBudget['forecast'])· {{ $event->money($fromBudget['forecast']) }}@endif
                                    </x-confirm>
                                </div>

                                @if ($budgetFlash)
                                    <p class="mt-1.5 text-[11px] font-semibold text-muted">{{ $budgetFlash }}</p>
                                @endif

                                <p class="mt-1.5 font-mono text-eyebrow text-muted">
                                    Quoted live in the body wherever a clause reads <b>&#123;&#123;value&#125;&#125;</b> — change it here and every one of them follows, no regeneration needed.
                                </p>
                            </div>

                            @php $totalPct = collect($f['payment_schedule'] ?? [])->sum(fn ($s) => (float) ($s['pct'] ?? 0)); @endphp
                            @foreach ($f['payment_schedule'] ?? [] as $i => $s)
                                <div wire:key="inst-{{ $i }}" class="group/inst space-y-1.5 border-t border-line/70 pt-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-white">{{ $i + 1 }}</span>
                                        <div class="relative w-20 shrink-0">
                                            <input type="number" min="0" max="100" step="0.01" wire:model.live.debounce.300ms="data.financials.payment_schedule.{{ $i }}.pct" class="{{ $in }} pr-6 text-center !font-bold">
                                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-eyebrow font-semibold text-muted">%</span>
                                        </div>
                                        <span class="flex-1 text-right text-micro font-semibold text-muted">{{ $fmt($est * (float) ($s['pct'] ?? 0) / 100) }}</span>
                                        @if (count($f['payment_schedule']) > 1)
                                            <button type="button" wire:click="removeInstallment({{ $i }})"
                                                    class="shrink-0 rounded-md px-1.5 py-1 text-eyebrow font-bold text-muted opacity-100 transition sm:opacity-0 hover:bg-danger-soft hover:text-red-700 sm:group-hover/inst:opacity-100">✕</button>
                                        @endif
                                    </div>
                                    <div class="grid gap-1.5 sm:grid-cols-2">
                                        <input type="text" wire:model.live.debounce.500ms="data.financials.payment_schedule.{{ $i }}.when_en" placeholder="Due — English" class="{{ $in }} !py-1.5 !text-xs">
                                        <input type="text" dir="rtl" wire:model.live.debounce.500ms="data.financials.payment_schedule.{{ $i }}.when_ar" placeholder="الاستحقاق — بالعربية" class="{{ $inAr }} !py-1.5 !text-xs">
                                    </div>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between border-t border-line pt-2.5">
                                <span class="text-micro font-bold {{ abs($totalPct - 100) < 0.001 ? 'text-emerald-600' : 'text-danger-ink' }}">Total {{ rtrim(rtrim(number_format($totalPct, 2), '0'), '.') }}%</span>
                                <span class="flex gap-2">
                                    <button type="button" wire:click="balanceInstallments" class="btn-ghost btn-xs" title="Spread 100% evenly">⚖ Balance</button>
                                    <button type="button" wire:click="addInstallment" class="btn-ghost btn-xs">＋ Add installment</button>
                                </span>
                            </div>
                        </div>
                    </x-accordion-section>

                    {{-- Assumptions module --}}
                    <x-accordion-section id="budget-assumptions" num="03" title="Budget Assumptions" summary="What the estimate is based on">
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            <div><label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Attendees — from</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.attendees_min" class="{{ $in }}"></div>
                            <div><label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Attendees — to</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.attendees_max" class="{{ $in }}"></div>
                            <div><label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Rooms</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.rooms" class="{{ $in }}"></div>
                            <div><label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Nights per guest</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.nights" class="{{ $in }}"></div>
                            <div class="sm:col-span-2"><label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Catering (English)</label><input type="text" wire:model.live.debounce.500ms="data.assumptions.catering_en" class="{{ $in }}"></div>
                        </div>
                    </x-accordion-section>
                @endif

                {{-- Body module --}}
                <x-accordion-section id="contract-body" num="{{ $type === 'client' ? '04' : '02' }}"
                                     title="{{ $editingAx ? 'Appendix '.$axNumber.' · '.($editingAx['title_en'] ?: 'Untitled') : 'Contract Body' }}"
                                     summary="{{ count($editBlocks) }} {{ $editingAx ? 'sections' : 'clauses' }} · every one editable">
                    <div class="space-y-3">
                        {{-- One editor, retargeted. The breadcrumb is the only way
                             to tell which set you are typing into, so it is never
                             hidden while an appendix is open. --}}
                        @if ($editingAx)
                            <div class="flex items-center gap-2 rounded-xl bg-gold-50/60 px-3 py-2 text-eyebrow">
                                <button type="button" wire:click="editAppendix(null)" class="font-bold text-gold-800 hover:underline">Body</button>
                                <span class="text-muted">/</span>
                                <span class="font-bold text-ink">Appendix {{ $axNumber }} · {{ $editingAx['title_en'] ?: 'Untitled' }}</span>
                                <button type="button" wire:click="editAppendix(null)" class="ms-auto btn-ghost btn-xs">← Back to the body</button>
                            </div>
                        @endif

                        @can('manage-contract')
                            <div class="flex items-center justify-end gap-2">
                                @unless ($editingAx)
                                    <x-confirm title="Restore the standard clause set?"
                                               body="Any edits to the body will be replaced."
                                               confirm="Restore" tone="warn" run="$wire.restoreStandardBlocks"
                                               class="btn-ghost btn-xs">↺ Restore standard</x-confirm>
                                @endunless
                                <button type="button" wire:click="addBlock" class="btn-gold btn-xs">＋ Add section</button>
                            </div>
                        @endcan

                        @forelse ($editBlocks as $bi => $b)
                            <div wire:key="blk-{{ $b['id'] }}" class="rounded-2xl bg-page/50 p-3">
                                <div class="flex items-start gap-2.5">
                                    <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-white">{{ $bi + 1 }}</span>
                                    <div class="grid min-w-0 flex-1 gap-1.5">
                                        <label class="block">
                                            <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-page px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-muted">EN</span>
                                            <input type="text" value="{{ $b['title_en'] ?? '' }}" placeholder="Clause title (English)"
                                                   wire:change="updateBlockField('{{ $b['id'] }}', 'title_en', $event.target.value)"
                                                   class="{{ $in }} !bg-white !py-1.5 !text-xs !font-bold">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-gold-50 px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-gold-800">AR</span>
                                            <input type="text" dir="rtl" value="{{ $b['title_ar'] ?? '' }}" placeholder="عنوان البند (بالعربية)"
                                                   wire:change="updateBlockField('{{ $b['id'] }}', 'title_ar', $event.target.value)"
                                                   class="{{ $inAr }} !bg-white !py-1.5 !text-xs !font-bold ring-1 ring-gold-300/40">
                                        </label>
                                    </div>
                                    @can('manage-contract')
                                        <div class="flex shrink-0 flex-col items-center gap-0.5">
                                            <button type="button" wire:click="moveBlock('{{ $b['id'] }}', -1)" @disabled($bi === 0)
                                                    class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-page hover:text-ink disabled:opacity-25" title="Move up">↑</button>
                                            <button type="button" wire:click="moveBlock('{{ $b['id'] }}', 1)" @disabled($bi === count($data['blocks']) - 1)
                                                    class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-page hover:text-ink disabled:opacity-25" title="Move down">↓</button>
                                            <x-confirm title="Delete “{{ $b['title_en'] ?? 'this clause' }}” from this contract?"
                                                       confirm="Delete" run="$wire.deleteBlock('{{ $b['id'] }}')"
                                                       class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-muted hover:bg-danger-soft hover:text-red-700">✕</x-confirm>
                                        </div>
                                    @endcan
                                </div>

                                <div class="mt-2 space-y-2">
                                    @foreach ($b['en'] ?? [] as $p => $para)
                                        <div class="group/para grid gap-1.5">
                                            <div>
                                                <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-page px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-muted">EN</span>
                                                <textarea rows="2" placeholder="English text…"
                                                          wire:change="updateParagraph('{{ $b['id'] }}', 'en', {{ $p }}, $event.target.value)"
                                                          class="{{ $in }} !bg-white !py-2 !text-xs leading-relaxed">{{ $para }}</textarea>
                                            </div>
                                            <div class="relative">
                                                <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-gold-50 px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-gold-800">AR</span>
                                                <textarea rows="2" dir="rtl" placeholder="النص بالعربية…"
                                                          wire:change="updateParagraph('{{ $b['id'] }}', 'ar', {{ $p }}, $event.target.value)"
                                                          class="{{ $inAr }} !bg-white !py-2 !text-xs leading-relaxed ring-1 ring-gold-300/40">{{ $b['ar'][$p] ?? '' }}</textarea>
                                                @if (count($b['en']) > 1)
                                                    <button type="button" wire:click="removeParagraph('{{ $b['id'] }}', {{ $p }})"
                                                            class="absolute -right-1 -top-1 hidden h-5 w-5 items-center justify-center rounded-full bg-white text-eyebrow font-bold text-muted shadow ring-1 ring-line hover:text-red-700 group-hover/para:flex"
                                                            title="Remove this paragraph pair">✕</button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    <button type="button" wire:click="addParagraph('{{ $b['id'] }}')"
                                            class="text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-gold-700">＋ Add paragraph</button>
                                </div>

                                @if (in_array($b['type'] ?? '', ['bullets', 'list'], true))
                                    <div class="mt-2.5 space-y-1.5 border-t border-line pt-2.5">
                                        <p class="text-eyebrow font-bold uppercase tracking-wide text-muted">{{ ($b['type'] === 'bullets') ? 'Items' : 'Policy rows' }} · {{ count($b['items'] ?? []) }}</p>
                                        @foreach ($b['items'] ?? [] as $r => $it)
                                            <div wire:key="itm-{{ $b['id'] }}-{{ $r }}" class="group/itm rounded-xl bg-white p-2 ring-1 ring-line">
                                                <div class="grid gap-1.5 sm:grid-cols-2">
                                                    <input type="text" value="{{ $it['l_en'] ?? '' }}" placeholder="Label (English)"
                                                           wire:change="updateItem('{{ $b['id'] }}', {{ $r }}, 'l_en', $event.target.value)" class="{{ $in }} !py-1.5 !text-xs !font-semibold">
                                                    <input type="text" dir="rtl" value="{{ $it['l_ar'] ?? '' }}" placeholder="العنوان بالعربية"
                                                           wire:change="updateItem('{{ $b['id'] }}', {{ $r }}, 'l_ar', $event.target.value)" class="{{ $inAr }} !py-1.5 !text-xs !font-semibold">
                                                    <input type="text" value="{{ $it['t_en'] ?? '' }}" placeholder="Description (English) — optional"
                                                           wire:change="updateItem('{{ $b['id'] }}', {{ $r }}, 't_en', $event.target.value)" class="{{ $in }} !py-1.5 !text-xs">
                                                    <input type="text" dir="rtl" value="{{ $it['t_ar'] ?? '' }}" placeholder="الوصف بالعربية — اختياري"
                                                           wire:change="updateItem('{{ $b['id'] }}', {{ $r }}, 't_ar', $event.target.value)" class="{{ $inAr }} !py-1.5 !text-xs">
                                                </div>
                                                <button type="button" wire:click="removeItem('{{ $b['id'] }}', {{ $r }})"
                                                        class="mt-1 text-eyebrow font-bold uppercase tracking-wide text-muted opacity-100 transition sm:opacity-0 hover:text-red-700 sm:group-hover/itm:opacity-100">Remove row</button>
                                            </div>
                                        @endforeach
                                        <button type="button" wire:click="addItem('{{ $b['id'] }}')"
                                                class="text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-gold-700">＋ Add item</button>
                                    </div>
                                @elseif (($b['type'] ?? '') !== 'prose')
                                    <p class="mt-2 rounded-lg bg-page/70 px-2.5 py-1.5 text-eyebrow text-muted">
                                        @switch($b['type'])
                                            @case('costshare') Followed by the cost-share table — edit the entities in <b>Parties</b>. @break
                                            @case('schedule') Followed by the payment table — edit it in <b>Value &amp; Payments</b>. @break
                                        @endswitch
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-line px-4 py-8 text-center text-xs text-muted">
                                {{ $editingAx ? 'This appendix is empty — add a section, or pull it from its module.' : 'No clauses yet — add a section or restore the standard set.' }}
                            </p>
                        @endforelse
                    </div>
                </x-accordion-section>

                {{-- ══ THE ANNEXES ══
                     What the contract carries behind its signatures. The list
                     lives here; editing one retargets the panel above rather
                     than nesting a second editor inside this one. --}}
                @if ($type === 'client')
                    <x-accordion-section id="appendices" num="05" title="Appendices"
                                         summary="{{ count($axList) }} {{ count($axList) === 1 ? 'appendix' : 'appendices' }}{{ count($axList) ? ' · '.collect($axList)->pluck('title_en')->implode(', ') : '' }}">
                        <div class="space-y-3">
                            @if ($broken)
                                <p class="rounded-xl bg-danger-soft px-3 py-2 text-eyebrow font-bold text-red-700">
                                    ⚠ The text refers to {{ implode(', ', $broken) }}, which no longer exists. Fix the reference or restore the appendix — the PDF will not export until you do.
                                </p>
                            @endif

                            @forelse ($axList as $ai => $ax)
                                <div wire:key="ax-{{ $ax['slug'] }}"
                                     @class(['rounded-2xl p-3', 'bg-gold-50/60 ring-1 ring-gold-300/50' => $editingAppendix === $ax['slug'], 'bg-page/50' => $editingAppendix !== $ax['slug']])>
                                    <div class="flex items-start gap-2.5">
                                        <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-white">{{ $ai + 1 }}</span>
                                        <div class="grid min-w-0 flex-1 gap-1.5">
                                            <input type="text" value="{{ $ax['title_en'] ?? '' }}" placeholder="Appendix title (English)"
                                                   wire:change="updateAppendixField('{{ $ax['slug'] }}', 'title_en', $event.target.value)"
                                                   class="{{ $in }} !py-1.5 !text-sm !font-bold">
                                            <input type="text" dir="rtl" value="{{ $ax['title_ar'] ?? '' }}" placeholder="عنوان الملحق"
                                                   wire:change="updateAppendixField('{{ $ax['slug'] }}', 'title_ar', $event.target.value)"
                                                   class="{{ $inAr }} !py-1.5 !text-xs">
                                        </div>
                                        @can('manage-contract')
                                            <div class="flex shrink-0 flex-col gap-0.5">
                                                <button type="button" wire:click="moveAppendix('{{ $ax['slug'] }}', -1)" @disabled($ai === 0) class="btn-ghost btn-xs disabled:opacity-25">↑</button>
                                                <button type="button" wire:click="moveAppendix('{{ $ax['slug'] }}', 1)" @disabled($ai === count($axList) - 1) class="btn-ghost btn-xs disabled:opacity-25">↓</button>
                                                <x-confirm title="Remove this appendix?"
                                                           body="Any reference to it in the contract text will break until you fix it."
                                                           confirm="Remove" run="$wire.deleteAppendix('{{ $ax['slug'] }}')"
                                                           class="btn-ghost btn-xs text-danger-ink">✕</x-confirm>
                                            </div>
                                        @endcan
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-md bg-white px-2 py-0.5 text-eyebrow font-bold text-muted ring-1 ring-line">
                                            {{ count($ax['blocks'] ?? []) }} {{ \Illuminate\Support\Str::plural('section', count($ax['blocks'] ?? [])) }}
                                        </span>
                                        @if ($ax['source'] ?? null)
                                            <span class="rounded-md bg-navy-900 px-2 py-0.5 text-eyebrow font-bold text-white/70">
                                                {{ ['budget' => 'Budget', 'agenda' => 'Agenda', 'venue' => 'Venue', 'brief' => 'Brief', 'form' => 'Form', 'typed' => 'Typed'][$ax['source']] ?? $ax['source'] }}
                                            </span>
                                        @endif
                                        @if ($ax['pulled_at'] ?? null)
                                            <span class="text-eyebrow text-muted">pulled {{ \Illuminate\Support\Carbon::parse($ax['pulled_at'])->diffForHumans() }}</span>
                                        @endif

                                        <span class="ms-auto flex gap-1.5">
                                            @if (($ax['source'] ?? null) && ! in_array($ax['source'], ['typed', 'form'], true))
                                                @can('manage-contract')
                                                    <x-confirm title="Replace this appendix with a fresh snapshot from the module?"
                                                               body="Anything typed here will be lost."
                                                               confirm="Replace" run="$wire.pullAppendix('{{ $ax['slug'] }}')"
                                                               class="btn-ghost btn-xs">⇣ {{ ($ax['pulled_at'] ?? null) ? 'Refresh' : 'Pull' }}</x-confirm>
                                                @endcan
                                            @endif
                                            <button type="button" wire:click="editAppendix('{{ $ax['slug'] }}')" class="btn-ghost btn-xs">Open →</button>
                                        </span>
                                    </div>

                                    <p class="mt-1.5 font-mono text-eyebrow text-muted">
                                        Refer to it in the text as <b>&#123;&#123;appendix:{{ $ax['slug'] }}&#125;&#125;</b> — never as “Appendix {{ $ai + 1 }}”.
                                    </p>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-line px-4 py-8 text-center text-xs text-muted">
                                    No appendices. The scope, the budget and the programme all belong here.
                                </p>
                            @endforelse

                            @can('manage-contract')
                                <div class="rounded-2xl border border-dashed border-line p-3">
                                    <p class="eyebrow mb-2">Add an appendix</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach (\App\Support\ContractAppendices::LIBRARY as $key => [$label, $labelAr, $src])
                                            <button type="button" wire:click="addAppendix('{{ $key }}')" class="btn-ghost btn-xs">＋ {{ $label }}</button>
                                        @endforeach
                                    </div>
                                </div>
                            @endcan
                        </div>
                    </x-accordion-section>
                @endif

                {{-- Signatories module --}}
                <x-accordion-section id="signatories" num="{{ $type === 'client' ? '06' : '03' }}" title="Signatories" summary="{{ $signatories->whereNotNull('signed_at')->count() }} of {{ $signatories->count() }} signed">
                    <div class="space-y-2">
                        @forelse ($signatories as $s)
                            <div wire:key="sig-{{ $s->id }}"
                                 @class(['group flex flex-wrap items-center gap-2 rounded-xl px-3 py-2.5', 'bg-emerald-50/60' => $s->isSigned(), 'bg-page/50' => ! $s->isSigned()])>
                                <span @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-black', 'bg-gold-500 text-ink' => $s->isSigned(), 'bg-white text-muted ring-1 ring-line' => ! $s->isSigned()])>{{ $s->initials() }}</span>
                                <div class="min-w-[9rem] flex-1">
                                    <input type="text" value="{{ $s->name }}" placeholder="Signatory name" @disabled($s->isSigned())
                                           wire:change="updateSignatory({{ $s->id }}, 'name', $event.target.value)"
                                           class="w-full rounded-lg border border-transparent bg-transparent px-1.5 py-0.5 text-sm font-bold text-ink hover:border-line focus:border-navy-300 focus:bg-white focus:outline-none disabled:opacity-70">
                                    <div class="flex items-center gap-2 px-1.5">
                                        <select wire:change="updateSignatory({{ $s->id }}, 'role', $event.target.value)" @disabled($s->isSigned())
                                                class="border-0 bg-transparent p-0 text-eyebrow font-semibold text-muted focus:ring-0 disabled:opacity-70">
                                            @foreach (\App\Support\Taxonomy::options('signatory_role') as $rk => $rl)<option value="{{ $rk }}" @selected($s->role === $rk)>{{ $rl }}</option>@endforeach
                                        </select>
                                        @if ($s->isSigned())
                                            <span class="text-eyebrow font-semibold text-emerald-700">✓ {{ $s->signed_at->format('j M Y · H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @can('manage-contract')
                                    @if ($s->isSigned())
                                        <button type="button" wire:click="unsign({{ $s->id }})" class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-muted hover:text-ink" title="Undo this signature">↺ Unsign</button>
                                    @else
                                        <x-confirm title="Record {{ $s->name ?: 'this party' }} as having signed?"
                                                   body="This stamps the date and locks their name to the current document."
                                                   confirm="Record" tone="neutral" run="$wire.recordSignature({{ $s->id }})"
                                                   :disabled="! $s->name"
                                                   class="rounded-lg bg-navy-900 px-3 py-1.5 text-eyebrow font-bold text-white transition hover:bg-navy-800 disabled:opacity-40">✒️ Mark signed</x-confirm>
                                        <button type="button" wire:click="removeSignatory({{ $s->id }})"
                                                class="rounded-lg px-1.5 py-1.5 text-eyebrow font-bold text-muted opacity-100 transition sm:opacity-0 hover:text-red-700 sm:group-hover:opacity-100" title="Remove signatory">✕</button>
                                    @endif
                                @endcan
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-line px-4 py-6 text-center text-xs text-muted">No signatories yet.</p>
                        @endforelse
                        @can('manage-contract')
                            <button type="button" wire:click="addSignatory" class="btn-ghost btn-xs">＋ Add signatory</button>
                        @endcan
                        <div class="border-t border-line pt-2.5">
                            <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Reference</label>
                            <input type="text" wire:model.live.debounce.500ms="reference" class="{{ $in }} font-mono !text-xs">
                        </div>
                    </div>
                </x-accordion-section>
            </x-accordion>

