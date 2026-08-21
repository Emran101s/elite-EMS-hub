<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\VenueFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id',
    'name', 'type', 'address', 'city', 'country', 'capacity',
    'contact_name', 'contact_phone', 'contact_email', 'notes'])]
class Venue extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<VenueFactory> */
    use HasFactory;

    /** Location kinds offered when adding a venue — free text still allowed. */
    public const TYPES = ['Hotel', 'Conference Centre', 'Ballroom', 'Exhibition Hall',
        'Auditorium', 'Outdoor', 'Restaurant', 'Embassy', 'Other'];

    /** Venue Studio's own tabs — key => [label, purpose, icon], same shape as Event::HUB_TABS. */
    public const STUDIO_TABS = [
        'overview' => ['Overview', 'Digital twin & health', 'home'],
        'spaces' => ['Space Explorer', 'Halls & rooms inventory', 'building'],
        'capacity' => ['Capacity Intelligence', 'Utilization & bottlenecks', 'chart'],
        'exhibition' => ['Exhibition History', 'Halls & booths across past events', 'grid'],
    ];

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

    /** This venue's own permanent halls/rooms — reusable across every event booked here. */
    public function spaces(): HasMany
    {
        return $this->hasMany(VenueSpace::class)->orderBy('position');
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
