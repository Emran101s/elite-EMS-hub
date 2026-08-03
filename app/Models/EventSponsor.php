<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'name', 'package', 'amount_cents', 'paid_cents', 'payment_status', 'booth', 'logo_path', 'notes'])]
class EventSponsor extends Model
{
    public const PAYMENT_STATUSES = ['pending', 'partial', 'paid'];

    /**
     * Label + badge class per payment status, shared by the sponsors tab and
     * the summary card — 'pending' reads as neutral (nothing received yet),
     * distinct from 'partial' (in progress); the generic <x-status-badge>
     * treats any 'pending' as its amber tone, which flattens that distinction.
     *
     * @return array<string,array{0:string,1:string}>
     */
    public static function paymentStatusMeta(): array
    {
        return [
            'pending' => ['Pending', 'bg-navy-100 text-navy-600'],
            'partial' => ['Partial', 'bg-amber-100 text-amber-700'],
            'paid' => ['Paid', 'bg-emerald-100 text-emerald-700'],
        ];
    }

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
