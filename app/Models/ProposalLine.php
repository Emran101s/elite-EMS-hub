<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One line of an offer.
 *
 * `optional` is the difference between "this is what it costs" and "this is
 * what it could also include": an optional line is quoted so the client can say
 * yes to it, and left out of the total so the headline price is the one they
 * are being asked to agree to.
 */
class ProposalLine extends Model
{
    protected $fillable = ['proposal_id', 'description', 'detail', 'qty', 'unit_cents', 'optional', 'sort'];

    protected function casts(): array
    {
        return [
            'qty' => 'float',
            'unit_cents' => 'integer',
            'optional' => 'boolean',
        ];
    }

    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }

    /** Rounded once, here, so a total is never the sum of unrounded halves. */
    public function amountCents(): int
    {
        return (int) round($this->qty * $this->unit_cents);
    }
}
