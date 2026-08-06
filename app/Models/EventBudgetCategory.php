<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A budget category is a per-event grouping the user controls — the 8 defaults
 * are seeded on first use, and the user can add / rename / remove their own.
 * Budget lines link to it by name (EventBudgetItem::category).
 */
#[Fillable(['tenant_id',
    'event_id', 'name', 'position'])]
class EventBudgetCategory extends Model
{
    use BelongsToTenant;

    protected function casts(): array
    {
        return ['position' => 'integer'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
