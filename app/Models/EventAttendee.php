<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id', 'name', 'email', 'phone', 'organization', 'job_title',
    'ticket_type', 'status', 'amount_cents', 'vip', 'dietary', 'notes', 'answers', 'checked_in_at',
])]
class EventAttendee extends Model
{
    /** Registration lifecycle. */
    public const STATUSES = ['registered', 'confirmed', 'checked_in', 'cancelled'];

    /** [label, pill classes] per status. */
    public const STATUS_META = [
        'registered' => ['Registered', 'bg-navy-100 text-navy-600'],
        'confirmed' => ['Confirmed', 'bg-blue-100 text-blue-700'],
        'checked_in' => ['Checked in', 'bg-emerald-100 text-emerald-700'],
        'cancelled' => ['Cancelled', 'bg-navy-100 text-navy-400 line-through'],
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'answers' => 'array',
            'vip' => 'boolean',
            'checked_in_at' => 'datetime',
        ];
    }

    /**
     * The sessions this person is booked into.
     *
     * A pivot rather than an answer: an answer is a string on a person, and a
     * string cannot be counted against a room's capacity or handed to whoever
     * is standing at the door.
     */
    public function sessions(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(EventAgendaSession::class, 'attendee_session')
            ->withPivot('checked_in_at')
            ->withTimestamps();
    }

    /**
     * What this person answered to a question that has no column of its own.
     *
     * A multi-select comes back as a list; everything else as a string. Both
     * are printed the same way, so a caller never has to know which it was.
     */
    public function answer(string $key): string
    {
        $value = ($this->answers ?? [])[$key] ?? null;

        if (is_array($value)) {
            return implode(', ', $value);
        }

        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }

        return trim((string) $value);
    }

    /**
     * The short code on the badge, and what a check-in scan looks up.
     *
     * Derived from the id rather than stored: it cannot drift, cannot collide,
     * and there is no column to backfill for the thousands of attendees who
     * were imported before badges existed. Base 36 keeps it short enough to
     * read aloud down a phone when a scanner will not cooperate.
     */
    public function reference(): string
    {
        return strtoupper(str_pad(base_convert((string) $this->id, 10, 36), 4, '0', STR_PAD_LEFT));
    }

    /** Find an attendee of this event by the code on their badge. */
    public static function findByReference(int $eventId, string $reference): ?self
    {
        $id = (int) base_convert(ltrim(strtolower(trim($reference)), '0') ?: '0', 36, 10);

        return $id > 0 ? self::where('event_id', $eventId)->find($id) : null;
    }

    public function statusMeta(): array
    {
        return self::STATUS_META[$this->status] ?? self::STATUS_META['registered'];
    }

    public function initials(): string
    {
        return str($this->name)->explode(' ')->filter()
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
