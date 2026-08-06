<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;

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
    use Auditable, SoftDeletes;
    use BelongsToTenant;

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

    protected $fillable = ['tenant_id', 'event_id', 'contract_id', 'client_id', 'number', 'status',
        'currency', 'issued_on', 'due_on', 'tax_pct', 'fee_pct', 'paid_cents', 'paid_at',
        'bill_to', 'notes', 'terms'];

    /**
     * Money recorded here lands on the schedule it was raised from.
     *
     * Without this the two ledgers contradict each other about the same money:
     * an invoice collected in full while its installment still reads "overdue,
     * JD52,500 outstanding". A hook rather than a call at each site, because a
     * fourth place that writes paid_cents one day would otherwise reintroduce
     * the divergence silently.
     */
    protected static function booted(): void
    {
        static::saved(function (self $invoice) {
            if ($invoice->wasChanged(['paid_cents', 'paid_at', 'status'])) {
                $invoice->syncToSchedule();
            }
        });

        // A deleted invoice never collected anything. Only a draft can be
        // deleted, so in practice this releases an installment that was
        // invoiced by mistake.
        static::deleted(fn (self $invoice) => $invoice->releaseSchedule());
    }

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'due_on' => 'date',
            'paid_at' => 'date',
            'tax_pct' => 'float',
            'fee_pct' => 'float',
            'paid_cents' => 'integer',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * The currency this document is written in.
     *
     * Its own, because a document may deliberately be raised in a client's
     * currency; then the event's, when it has one and never chose for itself;
     * then the house currency. What it is not is a hardcoded 'JOD' — which is
     * what the screens fell back to, so a dollar event could show a dinar
     * total to anyone whose document predated the currency being set.
     */
    /**
     * Named `currencyCode`, not `currency`.
     *
     * A method sharing a column's name is only ever consulted when the
     * attribute is missing — and then Eloquent reaches it through the relation
     * resolver and demands a relationship instance. It fails exactly on the
     * partially-loaded models that made the method necessary.
     */
    public function currencyCode(): string
    {
        // The raw attribute, not $this->currency: models run in strict mode, so
        // reading an attribute that was never loaded throws rather than
        // returning null — and a method sharing the column's name is exactly
        // where that bites.
        return ($this->attributes['currency'] ?? null)
            ?: ($this->event?->currency ?: CompanyProfile::currency());
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

    /**
     * The management fee, as a share of the work — the same shape as the tax.
     *
     * Its own row on the document rather than smeared across the lines,
     * because that is what a client expects to see and what an argument about
     * the bill is actually about.
     */
    public function feeCents(): int
    {
        return (int) round($this->subtotalCents() * (float) $this->fee_pct / 100);
    }

    /** What is being charged before tax: the work plus the fee on it. */
    public function netCents(): int
    {
        return $this->subtotalCents() + $this->feeCents();
    }

    /**
     * Tax sits on the net, fee included.
     *
     * The fee is part of what is being charged, so it is part of what is
     * taxed. With no fee this is exactly what it always was.
     */
    public function taxCents(): int
    {
        return (int) round($this->netCents() * $this->tax_pct / 100);
    }

    public function totalCents(): int
    {
        return $this->netCents() + $this->taxCents();
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

    /**
     * Money this invoice has collected that the contract schedule does not know
     * about.
     *
     * An invoice raised from an installment pushes what it collects onto that
     * installment, so counting it again as income would book the same payment
     * twice. An invoice raised for anything else — a one-off, a rebilled
     * expense, work agreed after the contract — has nowhere else to be counted,
     * and was simply invisible: the Budget showed income of nothing while a
     * paid invoice sat in the ledger.
     *
     * A void invoice collected nothing anybody should believe in.
     */
    public function unscheduledPaidCents(): int
    {
        if ($this->status === 'void') {
            return 0;
        }

        $onSchedule = $this->lines->pluck('payment')->filter()->unique('id')->sum('paid_cents');

        return max(0, (int) $this->paid_cents - (int) $onSchedule);
    }

    /* ── the link back to the schedule ── */

    /**
     * Write what has been collected onto the installments this was raised from.
     *
     * The pool is capped at the SUBTOTAL before it is spread: tax is collected
     * on top of the lines and settles no installment, so a client paying an
     * invoice in full settles its lines exactly, with nothing left over to
     * overpay the next one with.
     *
     * Allocation runs in line order and each line takes at most its own
     * amount — the same rule an accounts department applies when a part
     * payment arrives against a multi-line invoice.
     */
    public function syncToSchedule(): void
    {
        // Cast, do not trust: a column DEFAULT lives in the database, not in
        // the model, so paid_cents is still null in memory on the save that
        // follows a create() — and min(null, x) is null, which SQLite then
        // refuses to write into a NOT NULL column.
        $collected = (int) $this->paid_cents;

        // A void invoice collected nothing that the schedule should believe in.
        $pool = $this->status === 'void' ? 0 : min($collected, $this->subtotalCents());

        foreach ($this->lines as $line) {
            $take = min($pool, $line->amountCents());
            $pool -= $take;

            $payment = $line->payment;

            if (! $payment) {
                continue;
            }

            $payment->update([
                'paid_cents' => min((int) $payment->amount_cents, $take),
                'paid_at' => $take > 0 ? ($this->paid_at?->toDateString() ?? now()->toDateString()) : null,
            ]);
        }
    }

    /** Hand the installments back: this invoice is no longer asking for them. */
    public function releaseSchedule(): void
    {
        foreach ($this->lines as $line) {
            $line->payment?->update(['paid_cents' => 0, 'paid_at' => null]);
        }
    }

    /* ── making one ── */

    /** "EBH-INV-2026-014" — sequential within the year it is raised. */
    public static function nextNumber(?\DateTimeInterface $on = null): string
    {
        $year = ($on ? Carbon::instance($on) : now())->format('Y');

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
     * Create an invoice with a fresh number, retrying the number on collision.
     *
     * nextNumber() checks the number is free, then this inserts it — two
     * requests raising an invoice at nearly the same moment can both see the
     * same gap and both try to claim it, and the second one hit the unique
     * index as a 500 instead of just getting the next number along.
     */
    public static function createNumbered(array $attributes): self
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return static::create(array_merge($attributes, ['number' => static::nextNumber()]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 5) {
                    throw $e;
                }
            }
        }
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

        $invoice = static::createNumbered([
            'event_id' => $payment->event_id,
            'contract_id' => $payment->contract_id,
            'client_id' => $event?->client_id,
            'status' => 'draft',
            // The event's own currency where it has one, the house currency
            // otherwise — never a constant nobody can change.
            'currency' => $event?->currency ?: CompanyProfile::currency(),
            'issued_on' => now()->toDateString(),
            // The installment's own date is the promise that was made; an
            // invoice raised late is still due when the contract says.
            'due_on' => $payment->due_on?->toDateString() ?? now()->addDays(30)->toDateString(),
            'bill_to' => $event?->client?->name,
            // No fee on this one, deliberately. A contract installment is a
            // share of the contract VALUE, and that value already includes the
            // management fee — adding it again here bills the client twice for
            // the same thing. A fee belongs on an invoice built from raw
            // services, which is what a blank invoice is.
            'fee_pct' => 0,
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
