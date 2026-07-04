<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'name', 'package', 'amount_cents', 'payment_status', 'booth', 'logo_path', 'notes'])]
class EventSponsor extends Model
{
    public const PACKAGES = ['platinum', 'gold', 'silver', 'bronze', 'strategic', 'supporting'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
