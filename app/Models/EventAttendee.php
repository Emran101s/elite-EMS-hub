<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable([
    'tenant_id',
    'event_id', 'name', 'email', 'phone', 'organization', 'job_title',
    'ticket_type', 'status', 'amount_cents', 'vip', 'dietary', 'notes', 'answers', 'checked_in_at',
])]
class EventAttendee extends Model
{
    use BelongsToTenant;

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
    public function sessions(): BelongsToMany
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
     * The short code printed on the badge for humans to read aloud.
     *
     * Derived from the id rather than stored: it cannot drift, cannot collide,
     * and there is no column to backfill. Base 36 keeps it short enough to
     * read down a phone when a scanner will not cooperate.
     *
     * This is NOT what the QR URL looks up — see checkInCode(). A sequential
     * id on its own is guessable once the event token is known.
     */
    public function reference(): string
    {
        return strtoupper(str_pad(base_convert((string) $this->id, 10, 36), 4, '0', STR_PAD_LEFT));
    }

    /**
     * Opaque check-in code embedded in the badge QR.
     *
     * Format: {reference}-{hmac}. The reference alone is useless — the HMAC
     * is keyed by the event's check-in token and the app key, so forging a
     * scan for car #42 requires forging the signature, not just counting up.
     */
    public function checkInCode(): string
    {
        return strtolower($this->reference()).'-'.$this->checkInSignature();
    }

    public function checkInSignature(): string
    {
        return substr(hash_hmac('sha256', $this->event_id.'|'.$this->id, $this->checkInSigningKey()), 0, 12);
    }

    private function checkInSigningKey(): string
    {
        $event = $this->relationLoaded('event') ? $this->event : $this->event()->first();

        return ($event?->checkinToken() ?? '').'|'.(string) config('app.key');
    }

    /** Find an attendee of this event by the human-readable badge code. */
    public static function findByReference(int $eventId, string $reference): ?self
    {
        $id = (int) base_convert(ltrim(strtolower(trim($reference)), '0') ?: '0', 36, 10);

        return $id > 0 ? self::where('event_id', $eventId)->find($id) : null;
    }

    /**
     * Resolve a QR payload for this event.
     *
     * Prefers the signed check-in code. Bare references are only accepted when
     * `$allowLegacy` is true (old QRs that still carry the registration token)
     * so a guessed badge number against the check-in token cannot open the door.
     */
    public static function findForCheckIn(int $eventId, string $code, bool $allowLegacy = false): ?self
    {
        $code = trim($code);

        if (str_contains($code, '-')) {
            [$ref, $sig] = explode('-', strtolower($code), 2);
            $attendee = self::findByReference($eventId, $ref);

            if ($attendee && hash_equals($attendee->checkInSignature(), $sig)) {
                return $attendee;
            }

            return null;
        }

        return $allowLegacy ? self::findByReference($eventId, $code) : null;
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
