@php
    // ── Shared identity: the type's label, chip and document spine ──
    $typeMeta = [
        'client' => ['Client Contract', 'bg-sky-100 text-sky-700', 'linear-gradient(var(--ion),var(--ion-lit))'],
        'vendor' => ['Vendor Agreement', 'bg-emerald-100 text-emerald-700', 'linear-gradient(var(--vital),var(--vital-lit))'],
        'speaker' => ['Speaker Agreement', 'bg-eo-bg text-eo-text', 'linear-gradient(var(--plasma),var(--plasma-lit))'],
        'sponsorship' => ['Sponsorship', 'bg-gold-100 text-gold-800', 'linear-gradient(var(--gold-2),var(--gold))'],
        'letter' => ['Letter', 'bg-eo-bg text-eo-muted', 'linear-gradient(var(--chrome-2),var(--chrome))'],
        'acceptance' => ['Certificate of Services', 'bg-emerald-100 text-emerald-700', 'linear-gradient(var(--vital),var(--vital-lit))'],
    ];
    // Same status meta the register uses — this editor and the register showed
    // the same status in two different colours before they shared it.
    $statusChip = \App\Models\EventContract::statusMeta();

    // The new input language: quiet fills that light up on focus — no boxed grid.
    $in = 'w-full rounded-xl border border-transparent bg-eo-workspace/70 px-3 py-2 text-sm font-medium text-eo-text placeholder:text-eo-muted transition focus:border-eo-teal focus:bg-white focus:outline-none';
    $inAr = $in.' text-right';

    // Which set the block editor is pointed at. The body unless an appendix is
    // open — one editor, so there is only ever one of these to keep in step.
    $axList = array_values($data['appendices'] ?? []);
    $axIndex = null;
    foreach ($axList as $i => $a) {
        if (($a['slug'] ?? null) === $editingAppendix) { $axIndex = $i; break; }
    }
    $editingAx = $axIndex === null ? null : $axList[$axIndex];
    $axNumber = $axIndex === null ? null : $axIndex + 1;
    $editBlocks = $editingAx ? ($editingAx['blocks'] ?? []) : ($data['blocks'] ?? []);

    // References pointing at an appendix that no longer exists. A wrong
    // cross-reference in a signed contract is worse than a missing feature, so
    // this is shown loudly and blocks the export.
    $axNumbers = [];
    foreach ($axList as $i => $a) { $axNumbers[$a['slug'] ?? ''] = (string) ($i + 1); }
    $broken = \App\Support\ContractAppendices::brokenReferences(
        [...($data['blocks'] ?? []), ...$axList], $axNumbers,
    );
@endphp

