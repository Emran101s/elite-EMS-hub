<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\EventIncomeItem;
use App\Services\BudgetSync;
use App\Services\CurrencyService;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Url;
use Livewire\Attributes\Validate;
use Livewire\Component;

class BudgetTab extends Component
{
    use BulkSelectable;

    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** Overall budget cap (dollars, editable inline). */
    public string $budgetCap = '';

    /** Events-management company fee (% of subtotal, editable). */
    public string $feePct = '15';

    /** Income targets per stream (dollars, editable inline). */
    public string $clientTarget = '';

    public string $sponsorshipTarget = '';

    public string $exhibitionTarget = '';

    /** Category keys that are collapsed in the tree. */
    public array $collapsed = [];

    /** Note for the current approval submission. */
    public string $approvalNote = '';

    /**
     * Workspace mode:
     *   build — plan budgeted amounts, quantity × unit
     *   track — budget vs actual and paid, where you saved or overspent
     *   price — cost vs what the client is charged, and the margin between
     *
     * In the URL so a mode can be linked to — "look at the pricing on this".
     */
    #[Url(as: 'mode')]
    public string $view = 'build';

    // ── line form ──────────────────────────────────────────────
    /** Category (name) the line belongs to. */
    public string $category = '';

    #[Validate('nullable|string|max:160')]
    public string $description = '';

    #[Validate('nullable|string|max:120')]
    public string $vendor = '';

    #[Validate('required|integer|min:1|max:100000')]
    public int $quantity = 1;

    #[Validate('nullable|numeric|min:0')]
    public string $unit = '';

    #[Validate('nullable|numeric|min:0')]
    public string $actual = '';

    #[Validate('nullable|numeric|min:0')]
    public string $paid = '';

    // ── What the client is charged for this line ──
    /** A price typed by hand. Blank means "work it out". */
    #[Validate('nullable|numeric|min:0')]
    public string $sell = '';

    /** Cost plus this much. Blank means the event's management fee. */
    #[Validate('nullable|numeric|min:-100|max:1000')]
    public string $markup = '';

    /** Off for a line you absorb: it still costs, it is just not invoiced. */
    public bool $billable = true;

    #[Validate('nullable|string|max:60')]
    public string $invoice_number = '';

    #[Validate('nullable|date')]
    public string $due_on = '';

    #[Validate('nullable|string|max:400')]
    public string $notes = '';

    // ── income form ──
    public bool $showIncomeForm = false;

    public ?int $editingIncomeId = null;

    public string $incomeSource = 'client';

    public string $incomeDesc = '';

    public string $incomeAmount = '';

    public string $incomeStatus = 'expected';

    // ── category management ──
    public string $newCategoryName = '';

    public ?int $editingCategoryId = null;

    public string $categoryEditName = '';

    public function mount(): void
    {
        $this->event->ensureBudgetCategories();
        app(BudgetSync::class)->sync($this->event);
        // Categories start collapsed — a clean "builder" overview; click to expand.
        $this->collapsed = $this->event->budgetCategories()->pluck('name')->map(fn ($n) => 'cat:'.$n)->all();
        $this->budgetCap = $this->event->budget_cents ? (string) ($this->event->budget_cents / 100) : '';
        $this->feePct = rtrim(rtrim(number_format($this->event->management_fee_pct ?? EventBudgetItem::DEFAULT_FEE_PCT, 2), '0'), '.');
        $this->clientTarget = $this->event->client_target_cents ? (string) ($this->event->client_target_cents / 100) : '';
        $this->sponsorshipTarget = $this->event->sponsorship_target_cents ? (string) ($this->event->sponsorship_target_cents / 100) : '';
        $this->exhibitionTarget = $this->event->exhibition_target_cents ? (string) ($this->event->exhibition_target_cents / 100) : '';
        // Start in Build while planning; switch to Track once real costs are
        // recorded. A mode asked for in the URL wins — it was chosen on purpose.
        if (! in_array(request('mode'), ['build', 'track', 'price'], true)) {
            $this->view = $this->event->budgetItems()->whereNotNull('actual_cents')->exists() ? 'track' : 'build';
        }
        if (request('action') === 'add') {
            $this->newLine();
        }
    }

    /** Block a mutation when the budget baseline is locked (approved). */
    private function ensureUnlocked(): bool
    {
        if ($this->event->budgetLocked()) {
            session()->flash('status', 'Budget is locked. Create a revision to make changes.');

            return false;
        }

        return true;
    }

