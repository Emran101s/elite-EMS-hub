<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventContract;
use Livewire\Component;

class ContractTab extends Component
{
    public Event $event;

    public int $contractId;

    /** @var array<string,mixed> */
    public array $data = [];

    public string $reference = '';

    public string $status = 'draft';

    public function mount(Event $event): void
    {
        $this->event = $event;
        $c = EventContract::forEvent($event);
        $this->contractId = $c->id;
        $this->data = $c->data;
        $this->reference = $c->reference;
        $this->status = $c->status;
    }

    public function updated(string $name): void
    {
        if (str_starts_with($name, 'data') || $name === 'reference') {
            $this->persist();
        }
    }

    public function cycleStatus(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-contract');
        $this->status = match ($this->status) {
            'draft' => 'sent',
            'sent' => 'signed',
            default => 'draft',
        };
        $this->persist();
    }

    /** Pull the latest estimated total from the event budget. */
    public function syncBudget(): void
    {
        $this->data['financials']['estimated_total_cents'] = (int) ($this->event->budget_cents ?? 0);
        $this->persist();
    }

    public function resetContract(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-contract');
        $this->data = EventContract::defaultData($this->event);
        $this->persist();
    }

    private function persist(): void
    {
        $c = EventContract::find($this->contractId);
        if (! $c) {
            return;
        }
        $c->data = $this->data;
        $c->reference = $this->reference;
        $c->status = $this->status;
        $c->signed_at = $this->status === 'signed' ? ($c->signed_at ?? now()) : null;
        $c->save();
    }

    /* ── Payment tracking: the schedule, against reality ── */

    /** Record money received on an installment (amount in currency units). */
    public function recordPayment(int $paymentId, $amount = null): void
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-contract');

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
        \Illuminate\Support\Facades\Gate::authorize('manage-contract');

        $this->contract()->payments()->whereKey($paymentId)
            ->firstOrFail()->update(['paid_cents' => 0, 'paid_at' => null]);
    }

    public function setPaymentDue(int $paymentId, ?string $date): void
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-contract');

        $this->contract()->payments()->whereKey($paymentId)
            ->firstOrFail()->update(['due_on' => $date ?: null]);
    }

    /** Pull schedule/estimate changes onto unpaid installments. */
    public function repricePayments(): void
    {
        \Illuminate\Support\Facades\Gate::authorize('manage-contract');

        $this->contract()->repriceUnpaidPayments();
    }

    private function contract(): EventContract
    {
        return EventContract::findOrFail($this->contractId);
    }

    public function render()
    {
        $contract = $this->contract();
        $contract->ensurePayments();
        $payments = $contract->payments()->get();

        return view('livewire.hub.contract-tab', [
            'payments' => $payments,
            'collected' => $payments->sum('paid_cents'),
            'scheduledTotal' => $payments->sum('amount_cents'),
            'overdueCount' => $payments->filter(fn ($p) => $p->status() === 'overdue')->count(),
        ]);
    }
}
