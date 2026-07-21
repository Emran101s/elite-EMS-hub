<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'status', 'description', 'starts_on', 'ends_on', 'budget_cents'])]
class Project extends Model
{
    /** @use HasFactory<\Database\Factories\ProjectFactory> */
    use HasFactory;

    public const STATUSES = ['active', 'on_hold', 'completed'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'budget_cents' => 'integer',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

}
