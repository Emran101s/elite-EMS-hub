<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One food & beverage occasion — a coffee break, a lunch, a gala dinner.
 *
 * Deliberately not a single "catering" total: a breakfast in the hotel on the
 * 6th and a delegates' dinner at an outside restaurant on the 9th are two
 * different commitments with two different suppliers and two different
 * rates, and lumping them into one figure is how "how much is the gala
 * costing" stops having an answer.
 */
#[Fillable(['event_id', 'title', 'type', 'occasion_date', 'venue_mode', 'room_id', 'location',
    'headcount', 'cost_cents', 'per_person', 'supplier_id', 'status', 'notes', 'sort_order'])]
class EventCateringItem extends Model
{
    /** type => label */
    public const TYPES = [
        'coffee_break' => 'Coffee break',
        'breakfast' => 'Breakfast',
        'lunch' => 'Lunch',
        'dinner' => 'Dinner',
        'reception' => 'Reception',
        'other' => 'Other',
    ];

    public const STATUSES = ['planned', 'confirmed', 'cancelled'];

    /** Whether it happens in one of the event's own rooms, or somewhere else entirely. */
    public const VENUE_MODES = ['in_house', 'outside'];

    protected function casts(): array
    {
        return [
            'occasion_date' => 'date',
            'headcount' => 'integer',
            'cost_cents' => 'integer',
            'per_person' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(EventRoom::class, 'room_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? 'Other';
    }

    /**
     * Where it happens, in one line: the room it's held in, or the place
     * outside — never both, since a restaurant is not a schedulable space.
     */
    public function venueLabel(): string
    {
        if ($this->venue_mode === 'in_house') {
            return $this->room?->name ?? 'A venue room';
        }

        return trim($this->location ?? '') !== '' ? $this->location : 'Outside the venue';
    }

    /**
     * The full cost of this occasion.
     *
     * A per-person rate is quoted per head and multiplied by who's coming — a
     * coffee break at $6 for 60 people is $360, not $6. A flat rate is exactly
     * what it says: hiring the room for a private dinner costs the same
     * whether 40 or 55 people turn up.
     */
    public function totalCents(): int
    {
        if (! $this->per_person) {
            return (int) $this->cost_cents;
        }

        return (int) $this->cost_cents * max(1, (int) ($this->headcount ?? 1));
    }
}
