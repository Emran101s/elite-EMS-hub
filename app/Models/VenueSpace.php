<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One of a venue's own permanent halls/rooms — the thing an EventRoom books,
 * not the booking itself. Existed nowhere before Venue Studio: rooms used to
 * live only per-event, recreated from scratch every time, with no memory that
 * the same physical Main Hall had ever been used before.
 */
#[Fillable(['tenant_id', 'venue_id', 'name', 'type', 'capacity', 'capacity_by_setup',
    'width_m', 'length_m', 'floor_zone', 'position', 'notes'])]
class VenueSpace extends Model
{
    use BelongsToTenant;

    public const TYPES = ['main_hall', 'breakout', 'exhibition', 'registration',
        'vip', 'catering', 'networking', 'loading_dock', 'speaker_prep'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'capacity_by_setup' => 'array',
            'width_m' => 'float',
            'length_m' => 'float',
            'position' => 'integer',
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    /** Every event booking that has claimed this physical space. */
    public function bookings(): HasMany
    {
        return $this->hasMany(EventRoom::class);
    }

    /** The booking whose event is running today, if any — drives the occupancy tile colour. */
    public function currentBooking(): ?EventRoom
    {
        return $this->bookings()
            ->whereHas('event', fn ($q) => $q->whereDate('starts_at', '<=', now())
                ->whereDate('ends_at', '>=', now()))
            ->with('event')
            ->first();
    }

    public function areaM2(): ?float
    {
        return $this->width_m && $this->length_m ? round($this->width_m * $this->length_m, 1) : null;
    }

    /** The capacity this space seats under a given setup — null if never entered. */
    public function capacityFor(string $setup): ?int
    {
        return $this->capacity_by_setup[$setup] ?? null;
    }
}