    public function updatedBudgetCap(): void
    {
        if (! $this->ensureUnlocked()) {
            $this->budgetCap = $this->event->budget_cents ? (string) ($this->event->budget_cents / 100) : '';

            return;
        }
        $this->event->update([
            'budget_cents' => is_numeric($this->budgetCap) ? (int) round((float) $this->budgetCap * 100) : 0,
        ]);
    }

    public function updatedFeePct(): void
    {
        if (! $this->ensureUnlocked()) {
            $this->feePct = (string) ($this->event->management_fee_pct ?? EventBudgetItem::DEFAULT_FEE_PCT);

            return;
        }
        $pct = is_numeric($this->feePct) ? max(0, min(100, (float) $this->feePct)) : 0;
        $this->event->update(['management_fee_pct' => $pct]);
    }

    // Income targets stay editable even when the cost baseline is locked.
    public function updatedClientTarget(): void
    {
        $this->event->update(['client_target_cents' => is_numeric($this->clientTarget) ? (int) round((float) $this->clientTarget * 100) : null]);
    }

    public function updatedSponsorshipTarget(): void
    {
        $this->event->update(['sponsorship_target_cents' => is_numeric($this->sponsorshipTarget) ? (int) round((float) $this->sponsorshipTarget * 100) : null]);
    }

    public function updatedExhibitionTarget(): void
    {
        $this->event->update(['exhibition_target_cents' => is_numeric($this->exhibitionTarget) ? (int) round((float) $this->exhibitionTarget * 100) : null]);
    }

    // ── Approval & versioning ─────────────────────────────────
    public function submitForApproval(): void
    {
        if ($this->event->budgetItems()->count() === 0) {
            session()->flash('status', 'Add budget lines before submitting for approval.');

            return;
        }
        if ($this->event->budget_status === 'approved') {
            return;
        }

        $version = ((int) $this->event->budgetVersions()->max('version')) + 1;
        [$snapshot, $totals] = $this->buildSnapshot();
        $this->event->budgetVersions()->create([
            'version' => $version,
            'label' => $this->approvalNote ?: 'Baseline v'.$version,
            'status' => 'pending',
            'note' => $this->approvalNote ?: null,
            'snapshot' => $snapshot,
            'totals' => $totals,
            'requested_by' => auth()->id(),
        ]);
        $this->event->update(['budget_status' => 'pending']);
        $this->approvalNote = '';
        session()->flash('status', "Budget submitted for approval (v{$version}).");
    }

    public function approveBudget(): void
    {
        Gate::authorize('manage-budget');
        $v = $this->event->budgetVersions()->where('status', 'pending')->orderByDesc('version')->first();
        if (! $v) {
            return;
        }
        $this->event->budgetVersions()->where('status', 'approved')->update(['status' => 'superseded']);
        $v->update(['status' => 'approved', 'decided_by' => auth()->id(), 'decided_at' => now()]);
        // freeze the approved baseline on each line
        foreach ($this->event->budgetItems as $item) {
            $item->update(['approved_cents' => $item->estimated_cents]);
        }
        $this->event->update(['budget_status' => 'approved', 'budget_locked_at' => now()]);
        session()->flash('status', "Budget approved & locked (v{$v->version}).");
    }

    public function rejectBudget(): void
    {
        $v = $this->event->budgetVersions()->where('status', 'pending')->orderByDesc('version')->first();
        if ($v) {
            $v->update(['status' => 'rejected', 'decided_by' => auth()->id(), 'decided_at' => now()]);
        }
        $this->event->update(['budget_status' => 'draft']);
        session()->flash('status', 'Budget approval rejected — returned to draft.');
    }

    public function reviseBudget(): void
    {
        $this->event->update(['budget_status' => 'draft', 'budget_locked_at' => null]);
        session()->flash('status', 'Revision started — budget unlocked for editing. Re-submit when ready.');
    }

    private function buildSnapshot(): array
    {
        $items = $this->event->budgetItems()->get();
        $baseEst = $items->sum('estimated_cents');
        $pct = (float) ($this->event->management_fee_pct ?? EventBudgetItem::DEFAULT_FEE_PCT);
        $fee = (int) round($baseEst * $pct / 100);

        $snapshot = $items->map(fn ($i) => [
            'category' => $i->category, 'description' => $i->description,
            'quantity' => $i->quantity, 'estimated_cents' => $i->estimated_cents,
        ])->all();

        return [$snapshot, ['estimated' => $baseEst, 'fee' => $fee, 'grand' => $baseEst + $fee, 'lines' => count($snapshot)]];
    }

