<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * A reusable requirement in the global catalog. The user builds this list, then
 * picks entries when adding requirements to a venue or event (name + default
 * price auto-fill, still editable per use).
 */
#[Fillable(['name', 'unit_price_cents', 'notes'])]
class Requirement extends Model
{
    protected function casts(): array
    {
        return ['unit_price_cents' => 'integer'];
    }
}
