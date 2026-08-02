<?php

namespace App\Models;

use App\Support\Workflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;

#[Fillable(['event_id', 'agenda_day_id', 'room_id', 'title', 'type', 'format', 'capacity', 'status', 'flagged', 'starts_at', 'ends_at', 'speaker', 'moderator', 'track', 'description', 'sort'])]
class EventAgendaSession extends Model
{
    public const TYPES = ['opening', 'keynote', 'panel', 'workshop', 'break', 'lunch', 'networking', 'exhibition', 'gala_dinner', 'closing'];

    public const FORMATS = ['in_person' => 'In person', 'hybrid' => 'Hybrid', 'virtual' => 'Virtual'];

    public const STATUSES = ['draft', 'confirmed', 'waiting_speaker', 'needs_review', 'final'];

    /** status => [label, settled?, hex] — only settled sessions are safe to publish. */
    public const STATUS_META = [
        'draft' => ['Draft', false, '#94A3B8'],
        'waiting_speaker' => ['Awaiting speaker', false, '#F59E0B'],
        'needs_review' => ['Needs review', false, '#F97316'],
        'confirmed' => ['Confirmed', true, '#10B981'],
        'final' => ['Final', true, '#22C55E'],
    ];

    public function statusHex(): string
    {
        return Workflow::color('session_status', $this->status);
    }

    /**
     * Who has booked a seat in this session.
     */
    public function attendees(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(EventAttendee::class, 'attendee_session')
            ->withPivot('checked_in_at')
            ->withTimestamps();
    }

    /** Seats taken. */
    public function bookedCount(): int
    {
        return $this->relationLoaded('attendees')
            ? $this->attendees->count()
            : $this->attendees()->count();
    }

    /** Seats left, or null where the session has no limit. */
    public function seatsLeft(): ?int
    {
        return $this->capacity ? max(0, $this->capacity - $this->bookedCount()) : null;
    }

    /**
     * No room left.
     *
     * A session with no capacity set is never full — an unset limit is "we have
     * not counted the chairs", not "nobody may come".
     */
    public function isFull(): bool
    {
        return $this->capacity > 0 && $this->bookedCount() >= $this->capacity;
    }

    /** A settled session is agreed and safe to put in front of a delegate. */
    public function isSettled(): bool
    {
        return self::STATUS_META[$this->status][1] ?? false;
    }

    public function statusLabel(): string
    {
        return Workflow::label('session_status', $this->status);
    }

    /** Speaking roles on a session, in billing order (moderator leads the panel). */
    public const SPEAKER_ROLES = [
        'keynote' => 'Keynote',
        'moderator' => 'Moderator',
        'panelist' => 'Panellist',
        'facilitator' => 'Facilitator',
        'host' => 'Host',
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'flagged' => 'boolean'];
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

    /** Roster speakers on this session, each carrying a `role` on the pivot. */
    public function speakers(): BelongsToMany
    {
        return $this->belongsToMany(EventSpeaker::class, 'agenda_session_speaker', 'agenda_session_id', 'speaker_id')
            ->withPivot(['role', 'sort'])
            ->withTimestamps()
            ->orderBy('agenda_session_speaker.sort');
    }

    /** Speakers grouped by role, ordered as SPEAKER_ROLES declares. */
    public function speakersByRole(): Collection
    {
        return $this->speakers
            ->groupBy(fn ($s) => $s->pivot->role)
            ->sortBy(fn ($group, $role) => array_search($role, array_keys(self::SPEAKER_ROLES), true));
    }

    /**
     * One-line billing for cards and print, e.g.
     * "Moderator: Dr Salim · Panellists: A, B, C".
     */
    public function speakerLine(): string
    {
        if ($this->speakers->isEmpty()) {
            return trim(collect([$this->speaker, $this->moderator])->filter()->implode(' · '));
        }

        return $this->speakersByRole()
            ->map(function ($group, $role) {
                $label = self::SPEAKER_ROLES[$role] ?? ucfirst($role);
                $label = $group->count() > 1 ? str($label)->plural()->toString() : $label;

                return $label.': '.$group->pluck('name')->implode(', ');
            })
            ->implode(' · ');
    }
}
