<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A rich checklist entry owned by a plan item — title, done-state, owner, due date. */
#[Fillable(['plan_item_id', 'title', 'is_done', 'owner_id', 'due_on', 'position'])]
class PlanSubtask extends Model
{
    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
            'due_on' => 'date',
            'position' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(PlanItem::class, 'plan_item_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function isOverdue(): bool
    {
        return ! $this->is_done && $this->due_on && $this->due_on->isPast();
    }
}
