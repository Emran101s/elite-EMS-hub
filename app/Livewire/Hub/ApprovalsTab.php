<?php

namespace App\Livewire\Hub;

use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\User;
use App\Services\ApprovalDecisions;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class ApprovalsTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public string $title = '';

    public string $type = 'budget';

    public string $notes = '';

    /** What the request is worth — only budget/payment types route on it. */
    public string $amount = '';

    /**
     * The chain being built: each row is ['label' => ?string, 'approver_id' => ?int].
     * One step, unassigned, reproduces the platform's original single-manager
     * behavior — the form only grows past that when somebody adds a step.
     */
    public array $steps = [['label' => '', 'approver_id' => '']];

    /** Pending approval whose hand-off picker is open in the queue. */
    public ?int $delegatingId = null;

    /** User id chosen in that picker. */
    public string $delegateTo = '';

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    public function startDelegate(int $approvalId): void
    {
        $this->delegatingId = $approvalId;
        $this->delegateTo = '';
        $this->resetErrorBag('delegateTo');
    }

    public function cancelDelegate(): void
    {
        $this->delegatingId = null;
        $this->delegateTo = '';
        $this->resetErrorBag('delegateTo');
    }

    public function addStep(): void
    {
        $this->steps[] = ['label' => '', 'approver_id' => ''];
    }

    public function removeStep(int $i): void
    {
        // A chain of zero steps decides nothing — always leave one behind.
        if (count($this->steps) > 1) {
            unset($this->steps[$i]);
            $this->steps = array_values($this->steps);
        }
    }

    public function save()
    {
        Gate::authorize('write');
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:'.implode(',', array_keys(Taxonomy::options('approval_type')))],
            'notes' => ['nullable', 'string', 'max:500'],
            'amount' => ['nullable', 'numeric', 'min:0'],
            'steps' => ['required', 'array', 'min:1'],
            'steps.*.label' => ['nullable', 'string', 'max:80'],
            'steps.*.approver_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);

        // A chain that names the requester as a step can never finish — they
        // cannot decide their own request, and nobody else can decide a step
        // assigned to them. Catch it at submit, not when the queue is stuck.
        foreach (array_values($this->steps) as $i => $step) {
            $assigneeId = $step['approver_id'] !== '' && $step['approver_id'] !== null
                ? (int) $step['approver_id']
                : null;

            if ($assigneeId === null) {
                continue;
            }

            if ($assigneeId === auth()->id()) {
                $this->addError("steps.{$i}.approver_id", 'You cannot assign a step to yourself.');

                continue;
            }

            $assignee = User::find($assigneeId);

            if (! $assignee?->isAtLeast('manager')) {
                $this->addError("steps.{$i}.approver_id", 'Only a manager can be named on a step.');
            }
        }

        if ($this->getErrorBag()->isNotEmpty()) {
            return null;
        }

        $approval = $this->event->approvals()->create([
            'title' => $this->title,
            'type' => $this->type,
            'notes' => $this->notes ?: null,
            'amount_cents' => $this->amount !== '' ? (int) round((float) $this->amount * 100) : null,
            'status' => 'pending',
            'requested_by' => auth()->id(),
        ]);

        // Position 1 already exists — EventApproval::booted() creates it the
        // moment the approval itself does — so this overwrites it with what
        // was actually configured rather than colliding with it.
        $configured = array_values($this->steps);

        foreach ($configured as $i => $step) {
            $approval->steps()->updateOrCreate(['position' => $i + 1], [
                'label' => trim((string) ($step['label'] ?? '')) ?: null,
                'approver_id' => $step['approver_id'] !== '' && $step['approver_id'] !== null
                    ? (int) $step['approver_id']
                    : null,
            ]);
        }

        $approval->steps()->where('position', '>', count($configured))->delete();

        // Conditional routing: over the house threshold, a budget or payment
        // request gets one more step nobody configured by hand — gated to
        // admin rank, appended after whatever the requester built.
        $escalated = false;
        if ($approval->fresh()->needsAdminStep()) {
            $approval->steps()->create([
                'position' => count($configured) + 1,
                'label' => 'Admin sign-off (over threshold)',
                'min_role' => 'admin',
            ]);
            $escalated = true;
        }

        // Tell whoever the first step is waiting on. Only the first — the rest
        // of the chain is told as it reaches them, in decide().
        app(ApprovalDecisions::class)->announce($approval->fresh());

        $stepCount = count($configured) + ($escalated ? 1 : 0);
        $chained = $stepCount > 1 ? ' ('.$stepCount.'-step chain'.($escalated ? ', admin sign-off required' : '').')' : '';
        session()->flash('status', "Approval requested: “{$this->title}”{$chained}.");

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
    }

    public function decide(int $approvalId, string $decision)
    {
        Gate::authorize('decide-approvals');
        abort_unless(in_array($decision, ApprovalDecisions::DECISIONS, true), 422);

        $approval = $this->event->approvals()->whereKey($approvalId)->where('status', 'pending')->firstOrFail();
        $result = app(ApprovalDecisions::class)->decide($approval, $decision, auth()->user());

        session()->flash($result['ok'] ? 'status' : 'error', $result['message']);

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
    }

    /**
     * Hand the current pending step to another eligible manager.
     * Does not decide the step — only renames who owns it.
     */
    public function delegate(int $approvalId)
    {
        Gate::authorize('decide-approvals');

        $approval = $this->event->approvals()->whereKey($approvalId)->where('status', 'pending')->firstOrFail();
        $step = $approval->currentStep();
        abort_if($step === null, 404);

        if (! $step->delegatableBy(auth()->user(), $approval)) {
            session()->flash('error', 'You can’t hand this step off — it isn’t yours to reassign.');

            return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
        }

        $this->validate([
            'delegateTo' => ['required', 'integer', 'exists:users,id'],
        ]);

        $toId = (int) $this->delegateTo;

        if ($toId === auth()->id()) {
            $this->addError('delegateTo', 'Hand the step to someone else — not yourself.');

            return null;
        }

        if ($step->approver_id === $toId) {
            $this->addError('delegateTo', 'That person already holds this step.');

            return null;
        }

        $to = User::findOrFail($toId);

        if (! $step->canReceiveDelegation($to, $approval)) {
            $reason = $to->id === $approval->requested_by
                ? 'The requester can’t be given their own step to decide.'
                : ($step->min_role && ! $to->isAtLeast($step->min_role)
                    ? "This step needs {$step->min_role} rank or above."
                    : 'Only a manager can receive a hand-off.');
            $this->addError('delegateTo', $reason);

            return null;
        }

        $fromLabel = $step->assigneeLabel();
        $note = trim(($step->notes ? $step->notes."\n" : '').'Handed off from '.$fromLabel.' to '.$to->name.' by '.auth()->user()->name.' on '.now()->toDateString().'.');

        $step->update([
            'approver_id' => $to->id,
            'notes' => $note,
        ]);

        $this->cancelDelegate();
        session()->flash('status', "“{$approval->title}” handed to {$to->name}.");

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
    }

    public function render()
    {
        return view('livewire.hub.approvals-tab', [
            'pending' => $this->event->approvals()->with(['requester', 'steps.approver'])->where('status', 'pending')->latest()->get(),
            'decided' => $this->event->approvals()->with(['requester', 'decider', 'steps.approver', 'steps.decider'])->whereNot('status', 'pending')->latest('decided_at')->get(),
            'managers' => $this->managers(),
            'threshold' => CompanyProfile::approvalThresholdCents(),
        ]);
    }

    /**
     * Everyone eligible to be named on a step — manager rank or above.
     *
     * Same rule as User::isAtLeast('manager'), but isAtLeast() resolves
     * activeWorkspace() per instance with no cross-row memoization, so
     * filtering the whole roster through it fired one workspaces query per
     * user. This reads the one rule it's built on — the pivot role on the
     * tenant's one workspace, falling back to users.role — off a single
     * eager load instead.
     */
    private function managers()
    {
        return User::with('workspaces')->get()->filter(function (User $u) {
            $effective = $u->workspaces->first()?->pivot?->role ?? $u->role;

            return (User::ROLE_RANK[$effective] ?? 0) >= User::ROLE_RANK['manager'];
        })->values();
    }
}
