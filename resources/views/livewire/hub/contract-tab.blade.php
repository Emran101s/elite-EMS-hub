@php
    // ── Shared identity: the type's label, chip and document spine ──
    $typeMeta = [
        'client' => ['Client Contract', 'bg-sky-100 text-sky-700', 'linear-gradient(var(--ion),var(--ion-lit))'],
        'vendor' => ['Vendor Agreement', 'bg-emerald-100 text-emerald-700', 'linear-gradient(var(--vital),var(--vital-lit))'],
        'speaker' => ['Speaker Agreement', 'bg-page text-ink', 'linear-gradient(var(--plasma),var(--plasma-lit))'],
        'sponsorship' => ['Sponsorship', 'bg-gold-100 text-gold-800', 'linear-gradient(var(--gold-2),var(--gold))'],
        'letter' => ['Letter', 'bg-page text-muted', 'linear-gradient(var(--chrome-2),var(--chrome))'],
        'acceptance' => ['Certificate of Services', 'bg-emerald-100 text-emerald-700', 'linear-gradient(var(--vital),var(--vital-lit))'],
    ];
    // Same status meta the register uses — this editor and the register showed
    // the same status in two different colours before they shared it.
    $statusChip = \App\Models\EventContract::statusMeta();

    // The new input language: quiet fills that light up on focus — no boxed grid.
    $in = 'w-full rounded-xl border border-transparent bg-page/70 px-3 py-2 text-sm font-medium text-ink placeholder:text-muted transition focus:border-navy-300 focus:bg-white focus:outline-none';
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
                <p class="text-eyebrow font-bold uppercase tracking-[0.28em] text-gold-700">Contract Studio</p>
                <h2 class="text-h1 font-bold leading-tight text-ink">Documents</h2>
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

                <div class="ml-auto flex rounded-xl border border-line bg-white p-0.5">
                    @foreach (['deck' => 'The Deck', 'pipe' => 'Pipeline'] as $v => $vl)
                        <button type="button" @click="view = '{{ $v }}'"
                                :class="view === '{{ $v }}' ? 'bg-navy-900 text-white' : 'text-muted hover:text-ink'"
                                class="rounded-lg px-3 py-1.5 text-eyebrow font-bold transition">{{ $vl }}</button>
                    @endforeach
                </div>
                @can('manage-contract')
                    <button type="button" wire:click="$toggle('showNew')" class="rounded-full bg-navy-900 px-3.5 py-2 text-xs font-bold text-white transition hover:bg-navy-800">＋ New document</button>
                @endcan
            @endif
        </div>

        {{-- new-document panel --}}
        @if ($showNew)
            <div class="flex flex-wrap items-end gap-2 rounded-lg border border-gold-200 bg-gold-50 p-4">
                <div>
                    <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">Type</label>
                    <select wire:model.live="newType" class="h-10 w-auto rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                        @foreach (\App\Models\EventContract::TYPES as $key => $meta)<option value="{{ $key }}">{{ $meta['label'] }}</option>@endforeach
                    </select>
                </div>
                @if (in_array($newType, ['vendor', 'speaker', 'sponsorship'], true))
                    <div>
                        <label class="mb-1 block text-eyebrow font-bold uppercase tracking-[0.12em] text-muted">{{ ['vendor' => 'Supplier', 'speaker' => 'Speaker', 'sponsorship' => 'Sponsor'][$newType] }}</label>
                        <select wire:model="newPartyId" class="h-10 w-auto min-w-[12rem] rounded-lg border border-line bg-white px-2.5 text-sm text-ink focus:border-navy-300 focus:outline-none">
                            <option value="">— choose (optional) —</option>
                            @foreach ($partyOptions as $opt)<option value="{{ $opt->id }}">{{ $opt->name }}</option>@endforeach
                        </select>
                    </div>
                @endif
                <button type="button" wire:click="createContract" class="rounded-full bg-navy-900 px-3.5 py-2 text-xs font-bold text-white transition hover:bg-navy-800">Create &amp; open</button>
                <button type="button" wire:click="$set('showNew', false)" class="rounded-full border border-line bg-white px-3.5 py-2 text-xs font-semibold text-ink transition hover:border-navy-300">Cancel</button>
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
                    <button type="button" wire:click="$set('showNew', true)" class="relative rounded-full bg-gold-500 px-3.5 py-2 text-xs font-bold text-navy-900 shadow-raise transition hover:bg-gold-400">＋ Create your first document</button>
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
                                <div class="relative overflow-hidden rounded-[3px] shadow-[0_20px_45px_-18px_rgba(0,0,0,0.7)] ring-1 ring-white/10 transition duration-300 group-hover:-translate-y-2 group-hover:shadow-[0_34px_65px_-20px_rgba(0,0,0,0.85)] group-hover:ring-gold-400/70 group-focus-visible:ring-2 group-focus-visible:ring-gold-400">
                                    @include('event-contract.mini', ['c' => $c, 'event' => $event, 'scale' => 0.3875, 'window' => 296])
                                    {{-- the page falls away into the table's shadow --}}
                                    <span aria-hidden="true" class="pointer-events-none absolute inset-x-0 bottom-0 h-14 bg-gradient-to-t from-navy-900/60 to-transparent"></span>

                                    {{-- delete on hover — any document, client included --}}
                                    @can('manage-contract')
                                        <x-confirm title="Delete “{{ $c->displayTitle() }}”?"
                                                   body="This removes the document and its signatures for good."
                                                   confirm="Delete" run="$wire.deleteContract({{ $c->id }})"
                                                   class="absolute right-1.5 top-1.5 z-10 rounded-md bg-navy-900/60 px-1.5 py-0.5 text-eyebrow font-bold text-white/70 opacity-0 backdrop-blur-sm transition hover:bg-red-600/80 hover:text-white group-hover:opacity-100">✕</x-confirm>
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
                                class="group/new relative flex h-[296px] w-[248px] flex-col items-center justify-center gap-2 rounded-[3px] border-2 border-dashed border-white/15 text-white/40 transition hover:border-gold-400/60 hover:text-gold-300">
                            <span class="flex h-10 w-10 items-center justify-center rounded-full border border-dashed border-white/25 text-lg font-light transition group-hover/new:rotate-90 group-hover/new:border-gold-400/70">＋</span>
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
                                         class="group/pc relative cursor-grab overflow-hidden rounded-[3px] bg-white shadow-[0_12px_28px_-12px_rgba(0,0,0,0.75)] ring-1 ring-white/10 transition duration-200 hover:-translate-y-0.5 hover:rotate-[-0.5deg] hover:ring-gold-400/60 active:cursor-grabbing">
                                        <div class="px-3.5 py-3">
                                            <div class="flex items-center justify-between gap-2">
                                                <span class="block h-px w-6 bg-line"></span>
                                                <span class="shrink-0 select-none text-xs leading-none tracking-tighter text-line opacity-100 transition sm:opacity-0 sm:group-hover/pc:opacity-100" aria-hidden="true">⠿</span>
                                            </div>
                                            <p class="mt-1 truncate text-3xs font-bold uppercase tracking-[0.2em] text-muted">{{ $tLabel }}</p>
                                            @if ($cardTitle)
                                                <p class="mt-0.5 truncate text-sm font-bold leading-snug text-ink">{{ $cardTitle }}</p>
                                            @else
                                                <p class="mt-0.5 truncate text-xs font-medium italic text-muted">Untitled</p>
                                            @endif
                                            <div class="mt-2 flex items-end justify-between gap-2 border-t border-dashed border-line/80 pt-1.5">
                                                <span class="truncate font-mono text-3xs text-muted">{{ $c->reference }}</span>
                                                @if ($c->signatories_count > 0)
                                                    <span class="flex shrink-0 items-end gap-1" title="{{ $c->signed_count }} of {{ $c->signatories_count }} signed">
                                                        @for ($i = 0; $i < min($c->signatories_count, 4); $i++)
                                                            <span class="flex w-4 flex-col">
                                                                @if ($i < $c->signed_count)
                                                                    <svg viewBox="0 0 24 10" class="h-1.5 w-4 text-ink"><path d="M1 7 C4 1.5, 7 9, 10 4.5 S 15.5 2, 17.5 6 S 22 3.5, 23 5" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/></svg>
                                                                @else
                                                                    <span class="mt-1 block border-b border-dashed border-line"></span>
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
        [$sLabel, $sChip] = $statusChip[$status] ?? ['Draft', 'bg-page text-muted'];
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
                            'bg-white text-ink shadow hover:brightness-95' => $dirty,
                            'bg-white/5 text-white/30 ring-1 ring-white/10' => ! $dirty,
                        ])>
                    <span wire:loading.remove wire:target="save">{{ $dirty ? 'Save changes' : 'Saved' }}</span>
                    <span wire:loading wire:target="save">Saving…</span>
                </button>

                @if ($type === 'client')
                    <a href="{{ route('events.contract.pdf', $event) }}" target="_blank"
                       title="{{ $dirty ? 'Exports the saved version — save first to include your edits' : 'Export the contract' }}"
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-navy-900 px-4 text-micro font-bold text-white shadow transition hover:bg-navy-800">
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
                       class="flex h-9 items-center gap-1.5 rounded-xl bg-navy-900 px-4 text-micro font-bold text-white shadow transition hover:bg-navy-800">
                        ↧ Export PDF
                    </a>
                @endif

                @can('manage-contract')
                    <x-confirm title="Delete this document and its signatures for good?"
                               confirm="Delete" run="$wire.deleteContract({{ $contractId }})"
                               class="flex h-9 w-9 items-center justify-center rounded-xl text-white/40 transition hover:bg-danger/20 hover:text-red-300">🗑</x-confirm>
                @endcan
            </div>
        </div>

        <div class="grid items-start gap-5 xl:grid-cols-[minmax(0,30rem)_minmax(0,1fr)]">

            @include('livewire.hub.contract.controls')
            {{-- ══════════ RIGHT · the living paper ══════════ --}}
            <div class="xl:sticky xl:top-12">
                <div class="rounded-3xl bg-navy-900/[0.05] p-3 ring-1 ring-line sm:p-5">
                    <div class="mb-2 flex items-center justify-between px-1">
                        <span class="flex items-center gap-1.5 text-eyebrow font-bold uppercase tracking-[0.16em] text-muted">
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
        <div class="pointer-events-auto flex items-center gap-3 rounded-2xl bg-navy-900 px-4 py-2.5 text-white shadow-float ring-1 ring-white/10">
            <span class="flex items-center gap-2 text-xs font-semibold text-amber-200">
                <span class="h-2 w-2 animate-pulse rounded-full bg-amber-400"></span> Unsaved changes
            </span>
            <x-confirm title="Discard your unsaved changes?" confirm="Discard" tone="warn" run="$wire.discard"
                       class="rounded-lg px-3 py-1.5 text-xs font-bold text-white/55 transition hover:text-white">Discard</x-confirm>
            <button type="button" wire:click="save" wire:loading.attr="disabled" wire:target="save"
                    class="rounded-lg bg-white px-5 py-1.5 text-xs font-black text-ink shadow transition hover:brightness-95">
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
