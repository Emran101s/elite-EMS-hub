<?php

namespace App\Models;

use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'address', 'city', 'country', 'capacity',
    'contact_name', 'contact_phone', 'contact_email', 'notes'])]
class Venue extends Model
{
    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    /** Location kinds offered when adding a venue — free text still allowed. */
    public const TYPES = ['Hotel', 'Conference Centre', 'Ballroom', 'Exhibition Hall',
        'Auditorium', 'Outdoor', 'Restaurant', 'Embassy', 'Other'];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
        ];
    }

    /** "Amman, Jordan" — the one-line place, skipping blanks. */
    public function locationLine(): string
    {
        return collect([$this->city, $this->country])->filter()->implode(', ');
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /**
     * Venues that are places people sleep.
     *
     * The type is free text and editable in Settings, so this matches on the
     * word rather than an id — someone may well rename it to "Hotel / Resort".
     */
    public function scopeHotels(Builder $query): Builder
    {
        return $query->where('type', 'like', '%Hotel%');
    }

    public function isHotel(): bool
    {
        return str_contains(mb_strtolower((string) $this->type), 'hotel');
    }
}
