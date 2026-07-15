<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'name', 'package', 'amount_cents', 'paid_cents', 'payment_status', 'booth', 'logo_path', 'notes'])]
class EventSponsor extends Model
{
    public const PAYMENT_STATUSES = ['pending', 'partial', 'paid'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer', 'paid_cents' => 'integer'];
    }

    public function outstandingCents(): int
    {
        return max(0, $this->amount_cents - $this->paid_cents);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
