<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sponsorship package is a per-event catalog entry with a preset price.
 * Selling it to a sponsor auto-fills that amount (see SponsorsTab). The 8
 * defaults are seeded on first use and are fully user-editable.
 */
#[Fillable(['event_id', 'name', 'price_cents', 'slots', 'blurb', 'benefits', 'position'])]
class EventSponsorPackage extends Model
{
    protected function casts(): array
    {
        return ['price_cents' => 'integer', 'slots' => 'integer', 'position' => 'integer', 'benefits' => 'array'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
