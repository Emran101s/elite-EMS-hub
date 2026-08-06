<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['tenant_id',
    'event_id', 'title', 'type', 'status', 'requested_by', 'decided_by', 'decided_at', 'notes', 'source_type', 'source_id', 'amount_cents'])]
class EventApproval extends Model
{
    use Auditable;
    use BelongsToTenant;

    /** Only these changes are audit-worthy — decisions, not noise. */
    public const AUDIT_FIELDS = ['status', 'decided_by', 'title'];

    public const TYPES = ['budget', 'supplier', 'design', 'venue', 'agenda', 'client', 'payment', 'report'];

    public const STATUSES = ['pending', 'approved', 'rejected', 'needs_revision'];

    /** The only types an amount routes on — the rest never carry a figure worth gating. */
    public const AMOUNT_GATED_TYPES = ['budget', 'payment'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime', 'amount_cents' => 'integer'];
    }

    /**
     * Conditional routing: a budget or payment request over the house
     * threshold needs an admin's sign-off in addition to a manager's — the
     * chain gets a second step nobody had to configure by hand.
     */
    public function needsAdminStep(): bool
    {
        $threshold = CompanyProfile::approvalThresholdCents();

        return $threshold !== null
            && in_array($this->type, self::AMOUNT_GATED_TYPES, true)
            && $this->amount_cents !== null
            && $this->amount_cents > $threshold;
    }

    /**
     * A bare `EventApproval::create()` — direct, from a test, from a future
     * caller that has never heard of steps — still needs one step to be
     * decidable at all. ApprovalsTab::save() overwrites this default step
     * with whatever the requester actually configured; anything that skips
     * the form gets the platform's original single-any-manager behavior.
     */
    protected static function booted(): void
    {
        static::created(function (self $approval) {
            if ($approval->steps()->doesntExist()) {
                $approval->steps()->create(['position' => 1]);
            }
        });
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function steps(): HasMany
    {
        return $this->hasMany(ApprovalStep::class, 'approval_id')->orderBy('position');
    }

    /** The step waiting on a decision right now — the chain acts on this one and no other. */
    public function currentStep(): ?ApprovalStep
    {
        return $this->steps->firstWhere('status', 'pending');
    }

    /**
     * Fold the steps back into one status — the same "derive, don't
     * duplicate" shape as EventContract::syncStatusFromSignatures().
     *
     * Any rejection or revision request stops the chain right there: the
     * remaining steps are marked skipped rather than left pending, so the
     * queue never shows a decision still "waiting" on a request that is
     * already dead. Approval requires every step to have said yes.
     */
    public function syncStatusFromSteps(): void
    {
        $steps = $this->steps()->get();

        if ($steps->isEmpty()) {
            return;
        }

        $stopped = $steps->first(fn (ApprovalStep $s) => in_array($s->status, ['rejected', 'needs_revision'], true));

        if ($stopped) {
            $this->status = $stopped->status;
            $this->decided_by = $stopped->decided_by;
            $this->decided_at = $stopped->decided_at;
        } elseif ($steps->every(fn (ApprovalStep $s) => $s->status === 'approved')) {
            $last = $steps->last();
            $this->status = 'approved';
            $this->decided_by = $last->decided_by;
            $this->decided_at = $last->decided_at;
        } else {
            $this->status = 'pending';
        }

        $this->save();
    }
}