    public function newLine(?string $category = null): void
    {
        $this->reset(['editingId', 'description', 'vendor', 'unit', 'actual', 'paid', 'sell', 'markup', 'billable', 'invoice_number', 'due_on', 'notes']);
        $this->quantity = 1;
        $this->category = $category ?: (string) ($this->event->budgetCategories()->value('name') ?? 'Other');
        // Open the destination category so the new line is visible after saving.
        $this->collapsed = array_values(array_diff($this->collapsed, ['cat:'.$this->category]));
        $this->showForm = true;
    }

    public function editLine(int $id): void
    {
        $item = $this->event->budgetItems()->findOrFail($id);
        if ($item->isLinked()) {
            session()->flash('status', 'This line is synced from the '.str($item->linkedTab())->headline().' tab — edit it there.');

            return;
        }
        $this->editingId = $item->id;
        $this->category = $item->category;
        $this->description = $item->description ?? '';
        $this->vendor = $item->vendor ?? '';
        $this->quantity = max(1, $item->quantity ?? 1);
        $this->unit = $item->unit_cents ? (string) ($item->unit_cents / 100) : '';
        // Null reads as blank; a recorded zero reads as "0", because those are
        // different answers and the form has to be able to say both.
        $this->actual = $item->actual_cents === null ? '' : (string) ($item->actual_cents / 100);
        $this->paid = $item->paid_cents ? (string) ($item->paid_cents / 100) : '';
        $this->sell = $item->sell_cents !== null ? (string) ($item->sell_cents / 100) : '';
        $this->markup = $item->markup_pct !== null ? (string) $item->markup_pct : '';
        $this->billable = $item->billable ?? true;
        $this->invoice_number = $item->invoice_number ?? '';
        $this->due_on = $item->due_on?->format('Y-m-d') ?? '';
        $this->notes = $item->notes ?? '';
        $this->showForm = true;
    }

