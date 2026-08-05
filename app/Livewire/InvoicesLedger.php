<?php

namespace App\Livewire;

use App\Models\CompanyProfile;
use App\Models\EventContractPayment;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every invoice in the book, and what is still waiting to become one.
 *
 * Payments answers "what did we agree would arrive, and did it". This answers
 * the step between: the document that has to leave the building before anybody
 * can pay anything. Raising one off a contract installment is a click, which is
 * why the page opens with what has not been raised yet rather than with an
 * empty table and a New button.
 *
 * Draft and void are what the office decided; paid, part paid and overdue are
 * arithmetic. Invoice::state() owns that distinction and this page only reads
 * it — a sent invoice with the money in is paid, whatever anyone clicked.
 */
#[Layout('components.layouts.app', [
    'title' => 'Invoices',
    'subtitle' => 'What has been billed, what is owed, and what is still waiting to be raised.',
])]
class InvoicesLedger extends Component
{
    /** all · draft · sent · overdue · partial · paid · void */
    #[Url(as: 'state')]
    public string $state = 'all';

    #[Url(as: 'q')]
    public string $q = '';

    /** Amounts typed into a row, keyed by invoice id. Blank settles in full. */
    public array $amount = [];

    /** Whether the "ready to invoice" drawer is open. */
    public bool $showReady = true;

    public function setState(string $state): void
    {
        $this->state = in_array($state, ['all', 'draft', 'sent', 'overdue', 'partial', 'paid', 'void'], true)
            ? $state : 'all';
    }

    public function toggleReady(): void
    {
        $this->showReady = ! $this->showReady;
    }

    /* ── raising ── */

    /** Raise a draft invoice against a contract installment. */
    public function raise(int $paymentId)
    {
        Gate::authorize('manage-contract');

        $payment = EventContractPayment::with(['event.client', 'contract'])->findOrFail($paymentId);

        // Raising twice off one installment is the mistake this page makes
        // easiest to make, so it is the one it refuses. A VOIDED invoice is
        // not asking for anything, so it does not block — that is the whole
        // difference between voiding a document and deleting it.
        if (self::billedPaymentIds()->contains($payment->id)) {
            return;
        }

        $invoice = Invoice::fromPayment($payment);

        // Straight into the editor: a raised invoice almost always needs a
        // second line, a date or a word before it goes anywhere.
        return $this->redirectRoute('invoices.edit', $invoice, navigate: true);
    }

    /**
     * A blank invoice, for the work no schedule describes.
     *
     * Raising from an installment covers the contracted work; everything else
     * — a one-off, a rebilled expense, a retainer — had nowhere to start.
     */
    public function create()
    {
        Gate::authorize('manage-contract');

        $invoice = Invoice::createNumbered([
            'status' => 'draft',
            'currency' => CompanyProfile::currency(),
            // The house fee, so it is on the document before anybody has to
            // remember it. Attaching an event in the editor re-reads it from
            // that event, which is where a negotiated rate lives.
            'fee_pct' => CompanyProfile::feePct(),
            'issued_on' => now()->toDateString(),
            'due_on' => now()->addDays(30)->toDateString(),
        ]);

        return $this->redirectRoute('invoices.edit', $invoice, navigate: true);
    }

    /* ── the life of a document ── */

    public function markSent(int $invoiceId): void
    {
        Gate::authorize('manage-contract');

        $invoice = Invoice::findOrFail($invoiceId);

        $invoice->update([
            'status' => 'sent',
            'issued_on' => $invoice->issued_on ?? now()->toDateString(),
        ]);
    }

    public function record(int $invoiceId, $amount = null): void
    {
        Gate::authorize('manage-contract');

        $invoice = Invoice::with('lines')->findOrFail($invoiceId);

        $cents = $amount === null || $amount === '' || ! is_numeric($amount)
            ? $invoice->outstandingCents()
            : max(0, (int) round((float) $amount * 100));

        $invoice->update([
            'paid_cents' => min($invoice->totalCents(), $invoice->paid_cents + $cents),
            'paid_at' => now()->toDateString(),
            // Money arriving against a draft means it was sent and somebody
            // forgot to say so. Recording it says so.
            'status' => $invoice->status === 'draft' ? 'sent' : $invoice->status,
        ]);

        unset($this->amount[$invoiceId]);
    }

