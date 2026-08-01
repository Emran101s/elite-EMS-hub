<?php

namespace App\Models\Concerns;

use App\Support\Taxonomy;
use Illuminate\Database\Eloquent\Builder;

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

    /**
     * The sections a price list is divided into, and the seed for the editable
     * list in Settings — see Taxonomy::LISTS['service_section'].
     *
     * The question a section answers is where a service comes from, which is
     * not what a category answers. "Lighting" is a category; whether the hotel
     * hangs it or a production house trucks it in decides who is called, how
     * long the lead time is and what the margin looks like.
     */
    public const SECTIONS = [
        'hotel' => 'Hotel services',
        'production' => 'Outside production',
        'equipment' => 'Equipment & rental',
        'transport' => 'Transport',
        'people' => 'Crew & staffing',
        'fees' => 'Fees & management',
    ];

    public function unitLabel(): string
    {
        return self::UNITS[$this->unit][0] ?? 'Each';
    }

    /** What this section is called today — renaming one is a Settings job. */
    public function sectionLabel(): string
    {
        return $this->section
            ? Taxonomy::label('service_section', $this->section)
            : 'Unsectioned';
    }

    /**
     * The one search a document's price picker runs.
     *
     * Section is stored as a key and read as a label, so somebody typing the
     * words they can see on the tab ("hotel services") has to find the rows
     * filed under `hotel`. Resolving the label here is what makes "retrieve
     * from everywhere" mean everywhere rather than everywhere-but-by-name.
     */
    public function scopeSearch(Builder $q, string $term): Builder
    {
        $term = trim($term);

        if ($term === '') {
            return $q;
        }

        $like = '%'.mb_strtolower($term).'%';

        $sections = collect(Taxonomy::options('service_section'))
            ->filter(fn (string $label) => str_contains(mb_strtolower($label), mb_strtolower($term)))
            ->keys()->all();

        return $q->where(fn (Builder $w) => $w
            ->whereRaw('lower(name) like ?', [$like])
            ->orWhereRaw('lower(coalesce(code, "")) like ?', [$like])
            ->orWhereRaw('lower(coalesce(category, "")) like ?', [$like])
            ->orWhereRaw('lower(coalesce(detail, "")) like ?', [$like])
            ->when($sections !== [], fn (Builder $x) => $x->orWhereIn('section', $sections)));
    }

    public function scopeInSection(Builder $q, ?string $section): Builder
    {
        // 'none' is a section people actually browse: the rows nobody has
        // filed yet, which are invisible if the only filters are real sections.
        return $section === 'none'
            ? $q->whereNull('section')
            : $q->where('section', $section);
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
