<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A demand for money, issued on a date, with a number on it.
 *
 * The book already knows what is scheduled and what has arrived; neither of
 * those is an invoice. This is the document a client's accounts department pays
 * against, so it carries its own lines and its own money rather than pointing
 * at a contract installment and calling that enough.
 *
 * Only the issuing lifecycle is stored — draft, sent, void. Whether it is paid,
 * part paid or overdue is derived from money and dates, exactly as
 * EventContractPayment does it: a document must not be able to claim it is
 * settled while the cash is missing.
 */
class Invoice extends Model
{
    use Auditable;

    /** Issuing and money are decisions; a typo in the notes is not. */
    public const AUDIT_FIELDS = ['status', 'paid_cents', 'paid_at', 'issued_on', 'due_on'];

    /** The states that are STORED — what the office did with the document. */
    public const STATUSES = ['draft', 'sent', 'void'];

    /** state => [label, hex]. Includes the derived money states. */
    public const STATE_META = [
        'draft' => ['Draft', '#94A3B8'],
        'sent' => ['Sent', '#3B82F6'],
        'partial' => ['Part paid', '#D4AF37'],
        'overdue' => ['Overdue', '#DC2626'],
        'paid' => ['Paid', '#22C55E'],
        'void' => ['Void', '#CBD5E1'],
    ];

    protected $fillable = ['event_id', 'contract_id', 'client_id', 'number', 'status',
        'currency', 'issued_on', 'due_on', 'tax_pct', 'paid_cents', 'paid_at',
        'bill_to', 'notes', 'terms'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_at' => 'date',
            'tax_pct' => 'float',
            'paid_cents' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EventContract::class, 'contract_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /* ── money ── */

    public function subtotalCents(): int
    {
        return $this->lines->sum(fn (InvoiceLine $l) => $l->amountCents());
    }

    public function taxCents(): int
    {
        return (int) round($this->subtotalCents() * $this->tax_pct / 100);
    }

    public function totalCents(): int
    {
        return $this->subtotalCents() + $this->taxCents();
    }

    public function outstandingCents(): int
    {
        return max(0, $this->totalCents() - $this->paid_cents);
    }

    /**
     * What the document actually is right now.
     *
     * Draft and void are what the office decided, so they win. Everything else
     * is arithmetic: a sent invoice with the money in is paid, whatever anyone
     * remembered to click.
     */
    public function state(): string
    {
        if (in_array($this->status, ['draft', 'void'], true)) {
            return $this->status;
        }

        $total = $this->totalCents();

        if ($total > 0 && $this->paid_cents >= $total) {
            return 'paid';
        }
        if ($this->paid_cents > 0) {
            return 'partial';
        }
        if ($this->due_on?->isPast()) {
            return 'overdue';
        }

        return 'sent';
    }

    public function stateLabel(): string
    {
        return self::STATE_META[$this->state()][0];
    }

    public function stateHex(): string
    {
        return self::STATE_META[$this->state()][1];
    }

    /** An invoice nobody has sent is owed by nobody. */
    public function isOutstanding(): bool
    {
        return in_array($this->state(), ['sent', 'partial', 'overdue'], true);
    }

    /* ── making one ── */

    /** "EBH-INV-2026-014" — sequential within the year it is raised. */
    public static function nextNumber(?\DateTimeInterface $on = null): string
    {
        $year = ($on ? \Illuminate\Support\Carbon::instance($on) : now())->format('Y');

        $seq = static::where('number', 'like', 'EBH-INV-'.$year.'-%')->count() + 1;

        // A gap can only come from a deleted invoice; walk past it rather than
        // colliding on the unique index.
        do {
            $number = 'EBH-INV-'.$year.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $seq++;
        } while (static::where('number', $number)->exists());

        return $number;
    }

    /**
     * Raise a draft invoice against a contract installment.
     *
     * The line copies the installment's figures rather than referring to them:
     * `payment_id` is provenance, so amending the invoice afterwards can never
     * quietly rewrite the agreement it came from.
     */
    public static function fromPayment(EventContractPayment $payment): self
    {
        $event = $payment->event;
        $contract = $payment->contract;

        $invoice = static::create([
            'event_id' => $payment->event_id,
            'contract_id' => $payment->contract_id,
            'client_id' => $event?->client_id,
            'number' => static::nextNumber(),
            'status' => 'draft',
            'currency' => $event?->currency ?? 'JOD',
            'issued_on' => now()->toDateString(),
            // The installment's own date is the promise that was made; an
            // invoice raised late is still due when the contract says.
            'due_on' => $payment->due_on?->toDateString() ?? now()->addDays(30)->toDateString(),
            'bill_to' => $event?->client?->name,
            // No auto-note naming the agreement: the document already prints
            // "Against agreement X" under Billed to, and the same sentence
            // twice on one page reads as a mistake rather than as emphasis.
        ]);

        $invoice->lines()->create([
            'payment_id' => $payment->id,
            'description' => trim(($event?->name ? $event->name.' — ' : '').$payment->label),
            'qty' => 1,
            'unit_cents' => $payment->amount_cents,
            'sort' => 0,
        ]);

        return $invoice->load('lines');
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
