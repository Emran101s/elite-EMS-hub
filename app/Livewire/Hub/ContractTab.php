<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventContract;
use App\Support\ContractAppendices;
use App\Support\ContractClauses;
use App\Support\ContractTemplates;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;

class ContractTab extends Component
{
    public Event $event;

    /** The contract open in the editor — null means you're on the Deck. */
    public ?int $contractId = null;

    public string $type = 'client';

    public string $title = '';

    public string $language = 'bilingual';

    /** @var array<string,mixed> */
    public array $data = [];

    public string $reference = '';

    public string $status = 'draft';

    /** Nothing is written until Save — this flags work in progress. */
    public bool $dirty = false;

    // ── the "new contract" panel ──
    public bool $showNew = false;

    public string $newType = 'vendor';

    public ?int $newPartyId = null;

    private function touch(): void
    {
        $this->dirty = true;
    }

    /** Commit every pending edit in one go. */
    public function save(): void
    {
        Gate::authorize('manage-contract');
        $this->persist();
        $this->dirty = false;
        session()->flash('status', 'Contract saved.');
    }

    /** Throw the pending edits away and reload what is stored. */
    public function discard(): void
    {
        if ($c = EventContract::find($this->contractId)) {
            $this->data = $c->data;
            $this->reference = $c->reference;
            $this->status = $c->status;
        }
        $this->dirty = false;
        session()->flash('status', 'Changes discarded.');
    }

    /* ── Second parties: the client can be one entity or several ── */

    public function addSecondParty(): void
    {
        Gate::authorize('manage-contract');
        $this->data['second_parties'][] = ['name_en' => '', 'name_ar' => '', 'share' => 0];
        $this->touch();
    }

    public function removeSecondParty(int $i): void
    {
        Gate::authorize('manage-contract');
        unset($this->data['second_parties'][$i]);
        $this->data['second_parties'] = array_values($this->data['second_parties'] ?? []);
        $this->touch();
    }

    /* ── Payment schedule: three installments, four, however many ── */

    public function addInstallment(): void
    {
        Gate::authorize('manage-contract');
        $this->data['financials']['payment_schedule'][] = [
            'pct' => 0, 'when_en' => '', 'when_ar' => '',
        ];
        $this->touch();
    }

    public function removeInstallment(int $i): void
    {
        Gate::authorize('manage-contract');
        unset($this->data['financials']['payment_schedule'][$i]);
        $this->data['financials']['payment_schedule'] = array_values($this->data['financials']['payment_schedule'] ?? []);
        $this->touch();
    }

    /** Spread 100% evenly across the installments — a quick fix for odd totals. */
    public function balanceInstallments(): void
    {
        Gate::authorize('manage-contract');
        $rows = $this->data['financials']['payment_schedule'] ?? [];
        if (($n = count($rows)) === 0) {
            return;
        }
        $each = round(100 / $n, 2);
        foreach ($rows as $i => $r) {
            $this->data['financials']['payment_schedule'][$i]['pct'] = $each;
        }
        // Put any rounding remainder on the last installment so it totals exactly 100.
        $this->data['financials']['payment_schedule'][$n - 1]['pct'] = round(100 - $each * ($n - 1), 2);
        $this->touch();
    }

    public function mount(Event $event): void
    {
        // The Deck is the landing: the documents that exist, or the invitation to
        // create one. Nothing opens — and nothing is auto-created — until chosen,
        // so a deleted contract stays deleted.
        $this->event = $event;

        // …unless a link names one. ?document=12 opens that document straight
        // away, so a contract can be linked to from an email, a task or the
        // Deck of another event. A document belonging to a different event, or
        // one that has been deleted, silently lands on the Deck.
        if ($id = (int) request('document')) {
            if ($event->contracts()->whereKey($id)->exists()) {
                $this->loadContract($id);
            }
        }
    }

    /** Close the editor and return to the wall of documents. */
    public function backToDeck(): void
    {
        $this->contractId = null;
        $this->dirty = false;
    }

