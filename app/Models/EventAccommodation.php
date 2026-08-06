<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id',
    'event_id', 'block_id', 'hotel', 'venue_id', 'guest', 'attendee_id', 'guest_email', 'guest_phone', 'sharing_with', 'room_type', 'occupancy', 'rooms', 'check_in', 'arrival_time', 'arrival_note', 'check_out', 'departure_time', 'departure_note', 'rate_cents', 'cost_cents', 'status', 'confirmation_number', 'notes', 'position'])]
class EventAccommodation extends Model
{
    use BelongsToTenant;

    protected $table = 'event_accommodations';

    public const STATUSES = ['held', 'booked', 'confirmed', 'cancelled'];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'rooms' => 'integer',
            'rate_cents' => 'integer',
            'cost_cents' => 'integer',
        ];
    }

    /** Nights between check-in and check-out. */
    public function nights(): int
    {
        if (! $this->check_in || ! $this->check_out) {
            return 0;
        }

        return max(0, $this->check_in->diffInDays($this->check_out));
    }

    /** Room-nights = rooms × nights. */
    public function roomNights(): int
    {
        return $this->rooms * $this->nights();
    }

    /** How many sleep in the room — what the hotel allocates beds for. */
    public const OCCUPANCIES = ['single' => 'Single', 'double' => 'Double', 'twin' => 'Twin',
        'triple' => 'Triple', 'quad' => 'Quad'];

    /** Common grades, offered as suggestions — the field stays free text. */
    public const CATEGORIES = ['Standard', 'Superior', 'Deluxe', 'Executive', 'Club', 'Junior Suite', 'Suite'];

    /** The attendee this room belongs to, when the guest came from that list. */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(EventAttendee::class, 'attendee_id');
    }

    public function occupancyLabel(): string
    {
        return self::OCCUPANCIES[$this->occupancy] ?? '';
    }

    public function block(): BelongsTo
    {
        return $this->belongsTo(EventRoomBlock::class, 'block_id');
    }

    /** A rooming-list line is only "named" once a guest is on it. */
    public function isNamed(): bool
    {
        return filled($this->guest);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * The hotel, when it is one of yours from the directory.
     *
     * `hotel` stays alongside it as the name this row was made with — a
     * rooming list printed last month should keep reading the same.
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
