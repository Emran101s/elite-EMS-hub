<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One contract installment, tracked against reality. Status is derived from
 * money and dates, never stored — a payment can't claim to be settled while
 * cash is missing.
 */
#[Fillable(['event_id', 'contract_id', 'sort', 'label', 'pct', 'amount_cents', 'due_on', 'paid_cents', 'paid_at', 'note'])]
class EventContractPayment extends Model
{
    use \App\Models\Concerns\Auditable;

    /** Money movements and date changes are decisions; the rest is noise. */
    public const AUDIT_FIELDS = ['paid_cents', 'paid_at', 'due_on', 'amount_cents'];

    /** status => [label, hex]. */
    public const STATUS_META = [
        'paid' => ['Paid', '#22C55E'],
        'partial' => ['Partial', '#D4AF37'],
        'overdue' => ['Overdue', '#DC2626'],
        'pending' => ['Pending', '#94A3B8'],
    ];

    protected function casts(): array
    {
        return [
            'pct' => 'float',
            'amount_cents' => 'integer',
            'paid_cents' => 'integer',
            'due_on' => 'date',
            'paid_at' => 'date',
        ];
    }

    public function status(): string
    {
        if ($this->amount_cents > 0 && $this->paid_cents >= $this->amount_cents) {
            return 'paid';
        }
        if ($this->paid_cents > 0) {
            return 'partial';
        }
        if ($this->due_on?->isPast()) {
            return 'overdue';
        }

        return 'pending';
    }

    public function statusLabel(): string
    {
        return self::STATUS_META[$this->status()][0];
    }

    public function outstandingCents(): int
    {
        return max(0, $this->amount_cents - $this->paid_cents);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function contract(): BelongsTo
    {
        return $this->belongsTo(EventContract::class, 'contract_id');
    }

    public function auditLabel(): string
    {
        return 'Installment '.($this->sort + 1).' — '.$this->label;
    }
}