    /** Open a contract in the editor, loading its content into the form. */
    private function loadContract(int $id): void
    {
        $c = $this->event->contracts()->findOrFail($id);

        $this->contractId = $c->id;
        $this->type = $c->type;
        $this->title = $c->title ?? '';
        $this->language = $c->language;
        $this->data = $c->data ?? [];
        $this->reference = $c->reference;
        $this->status = $c->status;
        $this->dirty = false;

        // Client contracts seed the standard clause body once, then own it.
        if ($c->isClient() && empty($this->data['blocks'])) {
            $this->data['blocks'] = ContractClauses::blocks($this->data);
            $this->persist();
        }

        // …and one appendix, the scope. A contract written before appendices
        // existed gets it on first open; everything else is added on purpose.
        if ($c->isClient() && ! isset($this->data['appendices'])) {
            $this->data['appendices'] = ContractAppendices::seed();
            $this->persist();
        }

        // Every contract has its default signatories the first time it opens.
        $c->ensureSignatories();
    }

    // ── Signatories & the signing audit trail ──────────────────

    public function addSignatory(): void
    {
        Gate::authorize('manage-contract');
        $c = $this->contract();
        $c->signatories()->create([
            'role' => $c->isClient() ? 'client' : 'witness',
            'name' => '',
            'order' => (int) $c->signatories()->max('order') + 1,
        ]);
    }

    public function updateSignatory(int $id, string $field, string $value): void
    {
        Gate::authorize('manage-contract');
        if (! in_array($field, ['name', 'email', 'role'], true)) {
            return;
        }
        if ($field === 'role' && ! isset(Taxonomy::options('signatory_role')[$value])) {
            return;
        }

        $this->contract()->signatories()->whereKey($id)->update([$field => trim($value) ?: null]);
    }

    public function removeSignatory(int $id): void
    {
        Gate::authorize('manage-contract');
        $this->contract()->signatories()->whereKey($id)->delete();
        $this->contract()->syncStatusFromSignatures();
        $this->status = $this->contract()->status;
    }

    /**
     * Record a signature — the Phase-1 e-sign: a typed name, stamped with when,
     * from where, and the hash of the document as it stands. The contract's
     * status then follows from how many have signed.
     */
    public function recordSignature(int $id, ?string $typed = null): void
    {
        Gate::authorize('manage-contract');

        $c = $this->contract();
        $s = $c->signatories()->whereKey($id)->firstOrFail();
        if ($s->name === null || $s->name === '') {
            return;   // can't sign for an unnamed party
        }

        $s->update([
            'signed_at' => now(),
            'signature_data' => trim((string) ($typed ?: $s->name)),
            'signed_ip' => request()->ip(),
            'signed_hash' => $c->contentHash(),
        ]);

        $c->syncStatusFromSignatures();
        $this->status = $c->status;
    }

    /** Undo a signature — clears the audit fields and re-derives the status. */
    public function unsign(int $id): void
    {
        Gate::authorize('manage-contract');

        $c = $this->contract();
        $c->signatories()->whereKey($id)->update([
            'signed_at' => null, 'signature_data' => null, 'signed_ip' => null, 'signed_hash' => null,
        ]);

        $c->syncStatusFromSignatures();
        $this->status = $c->status;
    }

    public function selectContract(int $id): void
    {
        $this->loadContract($id);
    }

    /** Start a new contract of the chosen type, tied to a counterparty if any. */
    public function createContract(): void
    {
        Gate::authorize('manage-contract');

        $type = isset(EventContract::TYPES[$this->newType]) ? $this->newType : 'letter';
        $party = $this->resolveParty($type, $this->newPartyId);

        $c = EventContract::createFor($this->event, $type, $party);

        $this->reset(['showNew', 'newPartyId']);
        $this->newType = 'vendor';
        $this->loadContract($c->id);
    }

    /** The counterparty model for a party-bound type, scoped to this event. */
    private function resolveParty(string $type, ?int $id)
    {
        if (! $id) {
            return null;
        }

        return match ($type) {
            'vendor' => $this->event->suppliers()->find($id),
            'speaker' => $this->event->speakers()->find($id),
            'sponsorship' => $this->event->sponsors()->find($id),
            default => null,
        };
    }

    /** Remove any document — client contract included. Deleted means deleted. */
    public function deleteContract(int $id): void
    {
        Gate::authorize('manage-contract');

        $this->event->contracts()->findOrFail($id)->delete();

        if ($this->contractId === $id) {
            $this->backToDeck();
        }
    }

    public function setLanguage(string $lang): void
    {
        Gate::authorize('manage-contract');
        $this->language = in_array($lang, ['en', 'bilingual'], true) ? $lang : 'en';
        $this->touch();
    }

