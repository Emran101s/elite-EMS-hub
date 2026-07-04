<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name', 'description', 'type', 'status', 'stage', 'city', 'country',
    'venue_id', 'project_id', 'client_id', 'project_manager_id', 'avatar_id',
    'starts_at', 'ends_at', 'budget_cents', 'progress', 'expected_participants',
    'primary_color', 'secondary_color', 'accent_color', 'text_color',
])]
class Event extends Model
{
    /** @use HasFactory<\Database\Factories\EventFactory> */
    use HasFactory;

    public const TYPES = [
        'conference', 'summit', 'workshop', 'gala_dinner', 'exhibition', 'career_fair',
        'vip_reception', 'embassy_event', 'training_program', 'product_launch',
        'awards_ceremony', 'outdoor_event', 'public_event', 'private_dinner',
        'hybrid_event', 'online_event',
    ];

    /** Health statuses (color-mapped; `stage` below tracks the lifecycle). */
    public const STATUSES = ['planning', 'on_track', 'in_progress', 'at_risk', 'behind', 'completed'];

    /** Lifecycle stages. */
    public const STAGES = ['draft', 'proposal', 'confirmed', 'planning', 'production', 'live', 'completed', 'closed', 'cancelled', 'on_hold'];

    public const TEAM_ROLES = ['project_manager', 'operations_lead', 'registration_lead', 'supplier_coordinator', 'finance_owner', 'design_owner', 'production_owner', 'client_rm'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'budget_cents' => 'integer',
            'progress' => 'integer',
            'expected_participants' => 'integer',
        ];
    }

    /**
     * Collapse health statuses into the three health colors (track / warn / risk).
     */
    public function healthGroup(): string
    {
        return match ($this->status) {
            'at_risk', 'behind' => 'risk',
            'in_progress', 'planning' => 'warn',
            default => 'track',
        };
    }

    /**
     * The event color theme. Explicit colors win; otherwise inherit the
     * avatar palette; otherwise brand defaults.
     */
    public function theme(): array
    {
        $palette = $this->avatar?->colors ?? [];

        return [
            'primary' => $this->primary_color ?? $palette[1] ?? '#0B1F3A',
            'secondary' => $this->secondary_color ?? $palette[0] ?? '#F8FAFC',
            'accent' => $this->accent_color ?? $palette[2] ?? '#D4AF37',
            'text' => $this->text_color ?? '#0F172A',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
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
        return $this->belongsToMany(Supplier::class)->withPivot(['status', 'notes']);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(EventRoom::class);
    }

    public function agendaDays(): HasMany
    {
        return $this->hasMany(EventAgendaDay::class)->orderBy('sort');
    }

    public function agendaSessions(): HasMany
    {
        return $this->hasMany(EventAgendaSession::class);
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(EventBudgetItem::class);
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(EventSponsor::class);
    }

    public function risks(): HasMany
    {
        return $this->hasMany(EventRisk::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(EventApproval::class);
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_team_members')->withPivot('role');
    }
}
