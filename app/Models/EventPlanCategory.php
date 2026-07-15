<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A planning category is a per-event phase the user controls — the 4 defaults
 * (Pre-planning, Planning, During the Event, Post-event) seed on first use and
 * are fully editable. Plan items link to it via EventPlanItem::category_id.
 */
#[Fillable(['event_id', 'name', 'position'])]
class EventPlanCategory extends Model
{
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
