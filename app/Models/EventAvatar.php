<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'subtitle', 'category', 'best_for', 'image_path', 'thumbnail_path', 'model_3d_path', 'supports_3d', 'colors', 'recommended_types', 'sort_order', 'is_active'])]
class EventAvatar extends Model
{
    public const CATEGORIES = ['conference', 'gala', 'exhibition', 'workshop', 'vip', 'festival'];

    protected function casts(): array
    {
        return [
            'colors' => 'array',
            'recommended_types' => 'array',
            'supports_3d' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public function events(): HasMany
    {
        return $this->hasMany(Event::class, 'avatar_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Avatars suited to an event type, best match first.
     */
    public function scopeRecommendedFor(Builder $query, string $type): Builder
    {
        return $query->active()
            ->whereJsonContains('recommended_types', $type)
            ->orderBy('sort_order');
    }
}
