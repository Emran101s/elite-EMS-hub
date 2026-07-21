<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['event_id', 'type', 'vehicle_type_id', 'service_type_id', 'vehicles', 'route', 'pickup_from', 'drop_to', 'provider', 'driver_contact', 'depart_at', 'flight_no', 'arrive_at', 'capacity', 'passengers', 'cost_cents', 'status', 'notes'])]
class EventTransport extends Model
{
    protected $table = 'event_transport';

    public const TYPES = ['shuttle', 'coach', 'sedan', 'van', 'vip', 'flight'];

    public const STATUSES = ['planned', 'booked', 'confirmed', 'completed'];

    protected function casts(): array
    {
        return [
            'depart_at' => 'datetime',
            'arrive_at' => 'datetime',
            'capacity' => 'integer',
            'passengers' => 'integer',
            'cost_cents' => 'integer',
        ];
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(TransportServiceType::class);
    }

    /** Seats available across the vehicles booked for this movement. */
    public function seats(): int
    {
        $per = $this->vehicleType?->capacity ?? $this->capacity ?? 0;

        return $per * max(1, (int) $this->vehicles);
    }

    /**
     * Sort key for schedules: date then time, ascending. Movements with no time
     * set sort last rather than first — an unscheduled run is not the earliest
     * one, and SQL's NULLs-first ordering would otherwise put it at the top.
     */
    public function chronoKey(): string
    {
        return $this->depart_at?->format('Y-m-d H:i') ?? '9999-12-31 23:59';
    }

    /** The manifest — who is actually riding. */
    public function manifest(): HasMany
    {
        return $this->hasMany(EventTransportPassenger::class, 'transport_id')
            ->orderBy('position')->orderBy('id');
    }

    /**
     * Headcount. Named passengers win once anyone is on the manifest; the plain
     * `passengers` number is the estimate you start with before names exist.
     */
    public function paxCount(): int
    {
        $named = $this->relationLoaded('manifest') ? $this->manifest->count() : $this->manifest()->count();

        return $named ?: (int) $this->passengers;
    }

    public function seatsFree(): int
    {
        return max(0, $this->seats() - $this->paxCount());
    }

    /** Passengers beyond what the booked vehicles can carry. */
    public function isOverbooked(): bool
    {
        return $this->seats() > 0 && $this->paxCount() > $this->seats();
    }

    /** How many vehicles of this movement's type are committed. */
    public function vehicleCount(): int
    {
        return max(1, (int) $this->vehicles);
    }

    /** "Airport → Hotel · Regular Van ×2" */
    public function movementLabel(): string
    {
        $bits = array_filter([
            $this->serviceType?->name,
            $this->vehicleType ? $this->vehicleType->name.($this->vehicles > 1 ? ' ×'.$this->vehicles : '') : null,
        ]);

        return $bits ? implode(' · ', $bits) : ($this->route ?: 'Movement');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
