<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\PricedByUnit;
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
    use Auditable, PricedByUnit;

    public const AUDIT_FIELDS = ['name', 'unit_price_cents', 'unit', 'active'];

    protected $fillable = ['code', 'name', 'category', 'section', 'detail', 'unit',
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

    public function auditLabel(): string
    {
        return $this->name;
    }
}
