<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One entry in one of the platform's editable lists. */
#[Fillable(['taxonomy', 'key', 'label', 'color', 'note', 'position', 'is_active', 'is_system'])]
class TaxonomyTerm extends Model
{
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_system' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function scopeIn(Builder $query, string $taxonomy): Builder
    {
        return $query->where('taxonomy', $taxonomy)->orderBy('position')->orderBy('label');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
