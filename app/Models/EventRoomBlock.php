<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'supplier_id', 'hotel', 'venue_id', 'room_type', 'occupancy', 'rooms_count', 'rate_cents',
    'check_in', 'check_out', 'status', 'confirmation_number', 'cutoff_on', 'notes', 'position'])]
class EventRoomBlock extends Model
{
    use Auditable;

    /** slug => [label, hex] */
    public const STATUSES = [
        'held' => ['Held', '#F97316'],
        'booked' => ['Booked', '#D4AF37'],
        'confirmed' => ['Confirmed', '#10B981'],
        'cancelled' => ['Cancelled', '#64748B'],
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'cutoff_on' => 'date',
            'rooms_count' => 'integer',
            'rate_cents' => 'integer',
            'position' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** The rooming list — one row per named room. */
    public function rooms(): HasMany
    {
        return $this->hasMany(EventAccommodation::class, 'block_id')->orderBy('position')->orderBy('id');
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusHex(): string
    {
        return self::STATUSES[$this->status][1] ?? '#64748B';
    }

    public function nights(): int
    {
        return $this->check_in && $this->check_out
            ? (int) $this->check_in->diffInDays($this->check_out)
            : 0;
    }

    /** Rooms with a named guest. Uses the loaded relation when it's there. */
    public function filled(): int
    {
        return $this->relationLoaded('rooms')
            ? $this->rooms->whereNotNull('guest')->where('guest', '!=', '')->count()
            : $this->rooms()->whereNotNull('guest')->where('guest', '!=', '')->count();
    }

    public function remaining(): int
    {
        return max(0, $this->rooms_count - $this->filled());
    }

    public function fillPct(): int
    {
        return $this->rooms_count > 0
            ? (int) round($this->filled() / $this->rooms_count * 100)
            : 0;
    }

    public function isFull(): bool
    {
        return $this->remaining() === 0 && $this->rooms_count > 0;
    }

    /** Internal only — rate × rooms × nights. Never rendered on a rooming list. */
    public function totalCents(): int
    {
        return $this->rate_cents * $this->rooms_count * max(1, $this->nights());
    }

    public function roomNights(): int
    {
        return $this->rooms_count * $this->nights();
    }

    /** True room-nights: the sum of each named guest's own stay, not block × rooms. */
    public function namedRoomNights(): int
    {
        $rooms = $this->relationLoaded('rooms') ? $this->rooms : $this->rooms()->get();

        return $rooms->filter(fn ($r) => filled($r->guest))->sum(fn ($r) => $r->roomNights());
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
