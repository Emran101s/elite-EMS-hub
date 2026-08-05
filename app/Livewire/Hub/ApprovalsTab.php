<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\User;
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

    /**
     * The chain being built: each row is ['label' => ?string, 'approver_id' => ?int].
     * One step, unassigned, reproduces the platform's original single-manager
     * behavior — the form only grows past that when somebody adds a step.
     */
    public array $steps = [['label' => '', 'approver_id' => '']];

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
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

        $chained = count($configured) > 1 ? ' ('.count($configured).'-step chain)' : '';
        session()->flash('status', "Approval requested: “{$this->title}”{$chained}.");

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
    }

    public function decide(int $approvalId, string $decision)
    {
        Gate::authorize('decide-approvals');
        abort_unless(in_array($decision, ['approved', 'rejected', 'needs_revision'], true), 422);

        $approval = $this->event->approvals()->whereKey($approvalId)->where('status', 'pending')->firstOrFail();
        $step = $approval->currentStep();
        abort_if($step === null, 404);

        // Nobody signs off their own request — not even a manager.
        if ($approval->requested_by === auth()->id()) {
            session()->flash('error', 'You raised this request — a different manager has to decide it.');

            return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
        }

        // A step named for somebody specific is theirs to decide, not the queue's.
        if ($step->approver_id && $step->approver_id !== auth()->id()) {
            session()->flash('error', "This step is assigned to {$step->approver?->name} — you can't decide it.");

            return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
        }

        $step->update(['status' => $decision, 'decided_by' => auth()->id(), 'decided_at' => now()]);

        // A rejection or a request for revision ends the chain right here —
        // the steps behind it were never going to be asked for a decision.
        if (in_array($decision, ['rejected', 'needs_revision'], true)) {
            $approval->steps()->where('status', 'pending')->whereKeyNot($step->id)->update(['status' => 'skipped']);
        }

        $approval->syncStatusFromSteps();

        $label = $step->assigneeLabel();
        $stepNote = $approval->steps()->count() > 1 ? " — {$label}'s step" : '';
        session()->flash('status', "“{$approval->title}”{$stepNote} ".str($decision)->replace('_', ' ').'.');

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
    }

    public function render()
    {
        return view('livewire.hub.approvals-tab', [
            'pending' => $this->event->approvals()->with(['requester', 'steps.approver'])->where('status', 'pending')->latest()->get(),
            'decided' => $this->event->approvals()->with(['requester', 'decider', 'steps.approver', 'steps.decider'])->whereNot('status', 'pending')->latest('decided_at')->get(),
            'managers' => User::query()->get()->filter(fn (User $u) => $u->isAtLeast('manager'))->values(),
        ]);
    }
}
