<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id',
    'event_id', 'hall_id', 'company', 'contact_name', 'email', 'phone', 'booth_number', 'booth_size', 'package', 'fee_cents', 'paid_cents', 'status', 'notes', 'booth_x', 'booth_y', 'booth_w_m', 'booth_h_m'])]
class EventExhibitor extends Model
{
    use BelongsToTenant;

    public const PACKAGES = ['standard', 'premium', 'island', 'custom'];

    public const STATUSES = ['reserved', 'confirmed', 'paid', 'cancelled'];

    protected function casts(): array
    {
        return [
            'fee_cents' => 'integer',
            'paid_cents' => 'integer',
            'booth_x' => 'float',
            'booth_y' => 'float',
            'booth_w_m' => 'float',
            'booth_h_m' => 'float',
        ];
    }

    /** Placed on a hall's floor plan (assigned + has coordinates)? */
    public function placed(): bool
    {
        return $this->hall_id !== null && $this->booth_x !== null;
    }

    public function outstandingCents(): int
    {
        return max(0, $this->fee_cents - $this->paid_cents);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(EventExhibitionHall::class, 'hall_id');
    }

    /** The booth this exhibitor bought, if any. */
    public function booth(): HasOne
    {
        return $this->hasOne(EventBooth::class, 'exhibitor_id');
    }
}
