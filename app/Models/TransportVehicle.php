<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A specific car with a plate, as opposed to a VehicleType ("Regular Van, 7
 * seats") which is the category you plan with.
 *
 * Naming a plate is optional by design. It matters for VIP cars and coaches
 * where a guest is told which vehicle to look for; forcing it onto every shuttle
 * would just create a field nobody maintains.
 */
#[Fillable(['tenant_id',
    'vehicle_type_id', 'plate_no', 'supplier_id', 'model', 'colour', 'year', 'features', 'notes', 'is_active'])]
class TransportVehicle extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['year' => 'integer', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EventTransport::class, 'vehicle_id');
    }

    public function seats(): int
    {
        return (int) ($this->vehicleType?->capacity ?? 0);
    }

    /** "Mercedes V-Class · PLT 4471" — what you'd say to identify it in a car park. */
    public function label(): string
    {
        $bits = array_filter([$this->model ?: $this->vehicleType?->name, $this->plate_no]);

        return $bits ? implode(' · ', $bits) : 'Vehicle #'.$this->id;
    }

    /**
     * Is this car already committed at that moment? The check dispatch needs
     * before it double-books a plate.
     */
    public function isBusyAt(?string $from, ?string $to, ?int $ignoreMovementId = null): bool
    {
        if (! $from) {
            return false;
        }

        $to ??= $from;

        return $this->movements()
            ->when($ignoreMovementId, fn ($q) => $q->whereKeyNot($ignoreMovementId))
            ->whereNotIn('status', ['cancelled'])
            ->whereNotNull('depart_at')
            ->where('depart_at', '<=', $to)
            ->whereRaw('COALESCE(arrive_at, depart_at) >= ?', [$from])
            ->exists();
    }
}
