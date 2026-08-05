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
#[Fillable(['approval_id', 'position', 'label', 'approver_id', 'min_role', 'status', 'decided_by', 'decided_at', 'notes'])]
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

    /** Whoever this step is named for, or "any manager"/"any {role}" when it isn't. */
    public function assigneeLabel(): string
    {
        if ($this->label) {
            return $this->label;
        }

        if ($this->approver) {
            return $this->approver->name;
        }

        return 'Any '.($this->min_role ?: 'manager');
    }

    /**
     * Whether this specific step is this specific user's to decide — on top
     * of, not instead of, the baseline `decide-approvals` gate every step
     * still requires. A step named for somebody is theirs alone; a step
     * raised above the baseline (conditional routing's admin step) needs
     * that seniority; a step naming neither is any manager, which the gate
     * already covers.
     */
    public function decidableBy(User $user): bool
    {
        if ($this->approver_id) {
            return $this->approver_id === $user->id;
        }

        if ($this->min_role) {
            return $user->isAtLeast($this->min_role);
        }

        return true;
    }
}
