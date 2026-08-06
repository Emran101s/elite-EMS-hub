<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
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
#[Fillable(['tenant_id',
    'approval_id', 'position', 'label', 'approver_id', 'min_role', 'status', 'decided_by', 'decided_at', 'notes'])]
class ApprovalStep extends Model
{
    use BelongsToTenant;

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

    /**
     * Who may hand this pending step to somebody else.
     *
     * The named assignee (or anyone who can currently decide an open /
     * role-gated step) can hand it off — except the requester, who must
     * never steer their own chain. Admins can always reassign — the escape
     * hatch when the named person is away and the queue is stuck. Baseline
     * `decide-approvals` is still required at the call site.
     */
    public function delegatableBy(User $user, EventApproval $approval): bool
    {
        if (! $this->isPending()) {
            return false;
        }

        if ($user->isAtLeast('admin')) {
            return true;
        }

        if ($approval->requested_by === $user->id) {
            return false;
        }

        return $this->decidableBy($user);
    }

    /**
     * Whether this user is an eligible hand-off target for this step —
     * manager floor, seniority for role-gated steps, never the requester
     * (same stuck-chain rule as assign-at-create).
     */
    public function canReceiveDelegation(User $candidate, EventApproval $approval): bool
    {
        if ($candidate->id === $approval->requested_by) {
            return false;
        }

        if (! $candidate->isAtLeast('manager')) {
            return false;
        }

        if ($this->min_role && ! $candidate->isAtLeast($this->min_role)) {
            return false;
        }

        return true;
    }
}
