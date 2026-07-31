<?php

namespace App\Models;

use App\Support\Workflow;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A task on an event's board. Deliberately standalone — no sync, no workflow
 * engine, no audit hooks. Six stages a task travels by hand.
 */
#[Fillable(['event_id', 'assignee_id', 'title', 'description', 'status', 'priority', 'area', 'track_id', 'due_on', 'start_on', 'checklist', 'sort'])]
class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    /**
     * The six board lanes, in flow order. status => [label, hex, open?].
     * Colours align with Plan Studio so the two modules read as one system.
     */
    public const STAGES = [
        'todo' => ['To Do', '#94A3B8', true],
        'doing' => ['In Progress', '#D4AF37', true],
        'review' => ['Need Approval', '#F97316', true],
        'approved' => ['Approved', '#10B981', true],
        'done' => ['Done', '#22C55E', false],
        'cancelled' => ['Cancelled', '#64748B', false],
    ];

    public const PRIORITIES = [
        'low' => ['Low', '#94A3B8'],
        'normal' => ['Normal', '#3B82F6'],
        'high' => ['High', '#F59E0B'],
        'urgent' => ['Urgent', '#EF4444'],
    ];

    /** The module (department) a task belongs to — slug => [label, hex]. */
    public const MODULES = [
        'venue' => ['Venue', '#3B82F6'],
        'programme' => ['Programme', '#06B6D4'],
        'speakers' => ['Speakers', '#8B5CF6'],
        'marketing' => ['Marketing', '#EC4899'],
        'registration' => ['Registration', '#0EA5E9'],
        'sponsorship' => ['Sponsorship', '#D4AF37'],
        'exhibition' => ['Exhibition', '#F59E0B'],
        'production' => ['Production', '#10B981'],
        'logistics' => ['Logistics', '#14B8A6'],
        'transport' => ['Transport', '#22C55E'],
        'operations' => ['Operations', '#64748B'],
        'finance' => ['Finance', '#A855F7'],
        'vip' => ['VIP', '#F97316'],
    ];

    /** Legacy alias — the module slugs. */
    public const AREAS = [
        'registration', 'marketing', 'speakers', 'sponsorship', 'exhibition',
        'venue', 'operations', 'production', 'logistics', 'vip',
    ];

    protected function casts(): array
    {
        return [
            'due_on' => 'date',
            'start_on' => 'date',
            'checklist' => 'array',
        ];
    }

    public static function statuses(): array
    {
        return array_keys(self::STAGES);
    }

    public function stageLabel(): string
    {
        return Workflow::label('task_stage', $this->status);
    }

    public function stageHex(): string
    {
        return Workflow::color('task_stage', $this->status);
    }

    /** Live work — not done, not cancelled. */
    /**
     * What this record is still missing to be a real task.
     *
     * A row that says "Untitled task · Unassigned · —" is not a task, it is a
     * placeholder somebody made and walked away from. Rendered like any other
     * it makes the board look broken and the design take the blame, so the
     * board can now say so quietly instead.
     *
     * @return list<string>
     */
    public function incomplete(): array
    {
        if (! $this->isOpen()) {
            return [];   // a finished task is not waiting for its details
        }

        return array_values(array_filter([
            trim((string) $this->title) === '' ? 'a title' : null,
            $this->assignee_id === null ? 'an owner' : null,
            $this->due_on === null ? 'a due date' : null,
        ]));
    }

    public function isOpen(): bool
    {
        return self::STAGES[$this->status][2] ?? true;
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on && $this->due_on->isPast();
    }

    public function priorityLabel(): string
    {
        return Workflow::label('task_priority', $this->priority);
    }

    public function priorityHex(): string
    {
        return Workflow::color('task_priority', $this->priority);
    }

    /** The module a task belongs to (stored in `area`). */
    public function moduleLabel(): ?string
    {
        if (! $this->area) {
            return null;
        }

        return self::MODULES[$this->area][0] ?? str($this->area)->replace('_', ' ')->title()->toString();
    }

    public function moduleHex(): string
    {
        return self::MODULES[$this->area][1] ?? '#64748B';
    }

    public function areaLabel(): ?string
    {
        return $this->moduleLabel();
    }

    /** Carries a light approval mark once past the sign-off gates. */
    public function isSigned(): bool
    {
        return in_array($this->status, ['approved', 'done'], true);
    }

    public function track(): BelongsTo
    {
        return $this->belongsTo(PlanTrack::class, 'track_id');
    }

    /** [done, total] across the checklist. */
    public function checklistProgress(): array
    {
        $items = $this->checklist ?? [];

        return [collect($items)->where('done', true)->count(), count($items)];
    }

    /** 0–100 completion: the checklist if there is one, else stage-based. */
    public function progress(): int
    {
        [$done, $total] = $this->checklistProgress();
        if ($total > 0) {
            return (int) round($done / $total * 100);
        }

        return match ($this->status) {
            'done' => 100, 'approved' => 90, 'review' => 66, 'doing' => 33, default => 0,
        };
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }
}
