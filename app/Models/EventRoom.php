<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'name', 'type', 'capacity'])]
class EventRoom extends Model
{
    public const TYPES = ['main_hall', 'breakout', 'exhibition', 'registration', 'vip', 'catering'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(EventAgendaSession::class, 'room_id');
    }
}
