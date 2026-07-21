<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Plan Studio track — a user-controlled group (swimlane) that plan items
 * are organised into. Fully editable per event.
 */
#[Fillable(['event_id', 'name', 'goal', 'color', 'position'])]
class PlanTrack extends Model
{
    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PlanItem::class, 'track_id')->orderBy('position')->orderBy('id');
    }
}
