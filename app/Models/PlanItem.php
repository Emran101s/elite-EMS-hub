<?php

namespace App\Models;

use App\Support\Workflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A Plan Studio deliverable. Travels through the six status gates, owns rich
 * subtasks, carries clear start/due dates, and records its approval sign-off.
 */
#[Fillable([
    'event_id', 'track_id', 'title', 'description', 'status', 'priority',
    'start_on', 'due_on', 'progress_override', 'tags', 'approved_by', 'approved_at', 'position',
])]
class PlanItem extends Model
{
    /** The six gates: key => [label, hex]. Order is the lifecycle order. */
    public const STATUSES = [
        'todo' => ['To Do', '#94A3B8'],
        'in_progress' => ['In Progress', '#D4AF37'],
        'needs_approval' => ['Need Approval', '#F97316'],
        'approved' => ['Approved', '#10B981'],
        'done' => ['Done', '#22C55E'],
        'cancelled' => ['Cancelled', '#64748B'],
    ];

    public const PRIORITIES = [
        'low' => ['Low', '#94A3B8'],
        'medium' => ['Medium', '#3B82F6'],
        'high' => ['High', '#F59E0B'],
        'critical' => ['Critical', '#EF4444'],
    ];

    /** Terminal gates — a due date can't make these "overdue". */
    public const CLOSED = ['done', 'cancelled'];

    /** Gates that carry an approval seal. */
    public const SIGNED = ['approved', 'done'];

    protected function casts(): array
    {
        return [
            'start_on' => 'date',
            'due_on' => 'date',
            'approved_at' => 'datetime',
            'progress_override' => 'integer',
            'position' => 'integer',
            'tags' => 'array',
        ];
    }

    public static function statuses(): array
    {
        return array_keys(self::STATUSES);
    }

    public function statusLabel(): string
    {
        return Workflow::label('plan_status', $this->status ?: 'todo');
    }

    public function statusHex(): string
    {
        return Workflow::color('plan_status', $this->status ?: 'todo');
    }

    public function priorityLabel(): string
    {
        return (self::PRIORITIES[$this->priority] ?? self::PRIORITIES['medium'])[0];
    }

    public function priorityHex(): string
    {
        return (self::PRIORITIES[$this->priority] ?? self::PRIORITIES['medium'])[1];
    }

    public function isOpen(): bool
    {
        return ! in_array($this->status, self::CLOSED, true);
    }

    public function isSigned(): bool
    {
        return in_array($this->status, self::SIGNED, true) && $this->approved_at !== null;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on && $this->due_on->isPast();
    }

    /** [done, total] subtask counts. */
    public function subtaskProgress(): array
    {
        $total = $this->subtasks->count();

        return [$this->subtasks->where('is_done', true)->count(), $total];
    }

    /** Percent complete: manual override, else rolled up from subtasks, else gate-implied. */
    public function progress(): int
    {
        if ($this->progress_override !== null) {
            return max(0, min(100, $this->progress_override));
        }

        [$done, $total] = $this->subtaskProgress();
        if ($total > 0) {
            return (int) round($done / $total * 100);
        }

        return in_array($this->status, self::SIGNED, true) ? 100 : 0;
    }

    /** Days until due (negative = overdue). Null when there's no due date. */
    public function daysToDue(): ?int
    {
        return $this->due_on ? (int) round(now()->startOfDay()->diffInDays($this->due_on->copy()->startOfDay(), false)) : null;
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(PlanTrack::class, 'track_id');
    }

    public function subtasks(): HasMany
    {
        return $this->hasMany(PlanSubtask::class)->orderBy('position')->orderBy('id');
    }

    public function owners(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'plan_item_user')->orderBy('name');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
