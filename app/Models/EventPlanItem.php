<?php

namespace App\Models;

use App\Services\PlannerLibrary;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'event_id', 'category_id', 'parent_id', 'workstream', 'phase', 'title', 'description', 'status', 'priority',
    'assignee_id', 'owner_role', 'due_on', 'starts_on', 'deadline_code', 'approval_required', 'approval_status',
    'budget_impact', 'risk_level', 'dependencies', 'template_key', 'notes', 'sort_order',
])]
class EventPlanItem extends Model
{
    public const STATUSES = ['todo', 'in_progress', 'blocked', 'done'];

    /** Simple priority scale for the category planner. */
    public const PRIORITIES = ['low' => 'Low', 'medium' => 'Medium', 'high' => 'High', 'critical' => 'Critical'];

    /** [label, pill classes] per status — for the checklist UI. */
    public const STATUS_META = [
        'todo' => ['To do', 'bg-navy-100 text-navy-600'],
        'in_progress' => ['In progress', 'bg-amber-100 text-amber-700'],
        'blocked' => ['Blocked', 'bg-risk/10 text-red-700'],
        'done' => ['Done', 'bg-emerald-100 text-emerald-700'],
    ];

    /** [label, hex] per status — for the timeline bars. */
    public const STATUS_BAR = [
        'todo' => ['To do', '#94A3B8'],
        'in_progress' => ['In progress', '#D4AF37'],
        'blocked' => ['Blocked', '#EF4444'],
        'done' => ['Done', '#22C55E'],
    ];

    public function statusMeta(): array
    {
        return self::STATUS_META[$this->status] ?? self::STATUS_META['todo'];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(EventPlanCategory::class, 'category_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'starts_on' => 'date',
            'sort_order' => 'integer',
            'approval_required' => 'boolean',
            'budget_impact' => 'boolean',
            'dependencies' => 'array',
        ];
    }

    public function workstreamLabel(): string
    {
        return PlannerLibrary::WORKSTREAMS[$this->workstream][0] ?? str($this->workstream)->headline();
    }

    public function phaseLabel(): string
    {
        return PlannerLibrary::PHASES[$this->phase] ?? str($this->phase)->headline();
    }

    public function priorityLabel(): string
    {
        return PlannerLibrary::PRIORITIES[$this->priority][0] ?? strtoupper($this->priority);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_on && $this->due_on->isPast();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /** A task can be owned by several people. `assignee_id` mirrors the first owner. */
    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_plan_item_user', 'plan_item_id', 'user_id')
            ->orderBy('name');
    }
}
