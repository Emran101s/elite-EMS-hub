<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'capacity', 'is_active', 'position'])]
class VehicleType extends Model
{
    /**
     * Shipped presets. Only the ones marked active appear when you add a movement;
     * the rest wait in Settings → Transport until you need them.
     *
     * @var array<int,array{0:string,1:int,2:bool}>
     */
    public const PRESETS = [
        ['Regular Sedan', 2, true],
        ['Regular Van', 7, true],
        ['Minibus', 17, false],
        ['Midi Bus', 30, false],
        ['Coach Bus', 49, false],
        ['VIP Sedan', 2, false],
        ['Accessible Van', 4, false],
    ];

    protected function casts(): array
    {
        return ['capacity' => 'integer', 'is_active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    /** Seeds the presets once. Safe to call repeatedly. */
    public static function ensureSeeded(): void
    {
        if (static::exists()) {
            return;
        }

        foreach (self::PRESETS as $i => [$name, $capacity, $active]) {
            static::create(['name' => $name, 'capacity' => $capacity, 'is_active' => $active, 'position' => $i]);
        }
    }

    /** "Regular Van · max 7" */
    public function label(): string
    {
        return $this->name.' · max '.$this->capacity;
    }
}
