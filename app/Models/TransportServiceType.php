<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['tenant_id',
    'name', 'is_active', 'position'])]
class TransportServiceType extends Model
{
    use BelongsToTenant;

    /**
     * What the movement *is*, as opposed to what it's driven in.
     *
     * @var array<int,array{0:string,1:bool}>
     */
    public const PRESETS = [
        ['Pickup & Drop-off', true],
        ['Airport → Hotel', false],
        ['Hotel → Airport', false],
        ['Hotel → Venue', false],
        ['Venue → Hotel', false],
        ['Full Day (at disposal)', false],
        ['Half Day (at disposal)', false],
        ['Intercity Transfer', false],
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'position' => 'integer'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public static function ensureSeeded(): void
    {
        if (static::exists()) {
            return;
        }

        foreach (self::PRESETS as $i => [$name, $active]) {
            static::create(['name' => $name, 'is_active' => $active, 'position' => $i]);
        }
    }
}
