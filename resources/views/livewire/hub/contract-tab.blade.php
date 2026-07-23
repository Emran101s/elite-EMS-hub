@php
    // ── Shared identity: the type's label, chip and document spine ──
    $typeMeta = [
        'client' => ['Client Contract', 'bg-sky-100 text-sky-700', 'linear-gradient(#1F6FB2,#164e78)'],
        'vendor' => ['Vendor Agreement', 'bg-emerald-100 text-emerald-700', 'linear-gradient(#2E7D63,#1c5b48)'],
        'speaker' => ['Speaker Agreement', 'bg-violet-100 text-violet-700', 'linear-gradient(#7c5cbf,#573f8a)'],
        'sponsorship' => ['Sponsorship', 'bg-gold-100 text-gold-800', 'linear-gradient(#C8A44A,#9c7d2e)'],
        'letter' => ['Letter', 'bg-navy-100 text-navy-600', 'linear-gradient(#0E2140,#081426)'],
    ];
    $statusChip = [
        'draft' => ['Draft', 'bg-navy-50 text-navy-500'],
        'sent' => ['Sent', 'bg-amber-100 text-amber-700'],
        'partially_signed' => ['Partly signed', 'bg-amber-100 text-amber-700'],
        'signed' => ['Signed', 'bg-emerald-100 text-emerald-700'],
        'void' => ['Void', 'bg-navy-50 text-navy-300'],
    ];

    // The new input language: quiet fills that light up on focus — no boxed grid.
    $in = 'w-full rounded-xl border border-transparent bg-page/70 px-3 py-2 text-sm font-medium text-navy-900 placeholder:text-navy-300 transition focus:border-gold-400 focus:bg-white focus:outline-none';
    $inAr = $in.' text-right';
@endphp

