<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A physical exhibition hall / area for an event — its own real dimensions
 * (metres), booths and fixtures. An event can have several.
 */
#[Fillable(['event_id', 'name', 'width_m', 'length_m', 'position', 'fixtures'])]
class EventExhibitionHall extends Model
{
    protected function casts(): array
    {
        return [
            'width_m' => 'float',
            'length_m' => 'float',
            'position' => 'integer',
            'fixtures' => 'array',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function exhibitors(): HasMany
    {
        return $this->hasMany(EventExhibitor::class, 'hall_id');
    }
}
