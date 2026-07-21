<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Event;
use App\Models\EventBudgetItem;
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

    /** Workspace mode: 'build' (plan budgeted amounts) | 'track' (budget vs actual/paid/saved). */
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
        app(\App\Services\BudgetSync::class)->sync($this->event);
        // Categories start collapsed — a clean "builder" overview; click to expand.
        $this->collapsed = $this->event->budgetCategories()->pluck('name')->map(fn ($n) => 'cat:'.$n)->all();
        $this->budgetCap = $this->event->budget_cents ? (string) ($this->event->budget_cents / 100) : '';
        $this->feePct = rtrim(rtrim(number_format($this->event->management_fee_pct ?? EventBudgetItem::DEFAULT_FEE_PCT, 2), '0'), '.');
        $this->clientTarget = $this->event->client_target_cents ? (string) ($this->event->client_target_cents / 100) : '';
        $this->sponsorshipTarget = $this->event->sponsorship_target_cents ? (string) ($this->event->sponsorship_target_cents / 100) : '';
        $this->exhibitionTarget = $this->event->exhibition_target_cents ? (string) ($this->event->exhibition_target_cents / 100) : '';
        // Start in Build while planning; switch to Track once real costs are recorded.
        $this->view = $this->event->budgetItems()->where('actual_cents', '>', 0)->exists() ? 'track' : 'build';
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
        \Illuminate\Support\Facades\Gate::authorize('manage-budget');
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
        $this->reset(['editingId', 'description', 'vendor', 'unit', 'actual', 'paid', 'invoice_number', 'due_on', 'notes']);
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
        $this->actual = $item->actual_cents ? (string) ($item->actual_cents / 100) : '';
        $this->paid = $item->paid_cents ? (string) ($item->paid_cents / 100) : '';
        $this->invoice_number = $item->invoice_number ?? '';
        $this->due_on = $item->due_on?->format('Y-m-d') ?? '';
        $this->notes = $item->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        if (! $this->ensureUnlocked()) {
            return;
        }
        $this->validate();

        // The line must land in one of the event's (user-editable) categories.
        $names = $this->event->budgetCategories()->pluck('name')->all();
        $this->validate(['category' => ['required', \Illuminate\Validation\Rule::in($names)]]);

        $unitCents = (int) round((float) ($this->unit ?: 0) * 100);
        $actualCents = (int) round((float) ($this->actual ?: 0) * 100);
        $paidCents = (int) round((float) ($this->paid ?: 0) * 100);
        $estimatedCents = $this->quantity * $unitCents;

        $data = [
            'category' => $this->category,
            'description' => $this->description ?: null,
            'vendor' => $this->vendor ?: null,
            'quantity' => max(1, $this->quantity),
            'unit_cents' => $unitCents ?: null,
            'estimated_cents' => $estimatedCents,
            'actual_cents' => $actualCents,
            'paid_cents' => $paidCents,
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
        $this->incomeSource = $source && array_key_exists($source, \App\Models\EventIncomeItem::SOURCES) ? $source : 'client';
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
            'incomeSource' => 'required|in:'.implode(',', array_keys(\App\Models\EventIncomeItem::SOURCES)),
            'incomeDesc' => 'nullable|string|max:160',
            'incomeAmount' => 'required|numeric|min:0',
            'incomeStatus' => 'required|in:'.implode(',', \App\Models\EventIncomeItem::STATUSES),
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
        app(\App\Services\BudgetSync::class)->sync($this->event->fresh());
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
        app(\App\Services\CurrencyService::class)->refresh($this->event->currency, $other);
    }

    public function toggleFlag(int $id): void
    {
        $item = $this->event->budgetItems()->findOrFail($id);
        $item->update(['flagged' => ! $item->flagged]);
    }

    public function markPaid(int $id): void
    {
        $item = $this->event->budgetItems()->findOrFail($id);
        $due = $item->actual_cents ?: $item->estimated_cents;
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
                'actual_cents' => 0,
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
        $baseAct = $items->sum('actual_cents');
        $basePaid = $items->sum('paid_cents');
        $baseForecast = $items->sum(fn ($i) => $i->actual_cents ?: $i->estimated_cents);

        // Savings: budget − actual, only on lines where a real cost is recorded (+ = saved, − = over).
        $costedLines = $items->where('actual_cents', '>', 0);
        $savedTotal = $costedLines->sum('estimated_cents') - $costedLines->sum('actual_cents');

        // Events-management fee is derived on top of the subtotal.
        $pct = (float) ($this->event->management_fee_pct ?? EventBudgetItem::DEFAULT_FEE_PCT);
        $feeEst = (int) round($baseEst * $pct / 100);
        $feeAct = (int) round($baseAct * $pct / 100);
        $feeForecast = (int) round($baseForecast * $pct / 100);

        // ── Income side (P&L): three main streams, target vs actual ──
        $sponsors = $this->event->sponsors;
        $exhibitors = $this->event->exhibitors;
        $incomeItems = $this->event->incomeItems;

        // Client / Main Fund: actual = logged client payments; target set on the event.
        $clientItems = $incomeItems->where('source', 'client');
        $clientActual = $clientItems->sum('amount_cents');
        $clientTargetC = $this->event->client_target_cents ?? 0;

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
        $grandCost = $baseEst + $feeEst;

        // Live currency conversion (e.g. USD → JOD).
        $fx = app(\App\Services\CurrencyService::class);
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
            // grand (base + fee)
            'grandEst' => $baseEst + $feeEst,
            'grandAct' => $baseAct + $feeAct,
            'grandForecast' => $baseForecast + $feeForecast,
            'costPerHead' => ($heads = (int) ($this->event->expected_participants ?? 0)) > 0 ? (int) round(($baseEst + $feeEst) / $heads) : null,
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
            'sponsorsIncome' => $sponsorsIncome,
            'sponsorsReceived' => $sponsorsReceived,
            'sponsorTargetC' => $sponsorTargetC,
            'exhibitorsIncome' => $exhibitorsIncome,
            'exhibitorsReceived' => $exhibitorsReceived,
            'exhibitionTargetC' => $exhibitionTargetC,
            'otherIncome' => $otherIncome,
            'totalIncome' => $totalIncome,
            'totalTargetIncome' => $totalTargetIncome,
            'netResult' => $totalIncome - $grandCost,
            'projectedNet' => $totalTargetIncome - $grandCost,
            'sponsorsCount' => $sponsors->count(),
            'exhibitorsCount' => $exhibitors->where('status', '!=', 'cancelled')->count(),
            'syncedCount' => $items->whereNotNull('source_type')->count(),
        ]);
    }
}
