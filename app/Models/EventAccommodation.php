<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'hotel', 'guest', 'room_type', 'rooms', 'check_in', 'check_out', 'rate_cents', 'cost_cents', 'status', 'confirmation_number', 'notes'])]
class EventAccommodation extends Model
{
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

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
