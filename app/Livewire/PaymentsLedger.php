<?php

namespace App\Livewire;

use App\Models\EventContractPayment;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every installment in the book, in the order the money is due.
 *
 * A contract's schedule lives inside its own event, which is the right place
 * to write one and the wrong place to collect from: "who owes us this month"
 * is a question about the book, and it used to be answered by opening events
 * one at a time and adding up by hand.
 *
 * Status is never stored — EventContractPayment derives it from money and
 * dates, so an installment cannot claim to be settled while cash is missing.
 * This page reads that helper rather than re-deciding, and its actions are the
 * same two the Contract tab already offers, behind the same gate.
 */
#[Layout('components.layouts.app', [
    'title' => 'Payments',
    'subtitle' => 'Every installment in the book, in the order the money is due.',
])]
class PaymentsLedger extends Component
{
    /** all · overdue · pending · partial · paid */
    #[Url(as: 'status')]
    public string $status = 'all';

    #[Url(as: 'q')]
    public string $q = '';

    /** Amounts typed into a row, keyed by payment id. Blank settles in full. */
    public array $amount = [];

    public function setStatus(string $status): void
    {
        $this->status = in_array($status, ['all', 'overdue', 'pending', 'partial', 'paid'], true)
            ? $status : 'all';
    }

    /**
     * Record money received. Blank settles the installment in full — the same
     * rule the Contract tab uses, because two places that record a payment
     * differently is one place too many.
     */
    public function record(int $paymentId, $amount = null): void
    {
        Gate::authorize('manage-contract');

        $p = EventContractPayment::findOrFail($paymentId);

        $cents = $amount === null || $amount === '' || ! is_numeric($amount)
            ? $p->outstandingCents()
            : max(0, (int) round((float) $amount * 100));

        $p->update([
            'paid_cents' => min($p->amount_cents, $p->paid_cents + $cents),
            'paid_at' => now()->toDateString(),
        ]);

        unset($this->amount[$paymentId]);
    }

    /** Undo a recorded payment — a typo, or a transfer that bounced. */
    public function clear(int $paymentId): void
    {
        Gate::authorize('manage-contract');

        EventContractPayment::findOrFail($paymentId)
            ->update(['paid_cents' => 0, 'paid_at' => null]);
    }

    private function installments(): Collection
    {
        return EventContractPayment::query()
            ->with(['event.client', 'contract'])
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->get()
            ->filter(function (EventContractPayment $p) {
                if ($this->status !== 'all' && $p->status() !== $this->status) {
                    return false;
                }
                if ($this->q === '') {
                    return true;
                }

                $hay = mb_strtolower(implode(' ', array_filter([
                    $p->label, $p->event?->name, $p->event?->client?->name,
                    $p->contract?->reference, $p->contract?->displayTitle(),
                ])));

                return str_contains($hay, mb_strtolower(trim($this->q)));
            })
            ->values();
    }

    public function render()
    {
        $rows = $this->installments()
            // Undated installments sort last rather than as the epoch — an
            // installment with no due date is not overdue since 1970.
            ->sortBy(fn (EventContractPayment $p) => $p->due_on?->timestamp ?? PHP_INT_MAX)
            ->values();

        // Grouped by the month the money is due, with a subtotal, because that
        // is the unit collection actually happens in. Undated installments get
        // their own group at the end rather than being hidden or guessed at.
        $months = $rows
            ->groupBy(fn (EventContractPayment $p) => $p->due_on?->format('Y-m') ?? 'undated')
            ->map(fn (Collection $group, string $key) => [
                'key' => $key,
                'label' => $key === 'undated' ? 'No date set' : $group->first()->due_on->format('F Y'),
                'rows' => $group,
                'due' => $group->sum(fn ($p) => $p->outstandingCents()),
                // "Settled" means every installment is settled, not that the
                // outstanding total rounds to nothing: a zero-value installment
                // still past its date reads as Overdue on its own row, and a
                // month calling itself settled above it is a contradiction on
                // one screen.
                'settled' => $group->every(fn ($p) => $p->status() === 'paid'),
            ])
            ->values();

        // These four are counted across everything in the book, not across the
        // filtered view — a filter narrows what you are reading, not what you
        // are owed. Only the row count follows the filter.
        $all = EventContractPayment::query()
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))->get();

        $overdue = $all->filter(fn ($p) => $p->status() === 'overdue');
        $thisMonth = $all->filter(fn ($p) => $p->due_on?->isSameMonth(now()) && $p->status() !== 'paid');

        return view('livewire.payments-ledger', [
            'months' => $months,
            'rows' => $rows,
            'figures' => [
                ['label' => 'Overdue', 'value' => $this->money($overdue->sum(fn ($p) => $p->outstandingCents())),
                    'icon' => 'bell', 'tone' => $overdue->isEmpty() ? 'green' : 'red',
                    'note' => $overdue->count().' '.str('installment')->plural($overdue->count())],
                ['label' => 'Due this month', 'value' => $this->money($thisMonth->sum(fn ($p) => $p->outstandingCents())),
                    'icon' => 'calendar', 'tone' => 'gold',
                    'note' => now()->format('F Y')],
                ['label' => 'Outstanding', 'value' => $this->money($all->sum(fn ($p) => $p->outstandingCents())),
                    'icon' => 'chart', 'tone' => 'blue', 'note' => 'Across the whole book'],
                ['label' => 'Collected', 'value' => $this->money($all->sum('paid_cents')),
                    'icon' => 'check', 'tone' => 'green', 'note' => 'Received to date'],
                ['label' => 'Scheduled', 'value' => $this->money($all->sum('amount_cents')),
                    'icon' => 'currency', 'tone' => 'navy',
                    'note' => $all->count().' '.str('installment')->plural($all->count())],
            ],
        ]);
    }

    private function money(int $cents): string
    {
        $v = $cents / 100;

        return 'JD'.match (true) {
            abs($v) >= 1_000_000 => round($v / 1_000_000, 1).'M',
            abs($v) >= 1_000 => round($v / 1_000).'K',
            default => number_format($v),
        };
    }
}
