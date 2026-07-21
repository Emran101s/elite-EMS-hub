<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['event_id', 'name', 'title', 'organization', 'topic', 'bio', 'email', 'phone', 'photo_url', 'is_keynote', 'status', 'fee_cents', 'sort_order'])]
class EventSpeaker extends Model
{
    public const STATUSES = ['invited', 'confirmed', 'declined', 'cancelled'];

    protected function casts(): array
    {
        return [
            'is_keynote' => 'boolean',
            'fee_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function initials(): string
    {
        return collect(explode(' ', trim($this->name)))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->implode('');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** Every agenda session this person appears on, with their role on the pivot. */
    public function sessions(): BelongsToMany
    {
        return $this->belongsToMany(EventAgendaSession::class, 'agenda_session_speaker', 'speaker_id', 'agenda_session_id')
            ->withPivot(['role', 'sort'])
            ->withTimestamps();
    }
}
