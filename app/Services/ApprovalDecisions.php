<?php

namespace App\Services;

use App\Models\EventApproval;
use App\Models\User;
use App\Notifications\ApprovalDecided;
use App\Notifications\ApprovalRequested;

/**
 * Deciding an approval, in one place.
 *
 * It used to live inside the event's Approvals tab. The cross-event inbox
 * needs the very same decision — the chain, the "not your own request" rule,
 * the step assignee check, who gets told next — and a second copy of that is
 * exactly how two screens start disagreeing about whether you may approve
 * something. Both screens call this; each keeps its own gate and its own
 * idea of where to send you afterwards.
 */
class ApprovalDecisions
{
    public const DECISIONS = ['approved', 'rejected', 'needs_revision'];

    /**
     * Record $by's decision on the approval's current step.
     *
     * Returns ['ok' => bool, 'message' => string]: ok=false when the rules
     * refuse (nothing is written), with the reason to show the person.
     *
     * @return array{ok:bool,message:string}
     */
    public function decide(EventApproval $approval, string $decision, User $by): array
    {
        abort_unless(in_array($decision, self::DECISIONS, true), 422);
        abort_unless($approval->status === 'pending', 404);

        $step = $approval->currentStep();
        abort_if($step === null, 404);

        // Nobody signs off their own request — not even a manager.
        if ($approval->requested_by === $by->id) {
            return ['ok' => false, 'message' => 'You raised this request — a different manager has to decide it.'];
        }

        // A step named for somebody specific, or raised above the baseline
        // (an admin sign-off conditional routing added), is not the queue's
        // to decide — only the person or the seniority it names.
        if (! $step->decidableBy($by)) {
            return ['ok' => false, 'message' => $step->approver_id
                ? "This step is assigned to {$step->approver?->name} — you can't decide it."
                : "This step needs {$step->min_role} rank or above — you can't decide it."];
        }

        $step->update(['status' => $decision, 'decided_by' => $by->id, 'decided_at' => now()]);

        // A rejection or a request for revision ends the chain right here —
        // the steps behind it were never going to be asked for a decision.
        if (in_array($decision, ['rejected', 'needs_revision'], true)) {
            $approval->steps()->where('status', 'pending')->whereKeyNot($step->id)->update(['status' => 'skipped']);
        }

        $approval->syncStatusFromSteps();

        $approval->refresh();

        if ($approval->status === 'pending') {
            // The chain moved on: tell whoever it landed on.
            $this->announce($approval);
        } else {
            // It resolved. Close the loop with whoever raised it — unless they
            // just decided it themselves, which the guard above prevents, but
            // delegation makes that assumption worth not relying on.
            $requester = $approval->requester;

            if ($requester && filled($requester->email) && $requester->id !== $by->id) {
                $requester->notify(new ApprovalDecided($approval, $decision, $by->name));
            }
        }

        $label = $step->assigneeLabel();
        $stepNote = $approval->steps()->count() > 1 ? " — {$label}'s step" : '';

        return ['ok' => true, 'message' => "“{$approval->title}”{$stepNote} ".str($decision)->replace('_', ' ').'.'];
    }

    /**
     * Mail the people the approval's current step is waiting on.
     *
     * Silent when the chain has no live step, and never mails the requester —
     * they cannot decide their own request, so the only thing an email would
     * tell them is something they already know.
     */
    public function announce(EventApproval $approval): void
    {
        $step = $approval->currentStep();

        if ($step === null) {
            return;
        }

        foreach ($step->recipients(excludeUserId: $approval->requested_by) as $approver) {
            $approver->notify(new ApprovalRequested($approval, $step));
        }
    }

    /**
     * Pending approvals whose current step $user may decide right now — the
     * same two rules decide() enforces, so nothing listed refuses them.
     */
    public function waitingOn(User $user)
    {
        return EventApproval::with(['event', 'requester', 'steps.approver'])
            ->where('status', 'pending')
            ->where(fn ($q) => $q->whereNull('requested_by')->orWhere('requested_by', '!=', $user->id))
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->oldest()
            ->get()
            ->filter(fn (EventApproval $a) => $a->currentStep()?->decidableBy($user))
            ->values();
    }
}