    /**
     * Price a whole category at once.
     *
     * Pricing line by line is right when a line has its own deal; most of the
     * time a category is one decision — "production goes out at cost plus 25".
     * A line with a price typed by hand is left alone: that price was a
     * decision too, and a bulk action should not quietly overwrite it.
     */
    public function markupCategory(string $category, float $pct): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }

        $touched = $this->event->budgetItems()
            ->where('category', $category)
            ->whereNull('sell_cents')
            ->update(['markup_pct' => $pct]);

        session()->flash('status', $touched
            ? $category.' priced at cost plus '.rtrim(rtrim(number_format($pct, 2), '0'), '.').'%.'
            : 'Every line in '.$category.' already has a price of its own.');
    }

    /** Send a category back to the event's fee — the "undo my pricing" path. */
    public function clearCategoryPricing(string $category): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }

        $this->event->budgetItems()->where('category', $category)
            ->update(['markup_pct' => null, 'sell_cents' => null]);

        session()->flash('status', $category.' is back on the management fee.');
    }

    public function toggleBillable(int $id): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }

        $item = $this->event->budgetItems()->findOrFail($id);
        $item->update(['billable' => ! ($item->billable ?? true)]);
    }

    public function save(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $this->validate();

        // The line must land in one of the event's (user-editable) categories.
        $names = $this->event->budgetCategories()->pluck('name')->all();
        $this->validate(['category' => ['required', Rule::in($names)]]);

        $unitCents = (int) round((float) ($this->unit ?: 0) * 100);
        // Blank means not costed yet — null. A typed 0 means costed, at
        // nothing: the supplier waived it, a sponsor comped it. Coercing blank
        // to 0 made the second impossible to say and wiped the estimate off
        // every line anybody opened and saved.
        $actualCents = trim($this->actual) === '' ? null : (int) round((float) $this->actual * 100);
        $paidCents = (int) round((float) ($this->paid ?: 0) * 100);

        $existing = $this->editingId ? $this->event->budgetItems()->find($this->editingId) : null;

        // The estimate is quantity × unit — except on a line that arrived with
        // an estimate and no unit cost (an import, a seed, a module sync). The
        // form shows a blank unit for those, so deriving from it would zero the
        // estimate the moment someone edited the vendor and saved.
        $estimatedCents = $unitCents > 0
            ? $this->quantity * $unitCents
            : (int) ($existing?->estimated_cents ?? 0);

        $data = [
            'category' => $this->category,
            'description' => $this->description ?: null,
            'vendor' => $this->vendor ?: null,
            'quantity' => max(1, $this->quantity),
            'unit_cents' => $unitCents ?: null,
            'estimated_cents' => $estimatedCents,
            'actual_cents' => $actualCents,
            'paid_cents' => $paidCents,
            // Blank is meaningful here: it means "no price of its own", which
            // is what sends the line back to the management fee.
            'sell_cents' => $this->sell === '' ? null : (int) round((float) $this->sell * 100),
            'markup_pct' => $this->markup === '' ? null : (float) $this->markup,
            'billable' => $this->billable,
            'invoice_number' => $this->invoice_number ?: null,
            'due_on' => $this->due_on ?: null,
            'notes' => $this->notes ?: null,
        ];

        $item = $this->editingId
            ? tap($this->event->budgetItems()->findOrFail($this->editingId))->update($data)
            : $this->event->budgetItems()->create($data);

        $item->update(['payment_status' => $item->derivePaymentStatus()]);

        $this->showForm = false;
        $this->reset(['editingId']);
        session()->flash('status', 'Budget line saved.');
    }

    public function deleteLine(int $id): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $item = $this->event->budgetItems()->findOrFail($id);
        if ($item->isLinked()) {
            session()->flash('status', 'This line is synced — remove the source record in the '.str($item->linkedTab())->headline().' tab.');

            return;
        }
        $item->delete();
    }

    /** Delete the selected manual lines (synced lines are skipped). */
    public function deleteSelected(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $this->event->budgetItems()->whereIn('id', $this->selectedIds())->whereNull('source_type')->delete();
        $this->clearSelection();
        session()->flash('status', 'Selected lines deleted.');
    }

    /** Wipe every budget line (e.g. to remove a starter template and build fresh). */
    public function clearAllLines(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $this->event->budgetItems()->delete();
        session()->flash('status', 'Budget cleared — build it your way.');
    }

    // ── Income (P&L) ──────────────────────────────────────────
    public function newIncome(?string $source = null): void
    {
        $this->reset(['editingIncomeId', 'incomeDesc', 'incomeAmount']);
        $this->incomeSource = $source && array_key_exists($source, Taxonomy::options('income_source')) ? $source : 'client';
        $this->incomeStatus = 'expected';
        $this->showIncomeForm = true;
    }

    public function editIncome(int $id): void
    {
        $i = $this->event->incomeItems()->findOrFail($id);
        $this->editingIncomeId = $i->id;
        $this->incomeSource = $i->source;
        $this->incomeDesc = $i->description ?? '';
        $this->incomeAmount = $i->amount_cents ? (string) ($i->amount_cents / 100) : '';
        $this->incomeStatus = $i->status;
        $this->showIncomeForm = true;
    }

    public function saveIncome(): void
    {
        $this->validate([
            'incomeSource' => 'required|in:'.implode(',', array_keys(Taxonomy::options('income_source'))),
            'incomeDesc' => 'nullable|string|max:160',
            'incomeAmount' => 'required|numeric|min:0',
            'incomeStatus' => 'required|in:'.implode(',', EventIncomeItem::STATUSES),
        ]);

        $data = [
            'source' => $this->incomeSource,
            'description' => $this->incomeDesc ?: null,
            'amount_cents' => (int) round((float) $this->incomeAmount * 100),
            'status' => $this->incomeStatus,
        ];

        $this->editingIncomeId
            ? $this->event->incomeItems()->findOrFail($this->editingIncomeId)->update($data)
            : $this->event->incomeItems()->create($data);

        $this->showIncomeForm = false;
        session()->flash('status', 'Income line saved.');
    }

    public function deleteIncome(int $id): void
    {
        $this->event->incomeItems()->whereKey($id)->delete();
    }

    /** Re-sync linked cost lines from Accommodation, Transport, Speakers & Venue. */
    public function syncModules(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        app(BudgetSync::class)->sync($this->event->fresh());
        session()->flash('status', 'Budget synced with the modules.');
    }

    /** Copy a line within its section, ready to tweak. */
    public function duplicateLine(int $id): void
    {
        $item = $this->event->budgetItems()->findOrFail($id);
        $copy = $item->replicate();
        $copy->description = trim(($item->description ?? $item->categoryLabel()).' (copy)');
        $copy->save();
    }

    public function toggleCollapse(string $cat): void
    {
        $this->collapsed = in_array($cat, $this->collapsed, true)
            ? array_values(array_diff($this->collapsed, [$cat]))
            : [...$this->collapsed, $cat];
    }

    public function expandAll(): void
    {
        $this->collapsed = [];
    }

    public function collapseAll(): void
    {
        $this->collapsed = $this->event->budgetCategories()->pluck('name')->map(fn ($n) => 'cat:'.$n)->values()->all();
    }

    // ── Category management ───────────────────────────────────
    /** Add a new custom budget category. */
    public function addCategory(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $this->validate(['newCategoryName' => ['required', 'string', 'max:60']]);
        $name = trim($this->newCategoryName);

        if ($this->event->budgetCategories()->get()->contains(fn ($c) => mb_strtolower($c->name) === mb_strtolower($name))) {
            $this->addError('newCategoryName', 'That category already exists.');

            return;
        }

        $this->event->budgetCategories()->create([
            'name' => $name,
            'position' => (int) $this->event->budgetCategories()->max('position') + 1,
        ]);
        $this->newCategoryName = '';
        session()->flash('status', "Category “{$name}” added.");
    }

    /** Persist a new category order (list of ids in the desired order). */
    public function reorderCategories(array $ids): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        foreach (array_values($ids) as $pos => $id) {
            $this->event->budgetCategories()->whereKey((int) $id)->update(['position' => $pos]);
        }
    }

    public function startRenameCategory(int $id): void
    {
        $cat = $this->event->budgetCategories()->findOrFail($id);
        $this->editingCategoryId = $cat->id;
        $this->categoryEditName = $cat->name;
    }

    public function cancelRenameCategory(): void
    {
        $this->reset(['editingCategoryId', 'categoryEditName']);
    }

    /** Rename a category — every line under it follows the new name. */
    public function saveCategoryName(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $cat = $this->event->budgetCategories()->findOrFail($this->editingCategoryId);
        $this->validate(['categoryEditName' => ['required', 'string', 'max:60']]);
        $name = trim($this->categoryEditName);

        if ($name !== $cat->name) {
            $this->event->budgetItems()->where('category', $cat->name)->update(['category' => $name]);
            $cat->update(['name' => $name]);
        }
        $this->reset(['editingCategoryId', 'categoryEditName']);
    }

    /** Remove a category; any lines under it move to another category (never lost). */
    public function deleteCategory(int $id): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $cat = $this->event->budgetCategories()->findOrFail($id);
        $count = $this->event->budgetItems()->where('category', $cat->name)->count();

        if ($count > 0) {
            $target = $this->event->budgetCategories()->where('id', '!=', $cat->id)
                ->orderByRaw("name = 'Other' desc")->orderBy('position')->first();
            if (! $target) {
                $target = $this->event->budgetCategories()->create(['name' => 'Other', 'position' => 0]);
            }
            $this->event->budgetItems()->where('category', $cat->name)->update(['category' => $target->name]);
        }

        $name = $cat->name;
        $cat->delete();
        session()->flash('status', "Category “{$name}” removed".($count ? " — {$count} line(s) kept under another category." : '.'));
    }

    public function refreshRate(): void
    {
        $other = $this->event->currency === 'USD' ? 'JOD' : 'USD';
        app(CurrencyService::class)->refresh($this->event->currency, $other);
    }

    public function toggleFlag(int $id): void
    {
        $item = $this->event->budgetItems()->findOrFail($id);
        $item->update(['flagged' => ! $item->flagged]);
    }

    public function markPaid(int $id): void
    {
        $item = $this->event->budgetItems()->findOrFail($id);
        $due = $item->costCents();
        $item->update(['paid_cents' => $due, 'payment_status' => 'paid']);
    }

    /** Insert the reusable professional skeleton (only lines not already present). */
    public function insertStarter(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $existing = $this->event->budgetItems()->get(['category', 'description'])
            ->map(fn ($i) => $i->category.'|'.$i->description)->all();

        foreach (EventBudgetItem::STARTER_TEMPLATE as [$cat, $desc]) {
            if (in_array($cat.'|'.$desc, $existing, true)) {
                continue;
            }
            $this->event->budgetItems()->create([
                'category' => $cat,
                'description' => $desc,
                'quantity' => 1,
                'estimated_cents' => 0,
                                // Not costed yet, which is null — 0 would mean it costs nothing.
                'actual_cents' => null,
                'paid_cents' => 0,
                'payment_status' => 'pending',
            ]);
        }

        session()->flash('status', 'Starter template loaded — fill in your amounts.');
    }

    public function render()
    {
        $items = $this->event->budgetItems()->with('room')->orderBy('id')->get();
        $cats = $this->event->budgetCategories()->get();
        $known = $cats->pluck('name');

        // One section per category, in the user's order — empty categories included.
        $sections = $cats->map(fn ($c) => [
            'id' => $c->id,
            'key' => 'cat:'.$c->name,
            'name' => $c->name,
            'items' => $items->where('category', $c->name)->values(),
        ]);

        // Any lines whose category is no longer in the list still show (never hidden).
        $orphans = $items->reject(fn ($i) => $known->contains($i->category))
            ->groupBy('category')
            ->map(fn ($grp, $name) => [
                'id' => null,
                'key' => 'cat:'.$name,
                'name' => $name ?: 'Uncategorised',
                'items' => $grp->values(),
            ])->values();

        $sections = $sections->merge($orphans)->values();

        // Base subtotals (all real line items).
        $baseEst = $items->sum('estimated_cents');
        $baseAct = $items->sum(fn ($i) => (int) $i->actual_cents);
        $basePaid = $items->sum('paid_cents');
        $baseForecast = $items->sum(fn ($i) => $i->costCents());

        // Savings: budget − actual on lines that have been costed. "Costed at
        // nothing" counts — a venue a sponsor comped is the largest saving an
        // event ever makes, and it used to be invisible because 0 was read as
        // "not costed yet".
        $costedLines = $items->filter->hasActual();
        $savedTotal = $costedLines->sum(fn ($i) => $i->varianceCents());

        // What the client is charged, line by line.
        //
        // This used to be a flat percentage of the subtotal, which ignores a
        // hand-typed price, a per-line markup and a line marked non-billable —
        // so on any event where somebody had quoted a line, this tab and the
        // Finance page gave different answers for the same event, by tens of
        // thousands. Both read EventBudgetItem::sellCentsOn() now, so they
        // cannot. With nothing priced by hand it reproduces subtotal + fee
        // exactly, bar a cent or two of per-line rounding.
        $pct = (float) ($this->event->management_fee_pct ?? EventBudgetItem::DEFAULT_FEE_PCT);

        $chargeEst = (int) $items->sum(fn ($i) => $i->sellCentsOn((int) $i->estimated_cents, $pct));
        // Actual-only, like $baseAct beside it: a line nobody has costed has
        // not been charged for either, and padding the column with its quote
        // would make the actual column a forecast wearing its name.
        $chargeAct = (int) $costedLines->sum(fn ($i) => $i->sellCentsOn((int) $i->actual_cents, $pct));
        $chargeForecast = (int) $items->sum(fn ($i) => $i->sellCents($pct));

        // The fee row is what the charge adds over cost — the real number,
        // whatever mixture of quotes, markups and the default produced it.
        $feeEst = $chargeEst - $baseEst;
        $feeAct = $chargeAct - $baseAct;
        $feeForecast = $chargeForecast - $baseForecast;

        // ── Income side (P&L): three main streams, target vs actual ──
        $sponsors = $this->event->sponsors;
        $exhibitors = $this->event->exhibitors;
        $incomeItems = $this->event->incomeItems;

        // Client / Main Fund IS the client contract — the single source of truth for
        // the client deal. Target = the contract value; actual = payments collected
        // against it (plus any manually logged client income, for edge cases). This
        // stops the Budget showing income 0 while the Contract records money in.
        $contract = $this->event->contract;
        $contractCollected = $contract ? (int) $contract->payments->sum('paid_cents') : 0;
        $contractValue = $contract
            ? (int) ($contract->data['financials']['contract_value_cents']
                ?? $contract->data['financials']['estimated_total_cents'] ?? 0)
            : 0;
        $clientItems = $incomeItems->where('source', 'client');
        $clientActual = $clientItems->sum('amount_cents') + $contractCollected;
        $clientTargetC = ($this->event->client_target_cents ?? 0) ?: $contractValue;

        // Sponsorships (extra): actual = Σ sponsor deals; received = Σ paid.
        $sponsorsIncome = $sponsors->sum('amount_cents');
        $sponsorsReceived = $sponsors->sum('paid_cents');
        $sponsorTargetC = $this->event->sponsorship_target_cents ?? 0;

        // Exhibition / Booths (extra): actual = Σ non-cancelled booth fees; received = Σ paid.
        $exhibitorsIncome = $exhibitors->where('status', '!=', 'cancelled')->sum('fee_cents');
        $exhibitorsReceived = $exhibitors->sum('paid_cents');
        $exhibitionTargetC = $this->event->exhibition_target_cents ?? 0;

        // Other income (registration, tickets, grants, …) — everything that isn't the client fund.
        $otherIncomeItems = $incomeItems->where('source', '!=', 'client')->values();
        $otherIncome = $otherIncomeItems->sum('amount_cents');

        $totalIncome = $clientActual + $sponsorsIncome + $exhibitorsIncome + $otherIncome;
        $totalTargetIncome = $clientTargetC + $sponsorTargetC + $exhibitionTargetC + $otherIncome;
        // What it costs to DELIVER — which is what a P&L subtracts from income.
        //
        // This was the charge, so an event billed correctly and paid in full
        // reported a net profit of exactly zero: the management fee was being
        // subtracted from the income it is part of. The fee is revenue you
        // keep, not money you spend.
        $costToDeliver = $baseForecast;

        // Live currency conversion (e.g. USD → JOD).
        $fx = app(CurrencyService::class);
        $other = $this->event->currency === 'USD' ? 'JOD' : 'USD';
        $rate = $fx->rate($this->event->currency, $other);

        return view('livewire.hub.budget-tab', [
            'items' => $items,
            'sections' => $sections,
            'categories' => $cats,
            // base
            'estimatedTotal' => $baseEst,
            'actualTotal' => $baseAct,
            'paidTotal' => $basePaid,
            'forecastTotal' => $baseForecast,
            'outstandingTotal' => $items->sum(fn ($i) => $i->outstandingCents()),
            'savedTotal' => $savedTotal,
            'hasActuals' => $costedLines->isNotEmpty(),
            // management fee
            'feeEst' => $feeEst,
            'feeAct' => $feeAct,
            'feeForecast' => $feeForecast,
            // grand = what the client is charged
            'grandEst' => $chargeEst,
            'grandAct' => $chargeAct,
            'grandForecast' => $chargeForecast,
            // Cost per attendee, not charge per attendee — the row says Cost.
            // It read the grand total before, which is what you bill, and the
            // two are the same number only on an event nobody has priced.
            'costPerHead' => ($heads = (int) ($this->event->expected_participants ?? 0)) > 0 ? (int) round($baseForecast / $heads) : null,
            'heads' => $heads,
            // currency
            'fxRate' => $rate,
            'fxOther' => $other,
            'fxLive' => $fx->isLive($this->event->currency, $other),
            // approval / versioning
            'versions' => $this->event->budgetVersions()->with(['requester', 'decider'])->get(),
            'approvedTotal' => $items->sum('approved_cents'),
            'varianceVsApproved' => $items->sum('approved_cents') - $baseAct,
            // income / P&L — three streams, target vs actual
            'otherIncomeItems' => $otherIncomeItems,
            'clientActual' => $clientActual,
            'clientTargetC' => $clientTargetC,
            'contractRef' => $contract?->reference,
            'contractCollected' => $contractCollected,
            'sponsorsIncome' => $sponsorsIncome,
            'sponsorsReceived' => $sponsorsReceived,
            'sponsorTargetC' => $sponsorTargetC,
            'exhibitorsIncome' => $exhibitorsIncome,
            'exhibitorsReceived' => $exhibitorsReceived,
            'exhibitionTargetC' => $exhibitionTargetC,
            'otherIncome' => $otherIncome,
            'totalIncome' => $totalIncome,
            'totalTargetIncome' => $totalTargetIncome,
            'costToDeliver' => $costToDeliver,
            'netResult' => $totalIncome - $costToDeliver,
            'projectedNet' => $totalTargetIncome - $costToDeliver,
            'sponsorsCount' => $sponsors->count(),
            'exhibitorsCount' => $exhibitors->where('status', '!=', 'cancelled')->count(),
            'syncedCount' => $items->whereNotNull('source_type')->count(),
        ]);
    }
}
