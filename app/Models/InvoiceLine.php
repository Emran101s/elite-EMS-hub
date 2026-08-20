<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line on an invoice.
 *
 * It carries its own numbers. `payment_id` says where the line came from when
 * it was raised off a contract's schedule — provenance, not a live link, so
 * correcting a typo on an invoice cannot edit the agreement behind it.
 */
class InvoiceLine extends Model
{
    use BelongsToTenant;

    protected $fillable = ['tenant_id', 'invoice_id', 'payment_id', 'description', 'qty', 'unit_cents', 'sort'];

    /**
     * A line changing changes the invoice, and the invoice owes its
     * installments an answer.
     *
     * Without this, editing a line after money had been recorded left the
     * schedule holding an allocation of a total that no longer exists — the
     * two ledgers drift apart again, quietly, at exactly the moment somebody
     * is correcting a mistake.
     */
    protected static function booted(): void
    {
        $resync = function (self $line) {
            $line->invoice?->fresh()->load('lines')->syncToSchedule();
        };

        static::saved($resync);
        static::deleted($resync);
    }

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            // decimal:1, not integer — unit_cents is decimal(15,1) now
            // (tenths of a cent), so a unit price of 127.116 keeps its
            // third decimal instead of being rounded away on read.
            'unit_cents' => 'decimal:1',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    /** The installment this line was raised from, if it was. */
    public function payment(): BelongsTo
    {
        return $this->belongsTo(EventContractPayment::class, 'payment_id');
    }

    /**
     * Rounded once, here, so a total is never the sum of unrounded halves.
     *
     * Rounded to a tenth of a cent, not a whole one — unit_cents itself
     * carries that same tenth now, and rounding it away here would just
     * reintroduce the truncation at one remove.
     */
    public function amountCents(): float
    {
        return round($this->qty * (float) $this->unit_cents, 1);
    }
}