<div>
@if (! $contractId || ! $contract)

    {{-- ════════════════════════════════════════════════════════════
         THE DECK — the landing. Your documents, or the invitation
         to create the first one. Nothing else competes with them.
    ═════════════════════════════════════════════════════════════ --}}
    <div x-data="{ view: 'deck' }" class="space-y-4">

        @php
            $pendingCount = $contracts->whereIn('status', ['draft', 'sent', 'partially_signed'])->count();
        @endphp

        <div class="flex flex-wrap items-center gap-3">
            <div>
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-eo-teal-ink">Contract Studio</p>
                <h2 class="text-h1 font-bold leading-tight text-eo-text">Documents</h2>
            </div>

            {{-- "Docs" and "Signed" are dropped here — the Universal Module
                 Header already shows those two exact counts. "In progress"
                 stays: it counts documents not yet fully signed, which is a
                 different number from the header's "Pending signatures"
                 (individual missing signatory slots across all documents). --}}
            @if ($contracts->isNotEmpty())
                <div class="flex flex-wrap items-center gap-1.5 sm:ml-2">
                    <span class="inline-flex h-7 items-center gap-1.5 rounded-lg bg-amber-50 px-2.5 text-eyebrow font-bold text-amber-800 ring-1 ring-amber-200">
                        <span class="text-amber-600/80">In progress</span>
                        <span class="tabular-nums">{{ $pendingCount }}</span>
                    </span>
                </div>

                <div class="ml-auto flex rounded-xl border border-eo-line bg-white p-0.5">
                    @foreach (['deck' => 'The Deck', 'pipe' => 'Pipeline'] as $v => $vl)
                        <button type="button" @click="view = '{{ $v }}'"
                                :class="view === '{{ $v }}' ? 'bg-eo-navy text-white' : 'text-eo-muted hover:text-eo-text'"
                                class="rounded-lg px-3 py-1.5 text-eyebrow font-bold transition">{{ $vl }}</button>
                    @endforeach
                </div>
                @can('manage-contract')
                    <button type="button" wire:click="$toggle('showNew')" class="eo-btn-primary btn-sm">＋ New document</button>
                @endcan
            @endif
        </div>

        {{-- new-document panel --}}
        @if ($showNew)
            <div class="eo-soft-card flex flex-wrap items-end gap-2 border-eo-teal/30 bg-eo-teal-soft/40 p-4">
                <div>
                    <label class="eo-label !mb-1 !text-eyebrow">Type</label>
                    <select wire:model.live="newType" class="eo-input h-10 w-auto !py-0 text-sm">
                        @foreach (\App\Models\EventContract::TYPES as $key => $meta)<option value="{{ $key }}">{{ $meta['label'] }}</option>@endforeach
                    </select>
                </div>
                @if (in_array($newType, ['vendor', 'speaker', 'sponsorship'], true))
                    <div>
                        <label class="eo-label !mb-1 !text-eyebrow">{{ ['vendor' => 'Supplier', 'speaker' => 'Speaker', 'sponsorship' => 'Sponsor'][$newType] }}</label>
                        <select wire:model="newPartyId" class="eo-input h-10 w-auto min-w-[12rem] !py-0 text-sm">
                            <option value="">— choose (optional) —</option>
                            @foreach ($partyOptions as $opt)<option value="{{ $opt->id }}">{{ $opt->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <button type="button" wire:click="createContract" class="eo-btn-primary btn-sm">Create &amp; open</button>
                <button type="button" wire:click="$set('showNew', false)" class="eo-btn-ghost btn-sm">Cancel</button>
            </div>
        @endif

        @if ($contracts->isEmpty())
            {{-- the empty table: one clear invitation --}}
            <div class="strip-dark relative flex flex-col items-center gap-4 !rounded-2xl py-16 text-center">
                <span aria-hidden="true" class="pointer-events-none absolute -top-20 left-1/2 h-64 w-[32rem] -translate-x-1/2 rounded-full bg-[radial-gradient(ellipse,rgba(212,175,55,0.16),transparent_65%)]"></span>
                <span class="relative flex h-16 w-16 -rotate-6 items-center justify-center rounded-full text-xl font-bold text-[var(--gold-ink)] shadow-lg"
                      style="background: radial-gradient(circle at 35% 30%, var(--gold-3), var(--gold-lit) 55%, var(--gold-2)); font-family: Georgia, serif;">EB</span>
                <div class="relative">
                    <p class="text-lg font-bold text-white">No documents yet</p>
                    <p class="mx-auto mt-1 max-w-[44ch] text-sm text-white/60">
                        Every agreement for this event lives here — the client contract, vendor and
                        speaker agreements, sponsorships and letters.
                    </p>
                </div>
                @can('manage-contract')
                    <button type="button" wire:click="$set('showNew', true)" class="eo-btn-primary btn-sm relative">＋ Create your first document</button>
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
                            $plaqueTone = [
                                'draft' => 'text-white/40', 'sent' => 'text-amber-300',
                                'partially_signed' => 'text-sky-300', 'signed' => 'text-emerald-300',
                                'void' => 'text-white/30',
                            ];
                            $plaqueStatus = [
                                \App\Support\Workflow::label('contract_status', $c->status),
                                $plaqueTone[$c->status] ?? 'text-white/40',
                            ];
                        @endphp
                        <div class="group relative w-[248px]" wire:key="doc-{{ $c->id }}">
                            <div role="button" tabindex="0"
                                 wire:click="selectContract({{ $c->id }})"
                                 @keydown.enter="$wire.selectContract({{ $c->id }})"
                                 class="relative cursor-pointer focus:outline-none">

                                {{-- the paper itself, resting on the table --}}
                                <div class="relative overflow-hidden rounded-[3px] shadow-[0_20px_45px_-18px_rgba(0,0,0,0.7)] ring-1 ring-white/10 transition duration-300 group-hover:-translate-y-2 group-hover:shadow-[0_34px_65px_-20px_rgba(0,0,0,0.85)] group-hover:ring-eo-teal/70 group-focus-visible:ring-2 group-focus-visible:ring-eo-teal">
                                    @include('event-contract.mini', ['c' => $c, 'event' => $event, 'scale' => 0.3875, 'window' => 296])
                                    {{-- the page falls away into the table's shadow --}}
                                    <span aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-navy-900/60 to-transparent"></span>

                                    {{-- delete on hover — any document, client included --}}
                                    @can('manage-contract')
                                        <x-confirm title="Delete “{{ $c->displayTitle() }}”?"
                                                   body="This removes the document and its signatures for good."
                                                   confirm="Delete" run="$wire.deleteContract({{ $c->id }})"
                                                   class="absolute right-1.5 top-1.5 z-10 rounded-md bg-eo-navy/60 px-1.5 py-0.5 text-eyebrow font-bold text-white/70 opacity-0 backdrop-blur-sm transition hover:bg-red-600/80 hover:text-white group-hover:opacity-100">✕</x-confirm>
                                    @endcan

                                    {{-- wax seal over the paper's corner when fully signed --}}
                                    @if ($c->status === 'signed')
                                        <span class="absolute -right-1 bottom-3 z-10 flex h-11 w-11 -rotate-6 items-center justify-center rounded-full font-bold text-[var(--gold-ink)] shadow-lg transition group-hover:rotate-0"
                                              style="background: radial-gradient(circle at 35% 30%, var(--gold-3), var(--gold-lit) 55%, var(--gold-2)); font-family: Georgia, serif;"
                                              title="Signed &amp; sealed">EB</span>
                                    @endif
                                </div>

                                {{-- the engraved plaque beneath the paper --}}
                                <div class="mt-3 border-t border-white/15 pt-2.5">
                                    <div class="flex items-baseline justify-between gap-2">
                                        <p class="truncate text-3xs font-bold uppercase tracking-[0.28em] text-white/50">{{ $tLabel }}</p>
                                        <p class="shrink-0 text-3xs font-bold uppercase tracking-[0.14em] {{ $plaqueStatus[1] }}">{{ $plaqueStatus[0] }}</p>
                                    </div>
                                    @if ($cardTitle)
                                        <p class="mt-0.5 truncate text-sm font-bold text-white">{{ $cardTitle }}</p>
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
                                                            <svg viewBox="0 0 24 10" class="h-2 w-5 text-emerald-300"><path d="M1 7 C4 1.5, 7 9, 10 4.5 S 15.5 2, 17.5 6 S 22 3.5, 23 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
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
                                class="group/new relative flex h-[296px] w-[248px] flex-col items-center justify-center gap-2 rounded-[3px] border-2 border-dashed border-white/15 text-white/40 transition hover:border-eo-teal/60 hover:text-eo-teal-lit">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-dashed border-white/25 text-lg font-light transition group-hover/new:rotate-90 group-hover/new:border-eo-teal/70">＋</span>
                            <span class="text-eyebrow font-bold uppercase tracking-[0.22em]">New document</span>
                        </button>
                    @endcan
                </div>
            </div>

            {{-- ══ THE PIPELINE: the same table, ruled into three neutrally-numbered
                 stages. Documents are white paper slips you slide along it. ══ --}}
            <div x-show="view === 'pipe'" x-cloak class="strip-dark relative !rounded-2xl p-5 sm:p-6" data-pipeline>
                <span aria-hidden="true" class="pointer-events-none absolute -top-28 left-1/2 h-80 w-[42rem] -translate-x-1/2 rounded-full bg-[radial-gradient(ellipse,rgba(212,175,55,0.12),transparent_65%)]"></span>

                <div class="relative grid gap-6 sm:grid-cols-3">
                    @foreach (['draft' => 'Draft', 'sent' => 'Sent for signature', 'signed' => 'Signed'] as $col => $colLabel)
                        @php $inCol = $contracts->filter(fn ($c) => $c->pipelineColumn() === $col); @endphp
                        <div data-col="{{ $col }}" @class(['sm:border-r sm:border-white/10 sm:pr-6' => ! $loop->last])>
                            {{-- the engraved stage marker --}}
                            <div class="flex items-baseline gap-2.5 pb-3">
                                <span class="text-lg font-black leading-none text-white/40">{{ sprintf('%02d', $loop->iteration) }}</span>
                                <span class="text-eyebrow font-bold uppercase tracking-[0.22em] text-white/75">{{ $colLabel }}</span>
                                <span class="ml-auto flex h-5 min-w-5 items-center justify-center rounded-full px-1 text-3xs font-bold text-white/70 ring-1 ring-white/20">{{ $inCol->count() }}</span>
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
                                         class="group/pc relative cursor-grab overflow-hidden rounded-[3px] bg-white shadow-[0_12px_28px_-12px_rgba(0,0,0,0.75)] ring-1 ring-white/10 transition duration-200 hover:-translate-y-0.5 hover:rotate-[-0.5deg] hover:ring-eo-teal/60 active:cursor-grabbing">
                                        <div class="px-3.5 py-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="block h-px w-6 bg-eo-line"></span>
                                                <span class="shrink-0 select-none text-xs leading-none tracking-tighter text-eo-line opacity-100 transition sm:opacity-0 sm:group-hover/pc:opacity-100" aria-hidden="true">⠿</span>
                                            </div>
                                            <p class="mt-1 truncate text-3xs font-bold uppercase tracking-[0.2em] text-eo-muted">{{ $tLabel }}</p>
                                            @if ($cardTitle)
                                                <p class="mt-0.5 truncate text-sm font-bold leading-snug text-eo-text">{{ $cardTitle }}</p>
                                            @else
                                                <p class="mt-0.5 truncate text-xs font-medium italic text-eo-muted">Untitled</p>
                                            @endif
                                            <div class="mt-2 flex items-end justify-between gap-2 border-t border-dashed border-eo-line/80 pt-1.5">
                                                <span class="truncate font-mono text-3xs text-eo-muted">{{ $c->reference }}</span>
                                                @if ($c->signatories_count > 0)
                                                    <span class="flex shrink-0 items-end gap-1" title="{{ $c->signed_count }} of {{ $c->signatories_count }} signed">
                                                        @for ($i = 0; $i < min($c->signatories_count, 4); $i++)
                                                            <span class="flex w-4 flex-col">
                                                                @if ($i < $c->signed_count)
                                                                    <svg viewBox="0 0 24 10" class="h-1.5 w-4 text-eo-text"><path d="M1 7 C4 1.5, 7 9, 10 4.5 S 15.5 2, 17.5 6 S 22 3.5, 23 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                                                @else
                                                                    <span class="mt-1 block border-b border-dashed border-eo-line"></span>
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
        // The event's currency, not a copy frozen into the document.
        $cur = $event->currency ?: \App\Models\CompanyProfile::currency();
        $est = $f['contract_value_cents'] ?? $f['estimated_total_cents'] ?? 0;
        $isFixed = ($f['value_mode'] ?? 'fixed') === 'fixed';
        $fmt = fn ($c) => $event->money((int) round($c ?? 0));
        [$tLabel] = $typeMeta[$type] ?? ['Document'];
        [$sLabel, $sChip] = $statusChip[$status] ?? ['Draft', 'bg-eo-bg text-eo-muted'];
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
            <div class="pointer-events-none absolute -right-8 -top-16 h-48 w-48 rounded-full bg-[radial-gradient(circle,rgba(30,172,172,0.22),transparent_70%)]"></div>

            <button type="button" wire:click="backToDeck"
                    class="relative flex h-9 items-center gap-1.5 rounded-xl bg-white/5 px-3 text-micro font-bold text-white/70 ring-1 ring-white/15 transition hover:text-white">
                ← Documents
            </button>

            <div class="relative min-w-0 flex-1">
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-white/60">{{ $tLabel }} · {{ $reference }}</p>
                <p class="truncate text-base font-semibold text-white">{{ $title ?: ($type === 'client' ? 'Management Services Agreement' : $tLabel) }}</p>
            </div>

            <div class="relative flex flex-wrap items-center gap-2">
                <span class="rounded-full bg-white/10 px-2.5 py-1 text-eyebrow font-bold uppercase tracking-wide text-white ring-1 ring-white/15">{{ $sLabel }}</span>
                <button type="button" wire:click="cycleStatus" class="h-9 rounded-xl bg-white/5 px-3 text-micro font-bold text-white/70 ring-1 ring-white/15 transition hover:text-white" title="Advance status">Advance →</button>

                @if ($dirty)
                    <x-confirm title="Discard your unsaved changes?" confirm="Discard" tone="warn" run="$wire.discard"
                               class="h-9 rounded-xl px-3 text-micro font-bold text-white/50 transition hover:text-white/90">Discard</x-confirm>
                @endif
                <button type="button" wire:click="save" @disabled(! $dirty) wire:loading.attr="disabled" wire:target="save"
                        @class([
                            'h-9 rounded-xl px-4 text-micro font-bold transition',
                            'bg-white text-eo-text shadow hover:brightness-95' => $dirty,
                            'bg-white/5 text-white/30 ring-1 ring-white/10' => ! $dirty,
                        ])>
                    <span wire:loading.remove wire:target="save">{{ $dirty ? 'Save changes' : 'Saved' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>

                @if ($type === 'client')
                    <a href="{{ route('events.contract.pdf', $event) }}" target="_blank"
                       title="{{ $dirty ? 'Exports the saved version — save first to include your edits' : 'Export the contract' }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-eo-teal px-4 text-micro font-bold text-white shadow transition hover:bg-eo-teal-deep">
                        ↧ Export PDF
                    </a>
                    <x-confirm title="Reset the contract to defaults?"
                               body="Your edits will be replaced."
                               confirm="Reset" tone="warn" run="$wire.resetContract"
                               class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-white/5 hover:text-white/80">↺</x-confirm>
                @else
                    {{-- Every other type exports through the shared document sheet. --}}
                    <a href="{{ route('events.contract.doc.pdf', [$event, $contractId]) }}" target="_blank"
                       title="{{ $dirty ? 'Exports the saved version — save first to include your edits' : 'Export this document' }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-eo-teal px-4 text-micro font-bold text-white shadow transition hover:bg-eo-teal-deep">
                        ↧ Export PDF
                    </a>
                @endif

                @can('manage-contract')
                    <x-confirm title="Delete this document and its signatures for good?"
                               confirm="Delete" run="$wire.deleteContract({{ $contractId }})"
                               class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-eo-risk/20 hover:text-red-300">🗑</x-confirm>
                @endcan
            </div>
        </div>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">

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
                                    <label class="eo-label !mb-1 !text-eyebrow">
                                        {{ ['vendor' => 'Supplier', 'speaker' => 'Speaker', 'sponsorship' => 'Sponsor'][$type] }} — from the event's list
                                    </label>
                                    <select wire:change="setParty($event.target.value)" class="eo-input h-10 w-full !py-0 text-sm">
                                        <option value="">— choose —</option>
                                        @foreach ($editPartyOptions as $opt)
                                            <option value="{{ $opt->id }}" @selected($contract->party_id === $opt->id)>{{ $opt->name }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            @elseif ($type === 'letter')
                                <div>
                                    <label class="eo-label !mb-1 !text-eyebrow">Recipient</label>
                                    <input type="text" wire:model.live.debounce.500ms="data.counterparty.name_en"
                                           placeholder="HE the Minister of Culture…" class="{{ $in }}">
                                </div>
                            @endif

                            {{-- type-specific details that print into the agreement --}}
                            @if ($type === 'speaker')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="eo-label !mb-1 !text-eyebrow">Session / topic</label>
                                        <input type="text" wire:model.live.debounce.500ms="data.counterparty.detail" placeholder="Keynote — AI in Events" class="{{ $in }}">
                                    </div>
                                    <div>
                                        <label class="eo-label !mb-1 !text-eyebrow">Honorarium ({{ $cur }})</label>
                                        <input type="number" min="0" step="0.01" value="{{ ($cp['fee_cents'] ?? null) !== null ? number_format(($cp['fee_cents'] ?? 0) / 100, 2, '.', '') : '' }}"
                                               wire:change="setPartyFee($event.target.value)" placeholder="—" class="{{ $in }} text-right">
                                    </div>
                                </div>
                            @elseif ($type === 'sponsorship')
                                <div class="grid gap-2 sm:grid-cols-2">
                                    <div>
                                        <label class="eo-label !mb-1 !text-eyebrow">Package / tier</label>
                                        <input type="text" wire:model.live.debounce.500ms="data.counterparty.package" placeholder="Gold" class="{{ $in }}">
                                    </div>
                                    <div>
                                        <label class="eo-label !mb-1 !text-eyebrow">Sponsorship amount ({{ $cur }})</label>
                                        <input type="number" min="0" step="0.01" value="{{ ($cp['fee_cents'] ?? null) !== null ? number_format(($cp['fee_cents'] ?? 0) / 100, 2, '.', '') : '' }}"
                                               wire:change="setPartyFee($event.target.value)" placeholder="—" class="{{ $in }} text-right">
                                    </div>
                                </div>
                            @elseif ($type === 'vendor')
                                <div>
                                    <label class="eo-label !mb-1 !text-eyebrow">Service category</label>
                                    <input type="text" wire:model.live.debounce.500ms="data.counterparty.detail" placeholder="production, catering, transport…" class="{{ $in }}">
                                </div>
                            @endif

                            <div class="flex flex-wrap items-center gap-2 border-t border-eo-line pt-2.5">
                                <span class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">Language</span>
                                @foreach (['en' => 'English', 'bilingual' => 'Bilingual EN/AR'] as $lk => $ll)
                                    <button type="button" wire:click="setLanguage('{{ $lk }}')"
                                            @class(['rounded-lg px-2.5 py-1 text-eyebrow font-bold transition', 'bg-eo-navy text-white' => $language === $lk, 'bg-eo-bg text-eo-muted hover:bg-eo-bg' => $language !== $lk])>{{ $ll }}</button>
                                @endforeach

                                @can('manage-contract')
                                    <x-confirm title="Rewrite the body from the standard {{ strtolower($tLabel) }} template with the current details?"
                                               body="Your edits to the body will be replaced."
                                               confirm="Rewrite" run="$wire.refillFromTemplate"
                                               class="ml-auto text-eyebrow font-bold uppercase tracking-wide text-eo-muted hover:text-eo-teal-ink">↻ Refill body</x-confirm>
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
                                        <p class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">{{ $split ? 'Second party '.($i + 1) : 'Second party' }}</p>
                                        @if (count($data['second_parties']) > 1)
                                            <button type="button" wire:click="removeSecondParty({{ $i }})"
                                                    class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted opacity-100 transition sm:opacity-0 hover:text-red-700 sm:group-hover/sp:opacity-100">Remove</button>
                                        @endif
                                    </div>
                                    <div class="grid gap-1.5 {{ $split ? 'grid-cols-[1fr_5rem]' : 'grid-cols-1' }}">
                                        <input type="text" wire:model.live.debounce.500ms="data.second_parties.{{ $i }}.name_en" placeholder="Entity name (English)" class="{{ $in }}">
                                        @if ($split)
                                            <div class="relative">
                                                <input type="number" min="0" max="100" wire:model.live.debounce.300ms="data.second_parties.{{ $i }}.share" class="{{ $in }} pr-6 text-center !font-bold">
                                                <span class="pointer-events-none absolute right-2.5 top-1/2 -translate-y-1/2 text-eyebrow font-semibold text-eo-muted">%</span>
                                            </div>
                                        @endif
                                    </div>
                                    <input type="text" dir="rtl" wire:model.live.debounce.500ms="data.second_parties.{{ $i }}.name_ar" placeholder="اسم الجهة بالعربية" class="{{ $inAr }}">

                                    {{-- The recital names the Client from the English
                                         field, and the English text is the controlling
                                         one. Without it the row cannot be bound, so it
                                         is told rather than silently dropped. --}}
                                    @if (trim((string) ($sp['name_en'] ?? '')) === '')
                                        <p class="text-eyebrow text-eo-muted">
                                            No English name — this row will not appear on the document.
                                            @if (trim((string) ($sp['name_ar'] ?? '')) !== '')
                                                Add one, or remove the row.
                                            @endif
                                        </p>
                                    @endif
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between border-t border-eo-line pt-2.5">
                                @if ($split)
                                    @php $shareOk = abs($shareTotal - 100) < 0.001; @endphp
                                    <span class="text-micro font-bold {{ $shareOk ? 'text-emerald-600' : 'text-eo-risk-ink' }}">
                                        {{ rtrim(rtrim(number_format($shareTotal, 2), '0'), '.') }}%
                                        @unless ($shareOk)<span class="font-semibold"> — must equal 100%</span>@endunless
                                    </span>
                                @else
                                    <span class="text-micro text-eo-muted">One client — no cost split, so no shares.</span>
                                @endif
                                <button type="button" wire:click="addSecondParty" class="eo-btn-ghost btn-xs">＋ Add party</button>
                            </div>
                        </div>
                    </x-accordion-section>

                    {{-- Value & schedule module --}}
                    <x-accordion-section id="value-and-payments" num="02" title="Value & Payments" summary="{{ $fmt($est) }} · {{ count($f['payment_schedule'] ?? []) }} installments">
                        <div class="space-y-3">
                            <div class="rounded-xl bg-eo-teal-soft/40 p-3">
                                <div class="flex items-center gap-2">
                                    <span class="text-eyebrow font-bold text-eo-muted">{{ $cur }}</span>
                                    <input type="number" min="0" step="0.001" value="{{ number_format($est / 100, 3, '.', '') }}"
                                           wire:change="setContractValue($event.target.value)"
                                           class="{{ $in }} flex-1 !bg-white text-right !text-base !font-black">
                                </div>
                                <div class="mt-2 flex items-center justify-between gap-2">
                                    <div class="flex rounded-lg border border-eo-line bg-white p-0.5">
                                        @foreach (['fixed' => 'Fixed price', 'estimate' => 'Estimate'] as $mode => $ml)
                                            <button type="button" wire:click="setValueMode('{{ $mode }}')"
                                                    @class(['rounded-md px-2 py-1 text-eyebrow font-bold transition', 'bg-eo-navy text-white' => ($f['value_mode'] ?? 'fixed') === $mode, 'text-eo-muted hover:text-eo-text' => ($f['value_mode'] ?? 'fixed') !== $mode])>{{ $ml }}</button>
                                        @endforeach
                                    </div>
                                    {{-- The figure is on the button, so a pull is never a
                                         mystery: you can see what is coming before it comes. --}}
                                    @php $fromBudget = $event->costForecast(); @endphp
                                    <x-confirm title="Copy {{ $fromBudget['forecast'] ? $event->money($fromBudget['forecast']) : 'the budget' }} into the contract value?"
                                               body="This overwrites the figure once — it does not link them."
                                               confirm="Copy" tone="neutral" run="$wire.syncBudget"
                                               class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted hover:text-eo-teal-ink">
                                        ↻ From budget @if ($fromBudget['forecast'])· {{ $event->money($fromBudget['forecast']) }}@endif
                                    </x-confirm>
                                </div>

                                @if ($budgetFlash)
                                    <p class="mt-1.5 text-[11px] font-semibold text-eo-muted">{{ $budgetFlash }}</p>
                                @endif

                                <p class="mt-1.5 font-mono text-eyebrow text-eo-muted">
                                    Quoted live in the body wherever a clause reads <b>&#123;&#123;value&#125;&#125;</b> — change it here and every one of them follows, no regeneration needed.
                                </p>
                            </div>

                            @php $totalPct = collect($f['payment_schedule'] ?? [])->sum(fn ($s) => (float) ($s['pct'] ?? 0)); @endphp
                            @foreach ($f['payment_schedule'] ?? [] as $i => $s)
                                <div wire:key="inst-{{ $i }}" class="group/inst space-y-1.5 border-t border-eo-line/70 pt-2.5">
                                    <div class="flex items-center gap-2">
                                        <span class="flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-eo-navy text-eyebrow font-black text-white">{{ $i + 1 }}</span>
                                        <div class="relative w-20 shrink-0">
                                            <input type="number" min="0" max="100" step="0.01" wire:model.live.debounce.300ms="data.financials.payment_schedule.{{ $i }}.pct" class="{{ $in }} pr-6 text-center !font-bold">
                                            <span class="pointer-events-none absolute right-2 top-1/2 -translate-y-1/2 text-eyebrow font-semibold text-eo-muted">%</span>
                                        </div>
                                        <span class="flex-1 text-right text-micro font-semibold text-eo-muted">{{ $fmt($est * (float) ($s['pct'] ?? 0) / 100) }}</span>
                                        @if (count($f['payment_schedule']) > 1)
                                            <button type="button" wire:click="removeInstallment({{ $i }})"
                                                    class="shrink-0 rounded-md px-1.5 py-1 text-eyebrow font-bold text-eo-muted opacity-100 transition sm:opacity-0 hover:bg-eo-risk/10 hover:text-red-700 sm:group-hover/inst:opacity-100">✕</button>
                                        @endif
                                    </div>
                                    <div class="grid gap-1.5 sm:grid-cols-2">
                                        <input type="text" wire:model.live.debounce.500ms="data.financials.payment_schedule.{{ $i }}.when_en" placeholder="Due — English" class="{{ $in }} !py-1.5 !text-xs">
                                        <input type="text" dir="rtl" wire:model.live.debounce.500ms="data.financials.payment_schedule.{{ $i }}.when_ar" placeholder="الاستحقاق — بالعربية" class="{{ $inAr }} !py-1.5 !text-xs">
                                    </div>
                                </div>
                            @endforeach
                            <div class="flex items-center justify-between border-t border-eo-line pt-2.5">
                                <span class="text-micro font-bold {{ abs($totalPct - 100) < 0.001 ? 'text-emerald-600' : 'text-eo-risk-ink' }}">Total {{ rtrim(rtrim(number_format($totalPct, 2), '0'), '.') }}%</span>
                                <span class="flex gap-2">
                                    <button type="button" wire:click="balanceInstallments" class="eo-btn-ghost btn-xs" title="Spread 100% evenly">⚖ Balance</button>
                                    <button type="button" wire:click="addInstallment" class="eo-btn-ghost btn-xs">＋ Add installment</button>
                                </span>
                            </div>
                        </div>
                    </x-accordion-section>

                    {{-- Assumptions module --}}
                    <x-accordion-section id="budget-assumptions" num="03" title="Budget Assumptions" summary="What the estimate is based on">
                        <div class="grid gap-2.5 sm:grid-cols-2">
                            <div><label class="eo-label !mb-1 !text-eyebrow">Attendees — from</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.attendees_min" class="{{ $in }}"></div>
                            <div><label class="eo-label !mb-1 !text-eyebrow">Attendees — to</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.attendees_max" class="{{ $in }}"></div>
                            <div><label class="eo-label !mb-1 !text-eyebrow">Rooms</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.rooms" class="{{ $in }}"></div>
                            <div><label class="eo-label !mb-1 !text-eyebrow">Nights per guest</label><input type="number" wire:model.live.debounce.500ms="data.assumptions.nights" class="{{ $in }}"></div>
                            <div class="sm:col-span-2"><label class="eo-label !mb-1 !text-eyebrow">Catering (English)</label><input type="text" wire:model.live.debounce.500ms="data.assumptions.catering_en" class="{{ $in }}"></div>
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
                            <div class="flex items-center gap-2 rounded-xl bg-eo-teal-soft/60 px-3 py-2 text-eyebrow">
                                <button type="button" wire:click="editAppendix(null)" class="font-bold text-eo-teal-deep hover:underline">Body</button>
                                <span class="text-eo-muted">/</span>
                                <span class="font-bold text-eo-text">Appendix {{ $axNumber }} · {{ $editingAx['title_en'] ?: 'Untitled' }}</span>
                                <button type="button" wire:click="editAppendix(null)" class="ms-auto eo-btn-ghost btn-xs">← Back to the body</button>
                            </div>
                        @endif

                        @can('manage-contract')
                            <div class="flex items-center justify-end gap-2">
                                @unless ($editingAx)
                                    <x-confirm title="Restore the standard clause set?"
                                               body="Any edits to the body will be replaced."
                                               confirm="Restore" tone="warn" run="$wire.restoreStandardBlocks"
                                               class="eo-btn-ghost btn-xs">↺ Restore standard</x-confirm>
                                @endunless
                                <button type="button" wire:click="addBlock" class="eo-btn-primary btn-xs">＋ Add section</button>
                            </div>
                        @endcan

                        @forelse ($editBlocks as $bi => $b)
                            <div wire:key="blk-{{ $b['id'] }}" class="rounded-2xl bg-eo-workspace/50 p-3">
                                <div class="flex items-start gap-2.5">
                                    <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-eo-navy text-eyebrow font-black text-white">{{ $bi + 1 }}</span>
                                    <div class="grid min-w-0 flex-1 gap-1.5">
                                        <label class="block">
                                            <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-eo-bg px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-eo-muted">EN</span>
                                            <input type="text" value="{{ $b['title_en'] ?? '' }}" placeholder="Clause title (English)"
                                                   wire:change="updateBlockField('{{ $b['id'] }}', 'title_en', $event.target.value)"
                                                   class="{{ $in }} !bg-white !py-1.5 !text-xs !font-bold">
                                        </label>
                                        <label class="block">
                                            <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-eo-teal-soft px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-eo-teal-deep">AR</span>
                                            <input type="text" dir="rtl" value="{{ $b['title_ar'] ?? '' }}" placeholder="عنوان البند (بالعربية)"
                                                   wire:change="updateBlockField('{{ $b['id'] }}', 'title_ar', $event.target.value)"
                                                   class="{{ $inAr }} !bg-white !py-1.5 !text-xs !font-bold ring-1 ring-eo-teal/25">
                                        </label>
                                    </div>
                                    @can('manage-contract')
                                        <div class="flex shrink-0 flex-col items-center gap-0.5">
                                            <button type="button" wire:click="moveBlock('{{ $b['id'] }}', -1)" @disabled($bi === 0)
                                                    class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted hover:bg-eo-bg hover:text-eo-text disabled:opacity-25" title="Move up">↑</button>
                                            <button type="button" wire:click="moveBlock('{{ $b['id'] }}', 1)" @disabled($bi === count($data['blocks']) - 1)
                                                    class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted hover:bg-eo-bg hover:text-eo-text disabled:opacity-25" title="Move down">↓</button>
                                            <x-confirm title="Delete “{{ $b['title_en'] ?? 'this clause' }}” from this contract?"
                                                       confirm="Delete" run="$wire.deleteBlock('{{ $b['id'] }}')"
                                                       class="rounded-md px-1.5 py-0.5 text-eyebrow font-bold text-eo-muted hover:bg-eo-risk/10 hover:text-red-700">✕</x-confirm>
                                        </div>
                                    @endcan
                                </div>

                                <div class="mt-2 space-y-2">
                                    @foreach ($b['en'] ?? [] as $p => $para)
                                        <div class="group/para grid gap-1.5">
                                            <div>
                                                <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-eo-bg px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-eo-muted">EN</span>
                                                <textarea rows="2" placeholder="English text…"
                                                          wire:change="updateParagraph('{{ $b['id'] }}', 'en', {{ $p }}, $event.target.value)"
                                                          class="{{ $in }} !bg-white !py-2 !text-xs leading-relaxed">{{ $para }}</textarea>
                                            </div>
                                            <div class="relative">
                                                <span class="mb-1 inline-flex items-center gap-1 rounded-md bg-eo-teal-soft px-1.5 py-px text-[9px] font-bold uppercase tracking-wide text-eo-teal-deep">AR</span>
                                                <textarea rows="2" dir="rtl" placeholder="النص بالعربية…"
                                                          wire:change="updateParagraph('{{ $b['id'] }}', 'ar', {{ $p }}, $event.target.value)"
                                                          class="{{ $inAr }} !bg-white !py-2 !text-xs leading-relaxed ring-1 ring-eo-teal/25">{{ $b['ar'][$p] ?? '' }}</textarea>
                                                @if (count($b['en']) > 1)
                                                    <button type="button" wire:click="removeParagraph('{{ $b['id'] }}', {{ $p }})"
                                                            class="absolute -right-1 -top-1 hidden h-5 w-5 items-center justify-center rounded-full bg-white text-eyebrow font-bold text-eo-muted shadow ring-1 ring-line hover:text-red-700 group-hover/para:flex"
                                                            title="Remove this paragraph pair">✕</button>
                                                @endif
                                            </div>
                                        </div>
                                    @endforeach
                                    <button type="button" wire:click="addParagraph('{{ $b['id'] }}')"
                                            class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted hover:text-eo-teal-ink">＋ Add paragraph</button>
                                </div>

                                @if (in_array($b['type'] ?? '', ['bullets', 'list'], true))
                                    <div class="mt-2.5 space-y-1.5 border-t border-eo-line pt-2.5">
                                        <p class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted">{{ ($b['type'] === 'bullets') ? 'Items' : 'Policy rows' }} · {{ count($b['items'] ?? []) }}</p>
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
                                                        class="mt-1 text-eyebrow font-bold uppercase tracking-wide text-eo-muted opacity-100 transition sm:opacity-0 hover:text-red-700 sm:group-hover/itm:opacity-100">Remove row</button>
                                            </div>
                                        @endforeach
                                        <button type="button" wire:click="addItem('{{ $b['id'] }}')"
                                                class="text-eyebrow font-bold uppercase tracking-wide text-eo-muted hover:text-eo-teal-ink">＋ Add item</button>
                                    </div>
                                @elseif (($b['type'] ?? '') !== 'prose')
                                    <p class="mt-2 rounded-lg bg-eo-bg/70 px-2.5 py-1.5 text-eyebrow text-eo-muted">
                                        @switch($b['type'])
                                            @case('costshare') Followed by the cost-share table — edit the entities in <b>Parties</b>. @break
                                            @case('schedule') Followed by the payment table — edit it in <b>Value &amp; Payments</b>. @break
                                        @endswitch
                                    </p>
                                @endif
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-eo-line px-4 py-8 text-center text-xs text-eo-muted">
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
                                <p class="rounded-xl bg-eo-risk/10 px-3 py-2 text-eyebrow font-bold text-red-700">
                                    ⚠ The text refers to {{ implode(', ', $broken) }}, which no longer exists. Fix the reference or restore the appendix — the PDF will not export until you do.
                                </p>
                            @endif

                            @forelse ($axList as $ai => $ax)
                                <div wire:key="ax-{{ $ax['slug'] }}"
                                     @class(['rounded-2xl p-3', 'bg-eo-teal-soft/60 ring-1 ring-eo-teal/30' => $editingAppendix === $ax['slug'], 'bg-eo-workspace/50' => $editingAppendix !== $ax['slug']])>
                                    <div class="flex items-start gap-2.5">
                                        <span class="mt-1 flex h-6 w-6 shrink-0 items-center justify-center rounded-lg bg-eo-navy text-eyebrow font-black text-white">{{ $ai + 1 }}</span>
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
                                                <button type="button" wire:click="moveAppendix('{{ $ax['slug'] }}', -1)" @disabled($ai === 0) class="eo-btn-ghost btn-xs disabled:opacity-25">↑</button>
                                                <button type="button" wire:click="moveAppendix('{{ $ax['slug'] }}', 1)" @disabled($ai === count($axList) - 1) class="eo-btn-ghost btn-xs disabled:opacity-25">↓</button>
                                                <x-confirm title="Remove this appendix?"
                                                           body="Any reference to it in the contract text will break until you fix it."
                                                           confirm="Remove" run="$wire.deleteAppendix('{{ $ax['slug'] }}')"
                                                           class="eo-btn-ghost btn-xs text-eo-risk">✕</x-confirm>
                                            </div>
                                        @endcan
                                    </div>

                                    <div class="mt-2 flex flex-wrap items-center gap-1.5">
                                        <span class="rounded-md bg-white px-2 py-0.5 text-eyebrow font-bold text-eo-muted ring-1 ring-line">
                                            {{ count($ax['blocks'] ?? []) }} {{ \Illuminate\Support\Str::plural('section', count($ax['blocks'] ?? [])) }}
                                        </span>
                                        @if ($ax['source'] ?? null)
                                            <span class="rounded-md bg-eo-navy px-2 py-0.5 text-eyebrow font-bold text-white/70">
                                                {{ ['budget' => 'Budget', 'agenda' => 'Agenda', 'venue' => 'Venue', 'brief' => 'Brief', 'form' => 'Form', 'typed' => 'Typed'][$ax['source']] ?? $ax['source'] }}
                                            </span>
                                        @endif
                                        @if ($ax['pulled_at'] ?? null)
                                            <span class="text-eyebrow text-eo-muted">pulled {{ \Illuminate\Support\Carbon::parse($ax['pulled_at'])->diffForHumans() }}</span>
                                        @endif

                                        <span class="ms-auto flex gap-1.5">
                                            @if (($ax['source'] ?? null) && ! in_array($ax['source'], ['typed', 'form'], true))
                                                @can('manage-contract')
                                                    <x-confirm title="Replace this appendix with a fresh snapshot from the module?"
                                                               body="Anything typed here will be lost."
                                                               confirm="Replace" run="$wire.pullAppendix('{{ $ax['slug'] }}')"
                                                               class="eo-btn-ghost btn-xs">⇣ {{ ($ax['pulled_at'] ?? null) ? 'Refresh' : 'Pull' }}</x-confirm>
                                                @endcan
                                            @endif
                                            <button type="button" wire:click="editAppendix('{{ $ax['slug'] }}')" class="eo-btn-ghost btn-xs">Open →</button>
                                        </span>
                                    </div>

                                    <p class="mt-1.5 font-mono text-eyebrow text-eo-muted">
                                        Refer to it in the text as <b>&#123;&#123;appendix:{{ $ax['slug'] }}&#125;&#125;</b> — never as “Appendix {{ $ai + 1 }}”.
                                    </p>
                                </div>
                            @empty
                                <p class="rounded-xl border border-dashed border-eo-line px-4 py-8 text-center text-xs text-eo-muted">
                                    No appendices. The scope, the budget and the programme all belong here.
                                </p>
                            @endforelse

                            @can('manage-contract')
                                <div class="rounded-2xl border border-dashed border-eo-line p-3">
                                    <p class="eyebrow mb-2">Add an appendix</p>
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach (\App\Support\ContractAppendices::LIBRARY as $key => [$label, $labelAr, $src])
                                            <button type="button" wire:click="addAppendix('{{ $key }}')" class="eo-btn-ghost btn-xs">＋ {{ $label }}</button>
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
                                 @class(['group flex flex-wrap items-center gap-2 rounded-xl px-3 py-2.5', 'bg-emerald-50/60' => $s->isSigned(), 'bg-eo-workspace/50' => ! $s->isSigned()])>
                                <span @class(['flex h-9 w-9 shrink-0 items-center justify-center rounded-full text-xs font-black', 'bg-gold-500 text-eo-text' => $s->isSigned(), 'bg-white text-eo-muted ring-1 ring-line' => ! $s->isSigned()])>{{ $s->initials() }}</span>
                                <div class="min-w-[9rem] flex-1">
                                    <input type="text" value="{{ $s->name }}" placeholder="Signatory name" @disabled($s->isSigned())
                                           wire:change="updateSignatory({{ $s->id }}, 'name', $event.target.value)"
                                           class="w-full rounded-lg border border-transparent bg-transparent px-1.5 py-0.5 text-sm font-bold text-eo-text hover:border-eo-line focus:border-eo-teal focus:bg-white focus:outline-none disabled:opacity-70">
                                    <div class="flex items-center gap-2 px-1.5">
                                        <select wire:change="updateSignatory({{ $s->id }}, 'role', $event.target.value)" @disabled($s->isSigned())
                                                class="border-0 bg-transparent p-0 text-eyebrow font-semibold text-eo-muted focus:ring-0 disabled:opacity-70">
                                            @foreach (\App\Support\Taxonomy::options('signatory_role') as $rk => $rl)<option value="{{ $rk }}" @selected($s->role === $rk)>{{ $rl }}</option>@endforeach
                                        </select>
                                        @if ($s->isSigned())
                                            <span class="text-eyebrow font-semibold text-emerald-700">✓ {{ $s->signed_at->format('j M Y · H:i') }}</span>
                                        @endif
                                    </div>
                                </div>
                                @can('manage-contract')
                                    @if ($s->isSigned())
                                        <button type="button" wire:click="unsign({{ $s->id }})" class="rounded-lg px-2.5 py-1.5 text-eyebrow font-bold uppercase tracking-wide text-eo-muted hover:text-eo-text" title="Undo this signature">↺ Unsign</button>
                                    @else
                                        <x-confirm title="Record {{ $s->name ?: 'this party' }} as having signed?"
                                                   body="This stamps the date and locks their name to the current document."
                                                   confirm="Record" tone="neutral" run="$wire.recordSignature({{ $s->id }})"
                                                   :disabled="! $s->name"
                                                   class="rounded-lg bg-eo-navy px-3 py-1.5 text-eyebrow font-bold text-white transition hover:bg-eo-navy-mid disabled:opacity-40">✒️ Mark signed</x-confirm>
                                        <button type="button" wire:click="removeSignatory({{ $s->id }})"
                                                class="rounded-lg px-1.5 py-1.5 text-eyebrow font-bold text-eo-muted opacity-100 transition sm:opacity-0 hover:text-red-700 sm:group-hover:opacity-100" title="Remove signatory">✕</button>
                                    @endif
                                @endcan
                            </div>
                        @empty
                            <p class="rounded-xl border border-dashed border-eo-line px-4 py-6 text-center text-xs text-eo-muted">No signatories yet.</p>
                        @endforelse
                        @can('manage-contract')
                            <button type="button" wire:click="addSignatory" class="eo-btn-ghost btn-xs">＋ Add signatory</button>
                        @endcan
                        <div class="border-t border-eo-line pt-2.5">
                            <label class="eo-label !mb-1 !text-eyebrow">Reference</label>
                            <input type="text" wire:model.live.debounce.500ms="reference" class="{{ $in }} font-mono !text-xs">
                        </div>
                    </div>
                </x-accordion-section>
            </x-accordion>

            {{-- ══════════ RIGHT · the living paper ══════════ --}}
            <div class="xl:sticky xl:top-12">
                <div class="rounded-3xl bg-eo-navy/[0.05] p-3 ring-1 ring-line sm:p-5">
                    <div class="mb-2 flex items-center justify-between px-1">
                        <span class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-eo-muted">
                            <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-emerald-500"></span> Live preview
                        </span>
                        <span class="text-eyebrow text-eo-muted">{{ $bilingual ? 'English · العربية' : 'English' }}</span>
                    </div>

                    <div class="max-h-[calc(100vh-220px)] overflow-y-auto rounded-lg">
                        {{-- One source of truth: this very partial is what the PDF
                             renders, with the same compiled CSS. WYSIWYG for real. --}}
                        @include('event-contract.paper', [
                            'tLabel' => $tLabel, 'type' => $type, 'title' => $title, 'data' => $data,
                            'bilingual' => $bilingual, 'event' => $event, 'fmt' => $fmt, 'est' => $est,
                            'f' => $f, 'signatories' => $signatories, 'reference' => $reference,
                            'status' => $status, 'fullySigned' => $fullySigned, 'forPdf' => false,
                            'appendices' => $data['appendices'] ?? [],
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
        <div class="pointer-events-auto flex items-center gap-3 rounded-2xl bg-eo-navy px-4 py-2.5 text-white shadow-float ring-1 ring-white/10">
            <span class="flex items-center gap-2 text-xs font-semibold text-amber-200">
                <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span> Unsaved changes
            </span>
            <x-confirm title="Discard your unsaved changes?" confirm="Discard" tone="warn" run="$wire.discard"
                       class="rounded-lg px-3 py-1.5 text-xs font-bold text-white/55 transition hover:text-white">Discard</x-confirm>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-lg bg-white px-5 py-1.5 text-xs font-black text-eo-text shadow transition hover:brightness-95">
                <span wire:loading.remove wire:target="save">Save changes</span>
                <span wire:loading wire:target="save">Saving…</span>
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
