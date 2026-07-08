<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'agenda_day_id', 'room_id', 'title', 'type', 'format', 'capacity', 'status', 'starts_at', 'ends_at', 'speaker', 'moderator', 'track', 'description', 'sort'])]
class EventAgendaSession extends Model
{
    public const TYPES = ['opening', 'keynote', 'panel', 'workshop', 'break', 'lunch', 'networking', 'exhibition', 'gala_dinner', 'closing'];

    public const FORMATS = ['in_person' => 'In person', 'hybrid' => 'Hybrid', 'virtual' => 'Virtual'];

    public const STATUSES = ['draft', 'confirmed', 'waiting_speaker', 'needs_review', 'final'];

    protected function casts(): array
    {
        return ['capacity' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function day(): BelongsTo
    {
        return $this->belongsTo(EventAgendaDay::class, 'agenda_day_id');
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(EventRoom::class, 'room_id');
    }
}
