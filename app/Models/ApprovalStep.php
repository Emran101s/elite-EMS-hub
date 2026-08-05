<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One link in an approval's chain.
 *
 * An EventApproval used to decide itself in one shot; now it decides through
 * its steps, in order, and EventApproval::syncStatusFromSteps() reads them
 * back into a single status — the same "derive, don't duplicate" shape as
 * EventContract's signatories.
 */
#[Fillable(['approval_id', 'position', 'label', 'approver_id', 'status', 'decided_by', 'decided_at', 'notes'])]
class ApprovalStep extends Model
{
    public const STATUSES = ['pending', 'approved', 'rejected', 'needs_revision', 'skipped'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function approval(): BelongsTo
    {
        return $this->belongsTo(EventApproval::class, 'approval_id');
    }

    /** Null means any manager can decide this step — the platform's original behavior. */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    /** Whoever this step is named for, or "any manager" when it isn't assigned to one. */
    public function assigneeLabel(): string
    {
        return $this->label ?: ($this->approver?->name ?? 'Any manager');
    }
}
