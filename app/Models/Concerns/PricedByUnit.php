<?php

namespace App\Models\Concerns;

/**
 * How a thing is sold, and therefore how it is counted.
 *
 * Accommodation is not sold "each": it is sold per room per night, transport
 * per vehicle per day, exhibition per square metre. The unit names what ONE of
 * a thing is, and UNITS says which numbers it takes to count it — so a document
 * can ask for rooms AND nights and do the multiplication rather than leaving
 * somebody to do it in their head and type 36.
 *
 * Shared by the house price list and each event's own, because a room-night
 * means the same thing in both and two definitions would eventually disagree.
 */
trait PricedByUnit
{
    /** unit => [label, one-of, [factor labels]] */
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

    public function unitLabel(): string
    {
        return self::UNITS[$this->unit][0] ?? 'Each';
    }

    /** What one of this is, for the line's description: "room-night". */
    public function unitNoun(): string
    {
        return self::UNITS[$this->unit][1] ?? 'item';
    }

    /** @return list<string> the boxes a document draws for this unit */
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
     * by somebody who was not in the room; the same line with a quantity of 36
     * and no explanation does not.
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
}
