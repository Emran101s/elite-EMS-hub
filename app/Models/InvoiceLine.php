<?php

namespace App\Models;

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
    protected $fillable = ['invoice_id', 'payment_id', 'description', 'qty', 'unit_cents', 'sort'];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_cents' => 'integer',
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

    /** Rounded once, here, so a total is never the sum of unrounded halves. */
    public function amountCents(): int
    {
        return (int) round($this->qty * $this->unit_cents);
    }
}
