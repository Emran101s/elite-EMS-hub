<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'type', 'status', 'city', 'country', 'venue_id', 'project_id', 'avatar_id', 'starts_at', 'ends_at', 'budget_cents', 'progress'])]
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    public const TYPES = ['conference', 'gala', 'workshop', 'exhibition', 'career_fair', 'dinner'];

    public const STATUSES = ['planning', 'on_track', 'in_progress', 'at_risk', 'behind', 'completed'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'budget_cents' => 'integer',
            'progress' => 'integer',
        ];
    }

    /**
     * Collapse the six statuses into the three health colors
     * used across the Command Center (track / warn / risk).
     */
    public function healthGroup(): string
    {
        return match ($this->status) {
            'at_risk', 'behind' => 'risk',
            'in_progress', 'planning' => 'warn',
            default => 'track',
        };
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function avatar(): BelongsTo
    {
        return $this->belongsTo(EventAvatar::class, 'avatar_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class);
    }
}
