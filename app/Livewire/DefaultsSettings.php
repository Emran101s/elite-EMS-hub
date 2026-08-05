<?php

namespace App\Livewire;

use App\Models\CompanyProfile;
use App\Models\EventBudgetItem;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Illuminate\Support\Facades\Gate;

#[Layout('components.layouts.app', ['title' => 'Defaults & Templates'])]
class DefaultsSettings extends Component
{
    /** @var array<int,string> */
    public array $categories = [];

    public string $newCategory = '';

    public string $fee = '15';

    /** @var array<int,string> */
    public array $tickets = [];

    public string $newTicket = '';

    /** Blank means the policy is off — no approval ever gets an automatic admin step. */
    public string $approvalThreshold = '';

    public function mount(): void
    {
        $p = CompanyProfile::current();
        $this->categories = $p->budgetCategories();
        $this->tickets = $p->ticketTypes();
        $this->fee = rtrim(rtrim(number_format((float) $p->default_management_fee_pct, 2, '.', ''), '0'), '.');
        $this->approvalThreshold = $p->approval_threshold_cents !== null ? (string) ($p->approval_threshold_cents / 100) : '';
    }

    public function addTicket(): void
    {
        $name = trim($this->newTicket);
        if ($name === '') {
            return;
        }
        if (collect($this->tickets)->contains(fn ($t) => mb_strtolower($t) === mb_strtolower($name))) {
            $this->addError('newTicket', 'That ticket type already exists.');

            return;
        }
        $this->tickets[] = $name;
        $this->newTicket = '';
    }

    public function removeTicket(int $i): void
    {
        unset($this->tickets[$i]);
        $this->tickets = array_values($this->tickets);
    }

    public function addCategory(): void
    {
        $name = trim($this->newCategory);
        if ($name === '') {
            return;
        }
        if (collect($this->categories)->contains(fn ($c) => mb_strtolower($c) === mb_strtolower($name))) {
            $this->addError('newCategory', 'That category already exists.');

            return;
        }
        $this->categories[] = $name;
        $this->newCategory = '';
    }

    public function removeCategory(int $i): void
    {
        unset($this->categories[$i]);
        $this->categories = array_values($this->categories);
    }

    public function move(int $i, int $dir): void
    {
        $j = $i + $dir;
        if ($j < 0 || $j >= count($this->categories)) {
            return;
        }
        [$this->categories[$i], $this->categories[$j]] = [$this->categories[$j], $this->categories[$i]];
    }

    public function resetCategories(): void
    {
        $this->categories = EventBudgetItem::DEFAULT_CATEGORIES;
    }

    public function save(): void
    {
        Gate::authorize('write');
        $this->validate([
            'fee' => ['required', 'numeric', 'min:0', 'max:100'],
            'approvalThreshold' => ['nullable', 'numeric', 'min:0'],
        ]);

        CompanyProfile::current()->update([
            'default_budget_categories' => array_values(array_filter(array_map('trim', $this->categories))),
            'default_ticket_types' => array_values(array_filter(array_map('trim', $this->tickets))),
            'default_management_fee_pct' => (float) $this->fee,
            'approval_threshold_cents' => $this->approvalThreshold !== ''
                ? (int) round((float) $this->approvalThreshold * 100)
                : null,
        ]);

        session()->flash('status', 'Defaults saved — new events will use them.');
    }

    public function render()
    {
        return view('livewire.defaults-settings');
    }
}