    /**
     * Attach (or switch) the counterparty of the open document from the event's
     * own lists — the speaker, sponsor or supplier record. The link is written
     * immediately; the document text updates via the live data and can be fully
     * re-interpolated with refillFromTemplate().
     */
    public function setParty($id = null): void
    {
        Gate::authorize('manage-contract');

        $party = $this->resolveParty($this->type, $id ? (int) $id : null);
        $c = $this->contract();

        $c->party_type = $party?->getMorphClass();
        $c->party_id = $party?->getKey();
        $c->save();

        $this->data['counterparty'] = array_merge($this->data['counterparty'] ?? [], [
            'name_en' => $party?->name ?? '',
            'email' => $party->email ?? null,
            'phone' => $party->phone ?? null,
            'fee_cents' => $party->fee_cents ?? $party->amount_cents ?? null,
            'package' => $party->package ?? null,
            'detail' => $party->topic ?? $party->category ?? null,
        ]);

        if ($party?->name) {
            $this->title = EventContract::TYPES[$this->type]['label'].' · '.$party->name;
        }

        $this->touch();
    }

    /** The fee / amount, typed in currency units, stored in cents. */
    public function setPartyFee($value): void
    {
        Gate::authorize('manage-contract');
        $this->data['counterparty']['fee_cents'] = is_numeric($value)
            ? max(0, (int) round((float) $value * 100))
            : null;
        $this->touch();
    }

    /**
     * Re-seed the body from the type's template with the CURRENT counterparty
     * and details interpolated. Replaces the body — hence the confirm in the UI.
     */
    public function refillFromTemplate(): void
    {
        Gate::authorize('manage-contract');

        if ($this->type === 'client') {
            return;   // the client body refills via restoreStandardBlocks
        }

        $this->data['blocks'] = ContractTemplates::blocks($this->type, $this->data);
        $this->touch();
    }

    /**
     * Move a contract to a pipeline column — the drag target on the Deck's
     * pipeline view. A discrete action: written straight to the record.
     */
    public function setContractStatus(int $id, string $status): void
    {
        Gate::authorize('manage-contract');

        if (! in_array($status, EventContract::STATUSES, true)) {
            return;
        }

        $c = $this->event->contracts()->findOrFail($id);
        $c->status = $status;
        $c->signed_at = $status === 'signed' ? ($c->signed_at ?? now()) : null;
        $c->save();

        // Keep the editor's status in sync if it's the open one.
        if ($id === $this->contractId) {
            $this->status = $status;
        }
    }

    /* ── The contract body: editable bilingual blocks ──
       Every method here edits whichever set the editor is pointed at: the
       body by default, or an appendix when one is open. An appendix's
       blocks are the same shape, so retargeting is the whole feature —
       there is no second editor to keep in step with this one. ── */

    /** The blocks currently being edited, by value (for reading). */
    private function currentBlocks(): array
    {
        if ($this->editingAppendix !== null && ($i = $this->appendixIndex($this->editingAppendix)) !== null) {
            return $this->data['appendices'][$i]['blocks'] ?? [];
        }

        return $this->data['blocks'] ?? [];
    }

    /** The same list, by reference (for writing). */
    private function &blocksRef(): array
    {
        if ($this->editingAppendix !== null && ($i = $this->appendixIndex($this->editingAppendix)) !== null) {
            $this->data['appendices'][$i]['blocks'] ??= [];

            return $this->data['appendices'][$i]['blocks'];
        }

        $this->data['blocks'] ??= [];

        return $this->data['blocks'];
    }


    private function blockIndex(string $id): ?int
    {
        foreach ($this->currentBlocks() as $i => $b) {
            if (($b['id'] ?? '') === $id) {
                return $i;
            }
        }

        return null;
    }

