<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** One entry in one of the platform's editable lists. */
#[Fillable(['tenant_id',
    'taxonomy', 'parent_id', 'key', 'label', 'color', 'note', 'position', 'is_active', 'is_system'])]
class TaxonomyTerm extends Model
{
    use BelongsToTenant;

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

    /** Only the top level — the ones that can hold children. */
    public function scopeRoots(Builder $query): Builder
    {
        return $query->whereNull('parent_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('position')->orderBy('label');
    }

    /** "Production · Lighting" — what a term is called when the parent matters. */
    public function path(): string
    {
        return $this->parent ? $this->parent->label.' · '.$this->label : $this->label;
    }
}