<div>
@if (! $contractId || ! $contract)

    {{-- ════════════════════════════════════════════════════════════
         THE DECK — the landing. Your documents, or the invitation
         to create the first one. Nothing else competes with them.
    ═════════════════════════════════════════════════════════════ --}}
    <div x-data="{ view: 'deck' }" class="space-y-4">

        <div class="flex flex-wrap items-center gap-3">
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-gold-600">Contract Studio</p>
                <h2 class="pf text-h1 font-bold leading-tight text-navy-900">Documents</h2>
            </div>

            @if ($contracts->isNotEmpty())
                <div class="ml-auto flex rounded-xl border border-line bg-white p-0.5">
                    @foreach (['deck' => 'The Deck', 'pipe' => 'Pipeline'] as $v => $vl)
                        <button type="button" @click="view = '{{ $v }}'"
                                :class="view === '{{ $v }}' ? 'bg-navy-900 text-white' : 'text-navy-500 hover:text-navy-900'"
                                class="rounded-lg px-3 py-1.5 text-eyebrow font-bold transition">{{ $vl }}</button>
                    @endforeach
                </div>
                @can('manage-contract')
                    <button type="button" wire:click="$toggle('showNew')" class="btn-gold btn-sm">＋ New document</button>
                @endcan
            @endif
        </div>

        {{-- new-document panel --}}
        @if ($showNew)
            <div class="card flex flex-wrap items-end gap-2 border-gold-300 bg-gold-50/40 p-4">
                <div>
                    <label class="field-label !mb-1 !text-eyebrow">Type</label>
                    <select wire:model.live="newType" class="input h-10 w-auto !py-0 text-sm">
                        @foreach (\App\Models\EventContract::TYPES as $key => $meta)<option value="{{ $key }}">{{ $meta['label'] }}</option>@endforeach
                    </select>
                </div>
                @if (in_array($newType, ['vendor', 'speaker', 'sponsorship'], true))
                    <div>
                        <label class="field-label !mb-1 !text-eyebrow">{{ ['vendor' => 'Supplier', 'speaker' => 'Speaker', 'sponsorship' => 'Sponsor'][$newType] }}</label>
                        <select wire:model="newPartyId" class="input h-10 w-auto min-w-[12rem] !py-0 text-sm">
                            <option value="">— choose (optional) —</option>
                            @foreach ($partyOptions as $opt)<option value="{{ $opt->id }}">{{ $opt->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <button type="button" wire:click="createContract" class="btn-gold btn-sm">Create &amp; open</button>
                <button type="button" wire:click="$set('showNew', false)" class="btn-ghost btn-sm">Cancel</button>
            </div>
        @endif

        @if ($contracts->isEmpty())
            {{-- the empty table: one clear invitation --}}
            <div class="strip-dark relative flex flex-col items-center gap-4 !rounded-2xl py-16 text-center">
                <span aria-hidden="true" class="pointer-events-none absolute -top-20 left-1/2 h-64 w-[32rem] -translate-x-1/2 rounded-full bg-[radial-gradient(ellipse,rgba(212,175,55,0.16),transparent_65%)]"></span>
                <span class="relative flex h-16 w-16 -rotate-6 items-center justify-center rounded-full text-xl font-bold text-[#5a4718] shadow-lg"
                      style="background: radial-gradient(circle at 35% 30%, #E4C874, #C8A44A 55%, #9c7d2e); font-family: Georgia, serif;">EB</span>
                <div class="relative">
                    <p class="pf text-lg font-bold text-white">No documents yet</p>
                    <p class="mx-auto mt-1 max-w-[44ch] text-sm text-white/60">
                        Every agreement for this event lives here — the client contract, vendor and
                        speaker agreements, sponsorships and letters.
                    </p>
                </div>
                @can('manage-contract')
                    <button type="button" wire:click="$set('showNew', true)" class="btn-gold btn-sm relative">＋ Create your first document</button>
                @endcan
            </div>
        @else

            {{-- ══ THE TABLE: the real documents, laid out on the boardroom table.
                 Every miniature IS the actual paper — the same partial the
                 preview and the PDF render, scaled down. Navy & gold only. ══ --}}
            <div x-show="view === 'deck'" class="strip-dark relative !rounded-2xl p-6 sm:p-8">
                {{-- a pool of light over the table --}}
                <span aria-hidden="true" class="pointer-events-none absolute -top-28 left-1/2 h-80 w-[42rem] -translate-x-1/2 rounded-full bg-[radial-gradient(ellipse,rgba(212,175,55,0.14),transparent_65%)]"></span>

                <div class="relative flex flex-wrap items-start justify-center gap-x-8 gap-y-10 sm:justify-start">
                    @foreach ($contracts as $c)
                        @php
                            // Never print the type twice: the plaque's eyebrow owns it. The title
                            // is the custom name, else the counterparty, else honest "untitled".
                            $tLabel = \App\Models\EventContract::TYPES[$c->type]['label'] ?? 'Document';
                            $autoTitle = ! $c->title || $c->title === $tLabel || str_starts_with((string) $c->title, $tLabel.' · ');
                            $partyName = $c->party?->name
                                ?? ($c->isClient()
                                    ? ($c->data['second_parties'][0]['name_en'] ?? null)
                                    : (($c->data['counterparty']['name_en'] ?? '') ?: null));
                            $cardTitle = ! $autoTitle ? $c->title : ($c->isClient() ? 'Management Services Agreement' : $partyName);
                            $cardSub = $partyName && $partyName !== $cardTitle ? $partyName : null;
                            $plaqueStatus = [
                                'draft' => ['Draft', 'text-white/40'],
                                'sent' => ['Sent', 'text-amber-300'],
                                'partially_signed' => ['Partly signed', 'text-gold-300'],
                                'signed' => ['Signed', 'text-emerald-300'],
                                'void' => ['Void', 'text-white/30'],
                            ][$c->status] ?? ['Draft', 'text-white/40'];
                        @endphp
                        <div class="group relative w-[248px]" wire:key="doc-{{ $c->id }}">
                            <div role="button" tabindex="0"
                                 wire:click="selectContract({{ $c->id }})"
                                 @keydown.enter="$wire.selectContract({{ $c->id }})"
                                 class="relative cursor-pointer focus:outline-none">

                                {{-- the paper itself, resting on the table --}}
                                <div class="relative overflow-hidden rounded-[3px] shadow-[0_20px_45px_-18px_rgba(0,0,0,0.7)] ring-1 ring-white/10 transition duration-300 group-hover:-translate-y-2 group-hover:shadow-[0_34px_65px_-20px_rgba(0,0,0,0.85)] group-hover:ring-gold-400/70 group-focus-visible:ring-2 group-focus-visible:ring-gold-400">
                                    @include('event-contract.mini', ['c' => $c, 'event' => $event, 'scale' => 0.3875, 'window' => 296])
                                    {{-- the page falls away into the table's shadow --}}
                                    <span aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-navy-900/60 to-transparent"></span>

                                    {{-- delete on hover — any document, client included --}}
                                    @can('manage-contract')
                                        <button type="button" wire:click.stop="deleteContract({{ $c->id }})"
                                                wire:confirm="Delete “{{ $c->displayTitle() }}”? This removes the document and its signatures for good."
                                                class="absolute right-1.5 top-1.5 z-10 rounded-md bg-navy-900/60 px-1.5 py-0.5 text-eyebrow font-bold text-white/70 opacity-0 backdrop-blur-sm transition hover:bg-red-600/80 hover:text-white group-hover:opacity-100"
                                                title="Delete this document">✕</button>
                                    @endcan

                                    {{-- wax seal over the paper's corner when fully signed --}}
                                    @if ($c->status === 'signed')
                                        <span class="absolute -right-1 bottom-3 z-10 flex h-11 w-11 -rotate-6 items-center justify-center rounded-full font-bold text-[#5a4718] shadow-lg transition group-hover:rotate-0"
                                              style="background: radial-gradient(circle at 35% 30%, #E4C874, #C8A44A 55%, #9c7d2e); font-family: Georgia, serif;"
                                              title="Signed &amp; sealed">EB</span>
                                    @endif
                                </div>

                                {{-- the engraved plaque beneath the paper --}}
                                <div class="mt-3 border-t border-gold-400/40 pt-2.5">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="truncate text-3xs font-bold uppercase tracking-[0.28em] text-gold-300/90">{{ $tLabel }}</p>
                                        <p class="shrink-0 text-3xs font-bold uppercase tracking-[0.14em] {{ $plaqueStatus[1] }}">{{ $plaqueStatus[0] }}</p>
                                    </div>
                                    @if ($cardTitle)
                                        <p class="pf mt-0.5 truncate text-sm font-bold text-white">{{ $cardTitle }}</p>
                                    @else
                                        <p class="mt-0.5 truncate text-xs font-medium italic text-white/40">Untitled — choose a counterparty</p>
                                    @endif
                                    @if ($cardSub)<p class="truncate text-xs text-white/50">{{ $cardSub }}</p>@endif
                                    <div class="mt-1.5 flex items-end justify-between gap-2">
                                        <span class="truncate font-mono text-3xs text-white/35">{{ $c->reference }}</span>
                                        @if ($c->signatories_count > 0)
                                            <span class="flex shrink-0 items-end gap-1.5" title="{{ $c->signed_count }} of {{ $c->signatories_count }} signed">
                                                @for ($i = 0; $i < min($c->signatories_count, 5); $i++)
                                                    <span class="flex w-5 flex-col">
                                                        @if ($i < $c->signed_count)
                                                            <svg viewBox="0 0 24 10" class="h-2 w-5 text-gold-300"><path d="M1 7 C4 1.5, 7 9, 10 4.5 S 15.5 2, 17.5 6 S 22 3.5, 23 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                                        @else
                                                            <span class="mt-1.5 block border-b border-dashed border-white/25"></span>
                                                        @endif
                                                    </span>
                                                @endfor
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach

                    @can('manage-contract')
                        {{-- an empty place at the table --}}
                        <button type="button" wire:click="$set('showNew', true)"
                                class="group/new relative flex h-[296px] w-[248px] flex-col items-center justify-center gap-2 rounded-[3px] border-2 border-dashed border-white/15 text-white/40 transition hover:border-gold-400/60 hover:text-gold-300">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-dashed border-white/25 text-lg font-light transition group-hover/new:rotate-90 group-hover/new:border-gold-400/70">＋</span>
                            <span class="text-eyebrow font-bold uppercase tracking-[0.22em]">New document</span>
                        </button>
                    @endcan
                </div>
            </div>

            {{-- ══ THE PIPELINE: the same table, ruled into three gold-numbered
                 stages. Documents are white paper slips you slide along it. ══ --}}
            <div x-show="view === 'pipe'" x-cloak class="strip-dark relative !rounded-2xl p-5 sm:p-6" data-pipeline>
                <span aria-hidden="true" class="pointer-events-none absolute -top-28 left-1/2 h-80 w-[42rem] -translate-x-1/2 rounded-full bg-[radial-gradient(ellipse,rgba(212,175,55,0.12),transparent_65%)]"></span>

                <div class="relative grid gap-6 sm:grid-cols-3">
                    @foreach (['draft' => 'Draft', 'sent' => 'Sent for signature', 'signed' => 'Signed'] as $col => $colLabel)
                        @php $inCol = $contracts->filter(fn ($c) => $c->pipelineColumn() === $col); @endphp
                        <div data-col="{{ $col }}" @class(['sm:border-r sm:border-white/10 sm:pr-6' => ! $loop->last])>
                            {{-- the engraved stage marker --}}
                            <div class="flex items-baseline gap-2.5 pb-3">
                                <span class="pf text-lg font-black leading-none text-gold-400/60">{{ sprintf('%02d', $loop->iteration) }}</span>
                                <span class="text-eyebrow font-bold uppercase tracking-[0.22em] text-white/75">{{ $colLabel }}</span>
                                <span class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-3xs font-bold text-gold-300 ring-1 ring-gold-400/40">{{ $inCol->count() }}</span>
                            </div>

                            <div class="min-h-[9rem] space-y-3" data-drop="{{ $col }}">
                                @foreach ($inCol as $c)
                                    @php
                                        $tLabel = \App\Models\EventContract::TYPES[$c->type]['label'] ?? 'Document';
                                        $autoTitle = ! $c->title || $c->title === $tLabel || str_starts_with((string) $c->title, $tLabel.' · ');
                                        $partyName = $c->party?->name
                                            ?? ($c->isClient()
                                                ? ($c->data['second_parties'][0]['name_en'] ?? null)
                                                : (($c->data['counterparty']['name_en'] ?? '') ?: null));
                                        $cardTitle = ! $autoTitle ? $c->title : ($c->isClient() ? 'Management Services Agreement' : $partyName);
                                    @endphp
                                    {{-- a paper slip: the dossier's own masthead language, ink on white --}}
                                    <div wire:key="pipe-{{ $c->id }}" data-card="{{ $c->id }}"
                                         wire:click="selectContract({{ $c->id }})"
                                         class="group/pc relative cursor-grab overflow-hidden rounded-[3px] bg-white shadow-[0_12px_28px_-12px_rgba(0,0,0,0.75)] ring-1 ring-white/10 transition duration-200 hover:-translate-y-0.5 hover:rotate-[-0.5deg] hover:ring-gold-400/60 active:cursor-grabbing">
                                        <div class="px-3.5 py-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="block h-px w-6 bg-gold-500"></span>
                                                <span class="shrink-0 select-none text-xs leading-none tracking-tighter text-navy-200 opacity-0 transition group-hover/pc:opacity-100" aria-hidden="true">⠿</span>
                                            </div>
                                            <p class="mt-1 truncate text-3xs font-bold uppercase tracking-[0.2em] text-navy-400">{{ $tLabel }}</p>
                                            @if ($cardTitle)
                                                <p class="pf mt-0.5 truncate text-sm font-bold leading-snug text-navy-900">{{ $cardTitle }}</p>
                                            @else
                                                <p class="mt-0.5 truncate text-xs font-medium italic text-navy-300">Untitled</p>
                                            @endif
                                            <div class="mt-2 flex items-end justify-between gap-2 border-t border-dashed border-line/80 pt-1.5">
                                                <span class="truncate font-mono text-3xs text-muted">{{ $c->reference }}</span>
                                                @if ($c->signatories_count > 0)
                                                    <span class="flex shrink-0 items-end gap-1" title="{{ $c->signed_count }} of {{ $c->signatories_count }} signed">
                                                        @for ($i = 0; $i < min($c->signatories_count, 4); $i++)
                                                            <span class="flex w-4 flex-col">
                                                                @if ($i < $c->signed_count)
                                                                    <svg viewBox="0 0 24 10" class="h-1.5 w-4 text-navy-800"><path d="M1 7 C4 1.5, 7 9, 10 4.5 S 15.5 2, 17.5 6 S 22 3.5, 23 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                                                @else
                                                                    <span class="mt-1 block border-b border-dashed border-navy-300"></span>
                                                                @endif
                                                            </span>
                                                        @endfor
                                                    </span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                @endforeach

                                @if ($inCol->isEmpty())
                                    <div class="flex h-[5.5rem] items-center justify-center rounded-[3px] border border-dashed border-white/15 text-eyebrow font-semibold text-white/30">
                                        Drag a document here
                                    </div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

@else

    @php
        $f = $data['financials'] ?? [];
        $cur = $f['currency'] ?? ($data['currency'] ?? 'JOD');
        $est = $f['contract_value_cents'] ?? $f['estimated_total_cents'] ?? 0;
        $isFixed = ($f['value_mode'] ?? 'fixed') === 'fixed';
        $fmt = fn ($c) => $cur.' '.number_format(($c ?? 0) / 100);
        [$tLabel] = $typeMeta[$type] ?? ['Document'];
        [$sLabel, $sChip] = $statusChip[$status] ?? ['Draft', 'bg-navy-50 text-navy-500'];
        $bilingual = $type === 'client' || $language === 'bilingual';
        $fullySigned = $signatories->isNotEmpty() && $signatories->whereNull('signed_at')->isEmpty();
    @endphp

    {{-- ════════════════════════════════════════════════════════════
         THE LIVING EDITOR — controls on the left, the actual paper
         on the right, building itself as you type. No summary box.
    ═════════════════════════════════════════════════════════════ --}}
    <div class="space-y-4">

        {{-- ── document header ── --}}
        <div class="strip-dark flex flex-wrap items-center gap-3 px-5 py-4">
            <div class="pointer-events-none absolute -right-8 -top-16 h-48 w-48 rounded-full bg-[radial-gradient(circle,rgba(212,175,55,0.3),transparent_70%)]"></div>

            <button type="button" wire:click="backToDeck"
                    class="relative flex h-9 items-center gap-1.5 rounded-xl bg-white/5 px-3 text-micro font-bold text-white/70 ring-1 ring-white/15 transition hover:text-white">
                ← Documents
            </button>

            <div class="relative min-w-0 flex-1">
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-gold-300">{{ $tLabel }} · {{ $reference }}</p>
                <p class="pf truncate text-base font-semibold text-white">{{ $title ?: ($type === 'client' ? 'Management Services Agreement' : $tLabel) }}</p>
            </div>

            <div class="relative flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-eyebrow font-bold uppercase tracking-wide text-white ring-1 ring-white/15">{{ $sLabel }}</span>
                <button type="button" wire:click="cycleStatus" class="h-9 rounded-xl bg-white/5 px-3 text-micro font-bold text-white/70 ring-1 ring-white/15 transition hover:text-white" title="Advance status">Advance →</button>

                @if ($dirty)
                    <button type="button" wire:click="discard" wire:confirm="Discard your unsaved changes?"
                            class="h-9 rounded-xl px-3 text-micro font-bold text-white/50 transition hover:text-white/90">Discard</button>
                @endif
                <button type="button" wire:click="save" @disabled(! $dirty)
                        @class([
                            'h-9 rounded-xl px-4 text-micro font-bold transition',
                            'bg-white text-navy-900 shadow hover:brightness-95' => $dirty,
                            'bg-white/5 text-white/30 ring-1 ring-white/10' => ! $dirty,
                        ])>{{ $dirty ? 'Save changes' : 'Saved' }}</button>

                @if ($type === 'client')
                    <a href="{{ route('events.contract.pdf', $event) }}" target="_blank"
                       title="{{ $dirty ? 'Exports the saved version — save first to include your edits' : 'Export the contract' }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-4 text-micro font-bold text-navy-950 shadow transition hover:brightness-105">
                        ↧ Export PDF
                    </a>
                    <button type="button" wire:click="resetContract" wire:confirm="Reset the contract to defaults? Your edits will be replaced."
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-white/5 hover:text-white/80" title="Reset to defaults">↺</button>
                @else
                    {{-- Every other type exports through the shared document sheet. --}}
                    <a href="{{ route('events.contract.doc.pdf', [$event, $contractId]) }}" target="_blank"
                       title="{{ $dirty ? 'Exports the saved version — save first to include your edits' : 'Export this document' }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-gradient-to-r from-gold-400 to-gold-600 px-4 text-micro font-bold text-navy-950 shadow transition hover:brightness-105">
                        ↧ Export PDF
                    </a>
                @endif

                @can('manage-contract')
                    <button type="button" wire:click="deleteContract({{ $contractId }})"
                            wire:confirm="Delete this document and its signatures for good?"
                            class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-risk/20 hover:text-red-300" title="Delete this document">🗑</button>
                @endcan
            </div>
        </div>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">

            {{-- ══════════ LEFT · the controls, as collapsible modules ══════════ --}}
            <div class="space-y-3">

                @if ($type !== 'client')
                    {{-- Document module --}}
                    <section x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-line bg-white">
                        <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                            <span class="pf text-lg font-bold text-gold-500">01</span>
                            <span class="flex-1">
                                <span class="block text-sm font-bold text-navy-900">Document</span>
                                <span class="block text-eyebrow text-muted">Title, counterparty and language</span>
                            </span>
                            <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                        </button>
                        <div x-show="open" class="space-y-3 border-t border-line px-5 py-4">
                            <input type="text" wire:model.live.debounce.500ms="title" placeholder="{{ $tLabel }}" class="{{ $in }} !text-base !font-bold">
                            @php $cp = $data['counterparty'] ?? []; @endphp

                            @if (in_array($type, ['vendor', 'speaker', 'sponsorship'], true))
                                {{-- Pick the counterparty from the event's own list — the
                                     name, contact, fee and topic flow into the agreement. --}}
                                <div>
                                    <label class="field-label !mb-1 !text-eyebrow">
                                        {{ ['vendor' => 'Supplier', 'speaker' => 'Speaker', 'sponsorship' => 'Sponsor'][$type] }} — from the event's list
                                    </label>
                                    <select wire:change="setParty($event.target.value)" class="input h-10 w-full !py-0 text-sm">
                                        <option value="">— choose —</option>
                                        @foreach ($editPartyOptions as $opt)
                                            <option value="{{ $opt->id }}" @selected($contract->party_id === $opt->id)>{{ $opt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($type === 'letter')
                                <div>
                                    <label class="field-label !mb-1 !text-eyebrow">Recipient</label>
                                    <input type="text" wire:model.live.debounce.500ms="data.counterparty.name_en"
                                           placeholder="HE the Minister of Culture…" class="{{ $in }}">
                                </div>
                            @endif

                            {{-- type-specific details that print into the agreement --}}
                            @if ($type === 'speaker')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="field-label !mb-1 !text-eyebrow">Session / topic</label>
                                        <input type="text" wire:model.live.debounce.500ms="data.counterparty.detail" placeholder="Keynote — AI in Events" class="{{ $in }}">
                                    </div>
                                    <div>
                                        <label class="field-label !mb-1 !text-eyebrow">Honorarium ({{ $cur }})</label>
                                        <input type="number" min="0" step="0.01" value="{{ ($cp['fee_cents'] ?? null) !== null ? number_format(($cp['fee_cents'] ?? 0) / 100, 2, '.', '') : '' }}"
                                               wire:change="setPartyFee($event.target.value)" placeholder="—" class="{{ $in }} text-right">
                                    </div>
                                </div>
                            @elseif ($type === 'sponsorship')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="field-label !mb-1 !text-eyebrow">Package / tier</label>
                                        <input type="text" wire:model.live.debounce.500ms="data.counterparty.package" placeholder="Gold" class="{{ $in }}">
                                    </div>
                                    <div>
                                        <label class="field-label !mb-1 !text-eyebrow">Sponsorship amount ({{ $cur }})</label>
                                        <input type="number" min="0" step="0.01" value="{{ ($cp['fee_cents'] ?? null) !== null ? number_format(($cp['fee_cents'] ?? 0) / 100, 2, '.', '') : '' }}"
                                               wire:change="setPartyFee($event.target.value)" placeholder="—" class="{{ $in }} text-right">
                                    </div>
                                </div>
                            @elseif ($type === 'vendor')
                                <div>
                                    <label class="field-label !mb-1 !text-eyebrow">Service category</label>
                                    <input type="text" wire:model.live.debounce.500ms="data.counterparty.detail" placeholder="production, catering, transport…" class="{{ $in }}">
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center gap-2 border-t border-line pt-2.5">
                                <span class="text-eyebrow font-bold uppercase tracking-wide text-muted">Language</span>
                                @foreach (['en' => 'English', 'bilingual' => 'Bilingual EN/AR'] as $lk => $ll)
                                    <button type="button" wire:click="setLanguage('{{ $lk }}')"
                                            @class(['rounded-lg px-2.5 py-1 text-eyebrow font-bold transition', 'bg-navy-900 text-white' => $language === $lk, 'bg-navy-50 text-navy-600 hover:bg-navy-100' => $language !== $lk])>{{ $ll }}</button>
                                @endforeach

                                @can('manage-contract')
                                    <button type="button" wire:click="refillFromTemplate"
                                            wire:confirm="Rewrite the body from the standard {{ strtolower($tLabel) }} template with the current details? Your edits to the body will be replaced."
                                            class="ml-auto text-eyebrow font-bold uppercase tracking-wide text-navy-400 hover:text-gold-700"
                                            title="Re-generate the clauses with the chosen counterparty and details">↻ Refill body</button>
                                @endcan
                            </div>
                        </div>
                    </section>
                @endif

                @if ($type === 'client')
                    {{-- Parties module --}}
                    <section x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-line bg-white">
                        <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                            <span class="pf text-lg font-bold text-gold-500">01</span>
                            <span class="flex-1">
                                <span class="block text-sm font-bold text-navy-900">Parties</span>
                                <span class="block text-eyebrow text-muted">The client entities and their shares</span>
                            </span>
                            <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                        </button>
                        <div x-show="open" class="space-y-2.5 border-t border-line px-5 py-4">
                            @php $shareTotal = collect($data['second_parties'] ?? [])->sum(fn ($p) => (float) ($p['share'] ?? 0)); @endphp
                            @foreach ($data['second_parties'] ?? [] as $i => $sp)
                                <div wire:key="sp-{{ $i }}" class="group/sp space-y-1.5">
                                    <div class="flex items-center justify-between">
                                        <p class="text-eyebrow font-bold uppercase tracking-wide text-navy-400">Second party {{ $i + 1 }}</p>
                                        @if (count($data['second_parties']) > 1)
                                            <button type="button" wire:click="removeSecondParty({{ $i }})"
                                                    class="text-eyebrow font-bold uppercase tracking-wide text-navy-300 opacity-0 transition hover:text-red-700 group-hover/sp:opacity-100">Remove</button>
                                        @endif
                                    </div>
                                    <div class="grid grid-cols-[1fr_5rem] gap-1.5">
                                        <input type="text" wire:model.live.debounce.500ms="data.second_parties.{{ $i }}.name_en" placeholder="Entity name (English)" class="{{ $in }}">
                                        <div class="relative">
                                            <input type="number" min="0" max="100" wire:model.live.debounce.300ms="data.second_parties.{{ $i }}.share" class="{{ $in }} pr-6 text-center !font-bold">
                                            <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-eyebrow font-semibold text-navy-400">%</span>
                                        </div>
                                    </div>
                                    <input type="text" dir="rtl" wire:model.live.debounce.500ms="data.second_parties.{{ $i }}.name_ar" placeholder="اسم الجهة بالعربية" class="{{ $inAr }}">
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between border-t border-line pt-2.5">
                                @php $shareOk = abs($shareTotal - 100) < 0.001; @endphp
                                <span class="text-micro font-bold {{ $shareOk ? 'text-emerald-600' : 'text-risk' }}">
                                    {{ rtrim(rtrim(number_format($shareTotal, 2), '0'), '.') }}%
                                    @unless ($shareOk)<span class="font-semibold"> — must equal 100%</span>@endunless
                                </span>
                                <button type="button" wire:click="addSecondParty" class="btn-ghost btn-xs">＋ Add party</button>
                            </div>
                        </div>
                    </section>

                    {{-- Value & schedule module --}}
                    <section x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-line bg-white">
                        <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                            <span class="pf text-lg font-bold text-gold-500">02</span>
                            <span class="flex-1">
                                <span class="block text-sm font-bold text-navy-900">Value &amp; Payments</span>
                                <span class="block text-eyebrow text-muted">{{ $fmt($est) }} · {{ count($f['payment_schedule'] ?? []) }} installments</span>
                            </span>
                            <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                        </button>
                        <div x-show="open" class="space-y-3 border-t border-line px-5 py-4">
                            <div class="rounded-xl bg-gold-50/60 p-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-eyebrow font-bold text-navy-400">{{ $cur }}</span>
                                    <input type="number" min="0" step="0.001" value="{{ number_format($est / 100, 3, '.', '') }}"
                                           wire:change="setContractValue($event.target.value)"
                                           class="{{ $in }} flex-1 !bg-white text-right !text-base !font-black">
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <div class="flex rounded-lg border border-line bg-white p-0.5">
                                        @foreach (['fixed' => 'Fixed price', 'estimate' => 'Estimate'] as $mode => $ml)
                                            <button type="button" wire:click="setValueMode('{{ $mode }}')"
                                                    @class(['rounded-md px-2 py-1 text-eyebrow font-bold transition', 'bg-navy-900 text-white' => ($f['value_mode'] ?? 'fixed') === $mode, 'text-navy-500 hover:text-navy-900' => ($f['value_mode'] ?? 'fixed') !== $mode])>{{ $ml }}</button>
                                        @endforeach
                                    </div>
                                    <button type="button" wire:click="syncBudget" wire:confirm="Copy the event budget into the contract value? This overwrites the figure once — it does not link them."
                                            class="text-eyebrow font-bold uppercase tracking-wide text-navy-400 hover:text-gold-700">↻ From budget</button>
                                </div>
                            </div>

                            @php $totalPct = collect($f['payment_schedule'] ?? [])->sum(fn ($s) => (float) ($s['pct'] ?? 0)); @endphp
                            @foreach ($f['payment_schedule'] ?? [] as $i => $s)
                                <div wire:key="inst-{{ $i }}" class="group/inst space-y-1.5 border-t border-line/70 pt-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-gold-400">{{ $i + 1 }}</span>
                                        <div class="relative w-20 shrink-0">
                                            <input type="number" min="0" max="100" step="0.01" wire:model.live.debounce.300ms="data.financials.payment_schedule.{{ $i }}.pct" class="{{ $in }} pr-6 text-center !font-bold">
                                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-eyebrow font-semibold text-navy-400">%</span>
                                        </div>
                                        <span class="flex-1 text-right text-micro font-semibold text-navy-500">{{ $fmt($est * (float) ($s['pct'] ?? 0) / 100) }}</span>
                                        @if (count($f['payment_schedule']) > 1)
                                            <button type="button" wire:click="removeInstallment({{ $i }})"
                                                    class="shrink-0 rounded-md px-1.5 py-1 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:bg-risk/10 hover:text-red-700 group-hover/inst:opacity-100">✕</button>
                                        @endif
                                    </div>
                                    <div class="grid gap-1.5 sm:grid-cols-2">
                                        <input type="text" wire:model.live.debounce.500ms="data.financials.payment_schedule.{{ $i }}.when_en" placeholder="Due — English" class="{{ $in }} !py-1.5 !text-xs">
                                        <input type="text" dir="rtl" wire:model.live.debounce.500ms="data.financials.payment_schedule.{{ $i }}.when_ar" placeholder="الاستحقاق — بالعربية" class="{{ $inAr }} !py-1.5 !text-xs">
                                    </div>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between border-t border-line pt-2.5">
                                <span class="text-micro font-bold {{ abs($totalPct - 100) < 0.001 ? 'text-emerald-600' : 'text-risk' }}">Total {{ rtrim(rtrim(number_format($totalPct, 2), '0'), '.') }}%</span>
                                <span class="flex gap-2">
                                    <button type="button" wire:click="balanceInstallments" class="btn-ghost btn-xs" title="Spread 100% evenly">⚖ Balance</button>
                                    <button type="button" wire:click="addInstallment" class="btn-ghost btn-xs">＋ Add installment</button>
                                </span>
                            </div>
                        </div>
                    </section>

                    {{-- Assumptions module --}}
                    <section x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-line bg-white">
                        <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                            <span class="pf text-lg font-bold text-gold-500">03</span>
                            <span class="flex-1">
                                <span class="block text-sm font-bold text-navy-900">Budget Assumptions</span>
                                <span class="block text-eyebrow text-muted">What the estimate is based on</span>
                            </span>
                            <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                        </button>
                        <div x-show="open" class="grid gap-2.5 border-t border-line px-5 py-4 sm:grid-cols-2">
                            <div><label class="field-label !mb-1 !text-eyebrow">Attendees — from</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.attendees_min" class="{{ $in }}"></div>
                            <div><label class="field-label !mb-1 !text-eyebrow">Attendees — to</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.attendees_max" class="{{ $in }}"></div>
                            <div><label class="field-label !mb-1 !text-eyebrow">Rooms</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.rooms" class="{{ $in }}"></div>
                            <div><label class="field-label !mb-1 !text-eyebrow">Nights per guest</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.nights" class="{{ $in }}"></div>
                            <div class="sm:col-span-2"><label class="field-label !mb-1 !text-eyebrow">Catering (English)</label><input type="text" wire:model.live.debounce.500ms="data.assumptions.catering_en" class="{{ $in }}"></div>
                        </div>
                    </section>
                @endif

                {{-- Body module --}}
                <section x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-line bg-white">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                        <span class="pf text-lg font-bold text-gold-500">{{ $type === 'client' ? '04' : '02' }}</span>
                        <span class="flex-1">
                            <span class="block text-sm font-bold text-navy-900">Contract Body</span>
                            <span class="block text-eyebrow text-muted">{{ count($data['blocks'] ?? []) }} clauses · every one editable</span>
                        </span>
                        <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                    </button>
                    <div x-show="open" class="space-y-3 border-t border-line px-5 py-4">
                        @can('manage-contract')
                            <div class="flex items-center justify-end gap-2">
                                <button type="button" wire:click="restoreStandardBlocks"
                                        wire:confirm="Restore the standard clause set? Any edits to the body will be replaced."
                                        class="btn-ghost btn-xs" title="Put the standard clauses back">↺ Restore standard</button>
                                <button type="button" wire:click="addBlock" class="btn-gold btn-xs">＋ Add section</button>
                            </div>
                        @endcan

                        @forelse ($data['blocks'] ?? [] as $bi => $b)
                            <div wire:key="blk-{{ $b['id'] }}" class="rounded-2xl bg-page/50 p-3">
                                <div class="flex items-start gap-2.5">
                                    <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-navy-900 text-eyebrow font-black text-gold-400">{{ $bi + 1 }}</span>
                                    <div class="grid min-w-0 flex-1 gap-1.5">
                                        <input type="text" value="{{ $b['title_en'] ?? '' }}" placeholder="Clause title (English)"
                                               wire:change="updateBlockField('{{ $b['id'] }}', 'title_en', $event.target.value)"
                                               class="{{ $in }} !bg-white !py-1.5 !text-xs !font-bold">
                                        <input type="text" dir="rtl" value="{{ $b['title_ar'] ?? '' }}" placeholder="عنوان البند (بالعربية)"
                                               wire:change="updateBlockField('{{ $b['id'] }}', 'title_ar', $event.target.value)"
                                               class="{{ $inAr }} !bg-white !py-1.5 !text-xs !font-bold">
                                    </div>
                                    @can('manage-contract')
                                        <div class="flex shrink-0 flex-col items-center gap-0.5">
                                            <button type="button" wire:click="moveBlock('{{ $b['id'] }}', -1)" @disabled($bi === 0)
                                                    class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-navy-400 hover:bg-navy-50 hover:text-navy-800 disabled:opacity-25" title="Move up">↑</button>
                                            <button type="button" wire:click="moveBlock('{{ $b['id'] }}', 1)" @disabled($bi === count($data['blocks']) - 1)
                                                    class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-navy-400 hover:bg-navy-50 hover:text-navy-800 disabled:opacity-25" title="Move down">↓</button>
                                            <button type="button" wire:click="deleteBlock('{{ $b['id'] }}')"
                                                    wire:confirm="Delete “{{ $b['title_en'] ?? 'this clause' }}” from this contract?"
                                                    class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-navy-300 hover:bg-risk/10 hover:text-red-700" title="Delete clause">✕</button>
                                        </div>
                                    @endcan
                                </div>

                                <div class="mt-2 space-y-2">
                                    @foreach ($b['en'] ?? [] as $p => $para)
                                        <div class="group/para grid gap-1.5">
                                            <textarea rows="2" placeholder="English text…"
                                                      wire:change="updateParagraph('{{ $b['id'] }}', 'en', {{ $p }}, $event.target.value)"
                                                      class="{{ $in }} !bg-white !py-2 !text-xs leading-relaxed">{{ $para }}</textarea>
                                            <div class="relative">
                                                <textarea rows="2" dir="rtl" placeholder="النص بالعربية…"
                                                          wire:change="updateParagraph('{{ $b['id'] }}', 'ar', {{ $p }}, $event.target.value)"
                                                          class="{{ $inAr }} !bg-white !py-2 !text-xs leading-relaxed">{{ $b['ar'][$p] ?? '' }}</textarea>
                                                @if (count($b['en']) > 1)
                                                    <button type="button" wire:click="removeParagraph('{{ $b['id'] }}', {{ $p }})"
                                                            class="absolute -right-1 -top-1 hidden h-5 w-5 items-center justify-center rounded-full bg-white text-eyebrow font-bold text-navy-300 shadow ring-1 ring-line hover:text-red-700 group-hover/para:flex"
                                                            title="Remove this paragraph pair">✕</button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    <button type="button" wire:click="addParagraph('{{ $b['id'] }}')"
                                            class="text-eyebrow font-bold uppercase tracking-wide text-navy-400 hover:text-gold-700">＋ Add paragraph</button>
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
                                                        class="mt-1 text-eyebrow font-bold uppercase tracking-wide text-navy-300 opacity-0 transition hover:text-red-700 group-hover/itm:opacity-100">Remove row</button>
                                            </div>
                                        @endforeach
                                        <button type="button" wire:click="addItem('{{ $b['id'] }}')"
                                                class="text-eyebrow font-bold uppercase tracking-wide text-navy-400 hover:text-gold-700">＋ Add item</button>
                                    </div>
                                @elseif (($b['type'] ?? '') !== 'prose')
                                    <p class="mt-2 rounded-lg bg-navy-50/70 px-2.5 py-1.5 text-eyebrow text-muted">
                                        @switch($b['type'])
                                            @case('costshare') Followed by the cost-share table — edit the entities in <b>Parties</b>. @break
                                            @case('schedule') Followed by the payment table — edit it in <b>Value &amp; Payments</b>. @break
                                        @endswitch
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-line px-4 py-8 text-center text-xs text-muted">
                                No clauses yet — add a section or restore the standard set.
                            </p>
                        @endforelse
                    </div>
                </section>

                {{-- Signatories module --}}
                <section x-data="{ open: false }" class="overflow-hidden rounded-2xl border border-line bg-white">
                    <button type="button" @click="open = !open" class="flex w-full items-center gap-3 px-5 py-3.5 text-left">
                        <span class="pf text-lg font-bold text-gold-500">{{ $type === 'client' ? '05' : '03' }}</span>
                        <span class="flex-1">
                            <span class="block text-sm font-bold text-navy-900">Signatories</span>
                            <span class="block text-eyebrow text-muted">{{ $signatories->whereNotNull('signed_at')->count() }} of {{ $signatories->count() }} signed</span>
                        </span>
                        <span class="text-navy-300 transition" :class="open && 'rotate-180'">▾</span>
                    </button>
                    <div x-show="open" class="space-y-2 border-t border-line px-5 py-4">
                        @forelse ($signatories as $s)
                            <div wire:key="sig-{{ $s->id }}"
                                 @class(['group flex flex-wrap items-center gap-2 rounded-xl px-3 py-2.5', 'bg-emerald-50/60' => $s->isSigned(), 'bg-page/50' => ! $s->isSigned()])>
                                <span @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-black', 'bg-gold-500 text-navy-950' => $s->isSigned(), 'bg-white text-navy-500 ring-1 ring-line' => ! $s->isSigned()])>{{ $s->initials() }}</span>
                                <div class="min-w-[9rem] flex-1">
                                    <input type="text" value="{{ $s->name }}" placeholder="Signatory name" @disabled($s->isSigned())
                                           wire:change="updateSignatory({{ $s->id }}, 'name', $event.target.value)"
                                           class="w-full rounded-lg border border-transparent bg-transparent px-1.5 py-0.5 text-sm font-bold text-navy-900 hover:border-line focus:border-gold-400 focus:bg-white focus:outline-none disabled:opacity-70">
                                    <div class="flex items-center gap-2 px-1.5">
                                        <select wire:change="updateSignatory({{ $s->id }}, 'role', $event.target.value)" @disabled($s->isSigned())
                                                class="border-0 bg-transparent p-0 text-eyebrow font-semibold text-muted focus:ring-0 disabled:opacity-70">
                                            @foreach (\App\Models\ContractSignatory::ROLES as $rk => $rl)<option value="{{ $rk }}" @selected($s->role === $rk)>{{ $rl }}</option>@endforeach
                                        </select>
                                        @if ($s->isSigned())
                                            <span class="text-eyebrow font-semibold text-emerald-700">✓ {{ $s->signed_at->format('j M Y · H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @can('manage-contract')
                                    @if ($s->isSigned())
                                        <button type="button" wire:click="unsign({{ $s->id }})" class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-navy-400 hover:text-navy-900" title="Undo this signature">↺ Unsign</button>
                                    @else
                                        <button type="button" wire:click="recordSignature({{ $s->id }})"
                                                wire:confirm="Record {{ $s->name ?: 'this party' }} as having signed? This stamps the date and locks their name to the current document."
                                                @disabled(! $s->name)
                                                class="rounded-lg bg-navy-900 px-3 py-1.5 text-eyebrow font-bold text-white transition hover:bg-navy-800 disabled:opacity-40">✒️ Mark signed</button>
                                        <button type="button" wire:click="removeSignatory({{ $s->id }})"
                                                class="rounded-lg px-1.5 py-1.5 text-eyebrow font-bold text-navy-300 opacity-0 transition hover:text-red-700 group-hover:opacity-100" title="Remove signatory">✕</button>
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
                            <label class="field-label !mb-1 !text-eyebrow">Reference</label>
                            <input type="text" wire:model.live.debounce.500ms="reference" class="{{ $in }} font-mono !text-xs">
                        </div>
                    </div>
                </section>
            </div>

            {{-- ══════════ RIGHT · the living paper ══════════ --}}
            <div class="xl:sticky xl:top-[112px]">
                <div class="rounded-3xl bg-navy-900/[0.05] p-3 ring-1 ring-line sm:p-5">
                    <div class="mb-2 flex items-center justify-between px-1">
                        <span class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-navy-500">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Live preview
                        </span>
                        <span class="text-eyebrow text-muted">{{ $bilingual ? 'English · العربية' : 'English' }}</span>
                    </div>

                    <div class="max-h-[calc(100vh-220px)] overflow-y-auto rounded-lg">
                        {{-- One source of truth: this very partial is what the PDF
                             renders, with the same compiled CSS. WYSIWYG for real. --}}
                        @include('event-contract.paper', [
                            'tLabel' => $tLabel, 'type' => $type, 'title' => $title, 'data' => $data,
                            'bilingual' => $bilingual, 'event' => $event, 'fmt' => $fmt, 'est' => $est,
                            'f' => $f, 'signatories' => $signatories, 'reference' => $reference,
                            'status' => $status, 'fullySigned' => $fullySigned, 'forPdf' => false,
                        ])
                    </div>
                </div>
            </div>
        </div>
    </div>
@endif

    {{-- ══ Floating save bar — Save is always reachable while editing ══ --}}
    <div x-data="{ dirty: @entangle('dirty') }" x-show="dirty" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 translate-y-3"
         x-transition:enter-end="opacity-100 translate-y-0"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100 translate-y-0"
         x-transition:leave-end="opacity-0 translate-y-3"
         class="pointer-events-none fixed inset-x-0 bottom-6 z-40 flex justify-center px-4">
        <div class="pointer-events-auto flex items-center gap-3 rounded-2xl bg-navy-900 px-4 py-2.5 text-white shadow-float ring-1 ring-white/10">
            <span class="flex items-center gap-2 text-xs font-semibold text-amber-200">
                <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span> Unsaved changes
            </span>
            <button type="button" wire:click="discard" wire:confirm="Discard your unsaved changes?"
                    class="rounded-lg px-3 py-1.5 text-xs font-bold text-white/55 transition hover:text-white">Discard</button>
            <button type="button" wire:click="save"
                    class="rounded-lg bg-white px-5 py-1.5 text-xs font-black text-navy-900 shadow transition hover:brightness-95">
                Save changes
            </button>
        </div>
    </div>
</div>

@script
<script>
    // Pipeline: drag a contract card into another column to change its status.
    (() => {
        const wire = $wire;
        const wireUp = () => {
            document.querySelectorAll('[data-drop]').forEach((zone) => {
                if (zone.dataset.sortableBound) return;
                zone.dataset.sortableBound = '1';

                new window.Sortable(zone, {
                    group: 'contract-pipeline',
                    animation: 150,
                    draggable: '[data-card]',
                    ghostClass: 'opacity-40',
                    onAdd(evt) {
                        const id = Number(evt.item?.dataset?.card);
                        const status = zone.dataset.drop;
                        evt.item.remove();               // Livewire re-renders the truth
                        if (id && status) wire.setContractStatus(id, status);
                    },
                });
            });
        };
        wireUp();
        Livewire.hook('morphed', wireUp);
    })();
</script>
@endscript