    public function clearPaid(int $invoiceId): void
    {
        Gate::authorize('manage-contract');

        Invoice::findOrFail($invoiceId)->update(['paid_cents' => 0, 'paid_at' => null]);
    }

    /** Void keeps the number in the book; deleting a sent invoice does not. */
    public function void(int $invoiceId): void
    {
        Gate::authorize('manage-contract');

        Invoice::findOrFail($invoiceId)->update(['status' => 'void']);
    }

    /**
     * Only a draft can be deleted. Once a document has left the building its
     * number has to stay in the book with a reason beside it — that is what
     * Void is for, and an auditor asking about a missing number is not a
     * conversation anybody wants to have.
     */
    public function destroyDraft(int $invoiceId): void
    {
        Gate::authorize('manage-contract');

        $invoice = Invoice::findOrFail($invoiceId);

        if ($invoice->status === 'draft') {
            $invoice->delete();
        }
    }

    /* ── reading ── */

    private function invoices(): Collection
    {
        return Invoice::query()
            ->with(['lines', 'event.client', 'client', 'contract'])
            ->get()
            ->filter(function (Invoice $i) {
                if ($this->state !== 'all' && $i->state() !== $this->state) {
                    return false;
                }
                if ($this->q === '') {
                    return true;
                }

                $hay = mb_strtolower(implode(' ', array_filter([
                    $i->number, $i->bill_to, $i->event?->name,
                    $i->client?->name, $i->event?->client?->name, $i->contract?->reference,
                ])));

                return str_contains($hay, mb_strtolower(trim($this->q)));
            })
            ->values();
    }

    public function render()
    {
        $rows = $this->invoices()
            // Newest first: an invoice ledger is read from the top, and the one
            // you just raised is the one you are looking for.
            ->sortByDesc(fn (Invoice $i) => [$i->issued_on?->timestamp ?? 0, $i->id])
            ->values();

        // Installments that have never been billed. This is what makes the page
        // useful on the day it ships rather than an empty table with a button.
        $billed = self::billedPaymentIds()->all();

        $ready = EventContractPayment::query()
            ->with(['event.client', 'contract'])
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->whereNotIn('id', $billed ?: [0])
            ->where('amount_cents', '>', 0)
            ->get()
            ->sortBy(fn ($p) => $p->due_on?->timestamp ?? PHP_INT_MAX)
            ->values();

        // Counted across the book, not the filtered view: a filter narrows what
        // you are reading, not what you are owed.
        $all = Invoice::with('lines')->get();
        $outstanding = $all->filter->isOutstanding();
        $overdue = $all->filter(fn (Invoice $i) => $i->state() === 'overdue');

        return view('livewire.invoices-ledger', [
            'rows' => $rows,
            'ready' => $ready,
            'figures' => [
                ['label' => 'Outstanding', 'value' => $this->money($outstanding->sum(fn ($i) => $i->outstandingCents())),
                    'icon' => 'chart', 'tone' => 'blue',
                    'note' => $outstanding->count().' '.str('invoice')->plural($outstanding->count())],
                ['label' => 'Overdue', 'value' => $this->money($overdue->sum(fn ($i) => $i->outstandingCents())),
                    'icon' => 'bell', 'tone' => $overdue->isEmpty() ? 'green' : 'red',
                    'note' => $overdue->count().' past the due date'],
                ['label' => 'Collected', 'value' => $this->money($all->sum('paid_cents')),
                    'icon' => 'check', 'tone' => 'green', 'note' => 'Received against invoices'],
                ['label' => 'Drafts', 'value' => (string) $all->where('status', 'draft')->count(),
                    'icon' => 'document', 'tone' => 'gold', 'note' => 'Not sent yet'],
                ['label' => 'To raise', 'value' => (string) $ready->count(),
                    'icon' => 'sparkles', 'tone' => $ready->isEmpty() ? 'green' : 'violet',
                    'note' => 'Installments never billed'],
            ],
        ]);
    }

    /**
     * Installments a live invoice is already asking for.
     *
     * One definition, used by both the guard and the ready list, or the page
     * offers to raise something it will then refuse to raise.
     */
    private static function billedPaymentIds(): Collection
    {
        return InvoiceLine::whereNotNull('payment_id')
            ->whereHas('invoice', fn ($q) => $q->where('status', '!=', 'void'))
            ->pluck('payment_id');
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
