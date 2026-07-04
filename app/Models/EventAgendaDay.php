<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'date', 'label', 'sort'])]
class EventAgendaDay extends Model
{
    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventAgendaSession::class, 'agenda_day_id')->orderBy('starts_at');
    }
}