    /** A new, empty section — the "another content area" you can drop anywhere. */
    public function addBlock(): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        $blocks[] = [
            'id' => 'b'.Str::random(8),
            'type' => 'prose',
            'title_en' => 'New Section',
            'title_ar' => 'بند جديد',
            'en' => [''],
            'ar' => [''],
            'items' => [],
            'rows' => [],
        ];
        $this->touch();
    }

    public function updateBlockField(string $id, string $field, string $value): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (! in_array($field, ['title_en', 'title_ar'], true) || ($i = $this->blockIndex($id)) === null) {
            return;
        }
        $blocks[$i][$field] = $value;
        $this->touch();
    }

    public function updateParagraph(string $id, string $lang, int $p, string $value): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (! in_array($lang, ['en', 'ar'], true) || ($i = $this->blockIndex($id)) === null) {
            return;
        }
        $blocks[$i][$lang][$p] = $value;
        $this->touch();
    }

    /** Paragraphs are added as an EN/AR pair so the two versions stay aligned. */
    public function addParagraph(string $id): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (($i = $this->blockIndex($id)) === null) {
            return;
        }
        $blocks[$i]['en'][] = '';
        $blocks[$i]['ar'][] = '';
        $this->touch();
    }

    public function removeParagraph(string $id, int $p): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (($i = $this->blockIndex($id)) === null) {
            return;
        }
        foreach (['en', 'ar'] as $lang) {
            unset($blocks[$i][$lang][$p]);
            $blocks[$i][$lang] = array_values($blocks[$i][$lang]);
        }
        $this->touch();
    }

    /* ── Bullet / policy rows inside a block (scope, exclusions, cancellation) ── */

    public function addItem(string $id): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (($i = $this->blockIndex($id)) === null) {
            return;
        }
        $blocks[$i]['items'][] = ['l_en' => '', 'l_ar' => '', 't_en' => '', 't_ar' => ''];
        $this->touch();
    }

    public function updateItem(string $id, int $row, string $field, string $value): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (! in_array($field, ['l_en', 'l_ar', 't_en', 't_ar'], true) || ($i = $this->blockIndex($id)) === null) {
            return;
        }
        $blocks[$i]['items'][$row][$field] = $value;
        $this->touch();
    }

    public function removeItem(string $id, int $row): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (($i = $this->blockIndex($id)) === null) {
            return;
        }
        unset($blocks[$i]['items'][$row]);
        $blocks[$i]['items'] = array_values($blocks[$i]['items']);
        $this->touch();
    }

    public function moveBlock(string $id, int $dir): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        $i = $this->blockIndex($id);
        $j = $i === null ? null : $i + $dir;
        if ($i === null || $j === null || $j < 0 || $j >= count($blocks)) {
            return;
        }
        $blocks = $blocks;
        [$blocks[$i], $blocks[$j]] = [$blocks[$j], $blocks[$i]];
        $blocks = array_values($blocks);
        $this->touch();
    }

    public function deleteBlock(string $id): void
    {
        Gate::authorize('manage-contract');
        $blocks = &$this->blocksRef();
        if (($i = $this->blockIndex($id)) === null) {
            return;
        }
        unset($blocks[$i]);
        $blocks = array_values($blocks);
        $this->touch();
    }

    /** Put the standard clause set back, losing local edits to the body. */
    public function restoreStandardBlocks(): void
    {
        Gate::authorize('manage-contract');
        $this->data['blocks'] = ContractClauses::blocks($this->data);
        $this->touch();
    }

    // ══════════════════ THE ANNEXES ══════════════════
    //
    // An appendix holds blocks of exactly the same shape as the body's, so
    // every method above works on one — which is why the editor retargets
    // instead of growing a second set of controls.

    /** null = editing the body; a slug = editing that appendix. */
    public ?string $editingAppendix = null;

    /** The list, always an array even on a contract seeded before appendices. */
    private function appendices(): array
    {
        return array_values($this->data['appendices'] ?? []);
    }

    private function appendixIndex(string $slug): ?int
    {
        foreach ($this->appendices() as $i => $a) {
            if (($a['slug'] ?? '') === $slug) {
                return $i;
            }
        }

        return null;
    }

    public function editAppendix(?string $slug): void
    {
        $this->editingAppendix = $slug !== null && $this->appendixIndex($slug) !== null ? $slug : null;
    }

    public function addAppendix(string $key): void
    {
        Gate::authorize('manage-contract');

        // Slugs are the identity a reference points at, so a second Budget
        // appendix gets its own — never a duplicate that two references share.
        $slug = $key;
        $n = 1;
        while ($this->appendixIndex($slug) !== null) {
            $slug = $key.'-'.(++$n);
        }

        $this->data['appendices'] = [...$this->appendices(), ContractAppendices::make($key, $slug)];
        $this->editingAppendix = $slug;
        $this->touch();
    }

    public function updateAppendixField(string $slug, string $field, string $value): void
    {
        Gate::authorize('manage-contract');
        if (! in_array($field, ['title_en', 'title_ar'], true) || ($i = $this->appendixIndex($slug)) === null) {
            return;
        }
        $this->data['appendices'][$i][$field] = $value;
        $this->touch();
    }

    public function moveAppendix(string $slug, int $dir): void
    {
        Gate::authorize('manage-contract');
        $list = $this->appendices();
        $i = $this->appendixIndex($slug);
        $j = $i === null ? null : $i + $dir;

        if ($i === null || $j === null || $j < 0 || $j >= count($list)) {
            return;
        }

        [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        $this->data['appendices'] = array_values($list);
        $this->touch();   // every {{appendix:…}} in the document renumbers itself
    }

    public function deleteAppendix(string $slug): void
    {
        Gate::authorize('manage-contract');
        if (($i = $this->appendixIndex($slug)) === null) {
            return;
        }

        $list = $this->appendices();
        unset($list[$i]);
        $this->data['appendices'] = array_values($list);

        if ($this->editingAppendix === $slug) {
            $this->editingAppendix = null;
        }

        $this->touch();

        // The document may point at what was just removed. Say so — the editor
        // shows the broken reference and the export refuses, but finding out
        // at export time is late.
        if (in_array($slug, ContractAppendices::referencedSlugs([...($this->data['blocks'] ?? []), ...$list]), true)) {
            $this->dispatch(
                'contract-toast',
                message: 'Removed — but the contract still refers to it. Fix the reference before exporting.',
                tone: 'warn',
            );
        }
    }

    /**
     * Take a snapshot from the module that owns this appendix's content.
     *
     * A snapshot, not a feed: the contract must not change after it is sent
     * because somebody edited a budget line. Refreshing is this button, and it
     * is refused once the document is signed.
     */
    public function pullAppendix(string $slug): void
    {
        Gate::authorize('manage-contract');
        $i = $this->appendixIndex($slug);

        $contract = $this->contractId ? $this->event->contracts()->find($this->contractId) : null;

        if ($i === null || ! $contract) {
            return;
        }

        if ($contract->status === 'signed') {
            $this->dispatch('contract-toast', message: 'This document is signed — its appendices are fixed.', tone: 'warn');

            return;
        }

        $source = $this->data['appendices'][$i]['source'] ?? null;

        if (! $source || in_array($source, ['typed', 'form'], true)) {
            return;
        }

        [$blocks, $summary] = ContractAppendices::pull($source, $this->event, $contract);

        if ($blocks === []) {
            $this->dispatch('contract-toast', message: $summary, tone: 'warn');

            return;
        }

        $this->data['appendices'][$i]['blocks'] = $blocks;
        $this->data['appendices'][$i]['pulled_at'] = now()->toIso8601String();
        $this->touch();

        $this->dispatch('contract-toast', message: 'Pulled '.$summary.'.', tone: 'ok');
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'data') || in_array($name, ['reference', 'title'], true)) {
            $this->touch();
        }
    }

    public function cycleStatus(): void
    {
        Gate::authorize('manage-contract');
        $this->status = match ($this->status) {
            'draft' => 'sent',
            'sent' => 'signed',
            default => 'draft',
        };
        $this->touch();
    }

    /**
     * One-shot convenience: copy the current budget into the contract value.
     * The contract keeps its own figure afterwards — this is never a live link.
     */
    public function syncBudget(): void
    {
        $cents = (int) ($this->event->budget_cents ?? 0);
        $this->data['financials']['contract_value_cents'] = $cents;
        $this->data['financials']['estimated_total_cents'] = $cents;
        $this->touch();
    }

    /** Contract value typed in currency units → stored as cents. */
    public function setContractValue($value): void
    {
        Gate::authorize('manage-contract');
        $cents = (int) round(((float) preg_replace('/[^0-9.\-]/', '', (string) $value)) * 100);
        $this->data['financials']['contract_value_cents'] = max(0, $cents);
        $this->touch();
    }

    public function setValueMode(string $mode): void
    {
        Gate::authorize('manage-contract');
        $this->data['financials']['value_mode'] = $mode === 'estimate' ? 'estimate' : 'fixed';
        $this->touch();
    }

    public function resetContract(): void
    {
        Gate::authorize('manage-contract');
        $this->data = EventContract::defaultData($this->event);
        $this->touch();
    }

    /**
     * Clearing a number input leaves an empty string in the JSON, and "" breaks
     * every later sum. Coerce the numeric fields before anything is stored.
     */
    private function normalizeNumbers(): void
    {
        foreach ($this->data['financials']['payment_schedule'] ?? [] as $i => $s) {
            $this->data['financials']['payment_schedule'][$i]['pct'] = (float) ($s['pct'] ?? 0);
        }
        foreach ($this->data['second_parties'] ?? [] as $i => $sp) {
            $this->data['second_parties'][$i]['share'] = (float) ($sp['share'] ?? 0);
        }
        if (isset($this->data['financials'])) {
            $this->data['financials']['management_fee_pct'] = (float) ($this->data['financials']['management_fee_pct'] ?? 0);
            $this->data['financials']['contract_value_cents'] = (int) ($this->data['financials']['contract_value_cents'] ?? 0);
        }
    }

    private function persist(): void
    {
        $c = EventContract::find($this->contractId);
        if (! $c) {
            return;
        }
        $this->normalizeNumbers();
        $c->data = $this->data;
        $c->reference = $this->reference;
        $c->status = $this->status;
        $c->title = $this->title ?: null;
        $c->language = $this->language;
        $c->signed_at = $this->status === 'signed' ? ($c->signed_at ?? now()) : null;
        $c->save();
    }

    /* ── Payment tracking: the schedule, against reality ── */

    /** Record money received on an installment (amount in currency units). */
    public function recordPayment(int $paymentId, $amount = null): void
    {
        Gate::authorize('manage-contract');

        $p = $this->contract()->payments()->whereKey($paymentId)->firstOrFail();
        $cents = $amount === null || ! is_numeric($amount)
            ? $p->outstandingCents()                        // blank = settle in full
            : max(0, (int) round((float) $amount * 100));

        $p->update([
            'paid_cents' => min($p->amount_cents, $p->paid_cents + $cents),
            'paid_at' => now()->toDateString(),
        ]);
    }

    /** Undo a recorded payment (typo, bounced transfer). */
    public function clearPayment(int $paymentId): void
    {
        Gate::authorize('manage-contract');

        $this->contract()->payments()->whereKey($paymentId)
            ->firstOrFail()->update(['paid_cents' => 0, 'paid_at' => null]);
    }

    public function setPaymentDue(int $paymentId, ?string $date): void
    {
        Gate::authorize('manage-contract');

        $this->contract()->payments()->whereKey($paymentId)
            ->firstOrFail()->update(['due_on' => $date ?: null]);
    }

    /** Pull schedule/estimate changes onto unpaid installments. */
    public function repricePayments(): void
    {
        Gate::authorize('manage-contract');

        $this->contract()->repriceUnpaidPayments();
    }

    private function contract(): EventContract
    {
        return EventContract::findOrFail($this->contractId);
    }

    public function render()
    {
        // Null on the Deck; a model when a document is open in the editor.
        $contract = $this->contractId ? $this->event->contracts()->find($this->contractId) : null;

        if ($contract?->isClient()) {
            $contract->ensurePayments();
        }

        // Party options for the "new contract" panel, from this event's own records.
        $partyOptions = match ($this->newType) {
            'vendor' => $this->event->suppliers()->orderBy('name')->get(['suppliers.id', 'name']),
            'speaker' => $this->event->speakers()->orderBy('name')->get(['id', 'name']),
            'sponsorship' => $this->event->sponsors()->orderBy('name')->get(['id', 'name']),
            default => collect(),
        };

        return view('livewire.hub.contract-tab', [
            'contracts' => $this->event->contracts()->with('party', 'signatories')
                ->withCount([
                    'signatories',
                    'signatories as signed_count' => fn ($q) => $q->whereNotNull('signed_at'),
                ])->get(),
            'contract' => $contract,
            'signatories' => $contract?->signatories()->get() ?? collect(),
            'partyOptions' => $partyOptions,
            // Options for switching the OPEN document's counterparty.
            'editPartyOptions' => $contract && ! $contract->isClient() ? match ($this->type) {
                'vendor' => $this->event->suppliers()->orderBy('name')->get(['suppliers.id', 'name']),
                'speaker' => $this->event->speakers()->orderBy('name')->get(['id', 'name']),
                'sponsorship' => $this->event->sponsors()->orderBy('name')->get(['id', 'name']),
                default => collect(),
            } : collect(),
        ]);
    }
}
