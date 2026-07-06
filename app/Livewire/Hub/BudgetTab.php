<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\Supplier;
use Livewire\Component;

class BudgetTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public string $category = 'venue';

    public string $description = '';

    public string $estimated = '';

    public string $actual = '';

    public ?int $supplier_id = null;

    public string $payment_status = 'pending';

    public string $invoice_number = '';

    public string $due_on = '';

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    public function save()
    {
        $this->validate([
            'category' => ['required', 'in:'.implode(',', EventBudgetItem::CATEGORIES)],
            'description' => ['nullable', 'string', 'max:160'],
            'estimated' => ['required', 'numeric', 'min:0'],
            'actual' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'payment_status' => ['required', 'in:'.implode(',', EventBudgetItem::PAYMENT_STATUSES)],
            'invoice_number' => ['nullable', 'string', 'max:60'],
            'due_on' => ['nullable', 'date'],
        ]);

        $this->event->budgetItems()->create([
            'category' => $this->category,
            'description' => $this->description ?: null,
            'estimated_cents' => (int) round((float) $this->estimated * 100),
            'actual_cents' => (int) round((float) ($this->actual ?: 0) * 100),
            'supplier_id' => $this->supplier_id,
            'payment_status' => $this->payment_status,
            'invoice_number' => $this->invoice_number ?: null,
            'due_on' => $this->due_on ?: null,
        ]);

        session()->flash('status', 'Budget line added.');

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'budget']);
    }

    public function setPayment(int $itemId, string $status)
    {
        abort_unless(in_array($status, EventBudgetItem::PAYMENT_STATUSES, true), 422);

        $this->event->budgetItems()->whereKey($itemId)->firstOrFail()->update(['payment_status' => $status]);

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'budget']);
    }

    public function render()
    {
        return view('livewire.hub.budget-tab', [
            'items' => $this->event->budgetItems()->with('supplier')->orderBy('category')->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
        ]);
    }
}
