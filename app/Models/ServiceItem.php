<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One thing the company sells, at a price.
 *
 * The unit is what makes this more than a list of names. Accommodation is not
 * sold "each" — it is sold per room per night, transport per vehicle per day,
 * catering per person. UNITS says what one of a thing is and which numbers it
 * takes to count it, so the invoice editor can ask for rooms AND nights and do
 * the multiplication rather than leaving somebody to do it in their head and
 * type 36 with nothing on the document explaining where 36 came from.
 */
class ServiceItem extends Model
{
    use Auditable;

    public const AUDIT_FIELDS = ['name', 'unit_price_cents', 'unit', 'active'];

    /**
     * unit => [label, one-of, [factor labels]]
     *
     * The factors are the boxes the editor draws, multiplied together to make
     * the quantity. An empty list means the quantity is always one.
     */
    public const UNITS = [
        'item' => ['Each', 'item', ['Quantity']],
        'fixed' => ['Fixed fee', 'engagement', []],
        'day' => ['Per day', 'day', ['Days']],
        'hour' => ['Per hour', 'hour', ['Hours']],
        'person' => ['Per person', 'person', ['People']],
        'person_day' => ['Per person per day', 'person-day', ['People', 'Days']],
        'room_night' => ['Per room per night', 'room-night', ['Rooms', 'Nights']],
        'vehicle_day' => ['Per vehicle per day', 'vehicle-day', ['Vehicles', 'Days']],
        'vehicle_trip' => ['Per vehicle per trip', 'trip', ['Vehicles', 'Trips']],
        'sqm' => ['Per square metre', 'm²', ['Square metres']],
        'session' => ['Per session', 'session', ['Sessions']],
    ];

    protected $fillable = ['code', 'name', 'category', 'detail', 'unit',
        'unit_price_cents', 'currency', 'tax_pct', 'active', 'sort'];

    protected function casts(): array
    {
        return [
            'unit_price_cents' => 'integer',
            'tax_pct' => 'float',
            'active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    public function unitLabel(): string
    {
        return self::UNITS[$this->unit][0] ?? 'Each';
    }

    /** What one of this is, for the line's description: "room-night". */
    public function unitNoun(): string
    {
        return self::UNITS[$this->unit][1] ?? 'item';
    }

    /** @return list<string> the boxes the editor draws for this unit */
    public function factors(): array
    {
        return self::UNITS[$this->unit][2] ?? ['Quantity'];
    }

    /**
     * Turn the factor boxes into a quantity.
     *
     * A blank or missing factor counts as one rather than as nothing — half a
     * filled form should still price something, and an item with no factors at
     * all is a fixed fee, which is exactly one of itself.
     *
     * @param  array<int|string,mixed>  $values
     */
    public function quantityFrom(array $values): float
    {
        $factors = $this->factors();

        if ($factors === []) {
            return 1.0;
        }

        $qty = 1.0;

        foreach (array_keys($factors) as $i) {
            $n = $values[$i] ?? null;
            $qty *= is_numeric($n) ? (float) $n : 1.0;
        }

        return $qty;
    }

    /**
     * The line's description, saying how the quantity was arrived at.
     *
     * "Double room — 12 rooms × 3 nights" survives being read six months later
     * by somebody who was not in the room; "Double room" with a quantity of 36
     * does not.
     *
     * @param  array<int|string,mixed>  $values
     */
    public function describe(array $values): string
    {
        $parts = [];

        foreach ($this->factors() as $i => $label) {
            $n = $values[$i] ?? null;

            if (is_numeric($n) && (float) $n > 0) {
                $parts[] = rtrim(rtrim(number_format((float) $n, 2), '0'), '.').' '.mb_strtolower($label);
            }
        }

        return $parts === [] ? $this->name : $this->name.' — '.implode(' × ', $parts);
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
