<?php

namespace App\Livewire;

use App\Models\EventApproval;
use App\Services\ApprovalDecisions;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Every decision waiting on you, across every event, decided in place.
 *
 * Before this, "3 approvals awaiting your review" on the dashboard led to a
 * filtered list of *events*, and each decision meant opening that event and
 * finding its Approvals tab. The decision itself is ApprovalDecisions — the
 * same one the event tab runs — so the rules cannot drift between the two.
 */
#[Layout('components.layouts.app', ['title' => 'Approvals', 'hideTitleRow' => true])]
class ApprovalsInbox extends Component
{
    /** 'waiting' — decisions that are yours; 'raised' — your requests still out. */
    #[Url(as: 'show')]
    public string $show = 'waiting';

    public function mount(): void
    {
        // A coordinator decides nothing, so their inbox opens on what they
        // are waiting for instead of an empty list they can never fill.
        if (! Gate::allows('decide-approvals')) {
            $this->show = 'raised';
        }
    }

    public function decide(int $approvalId, string $decision): void
    {
        Gate::authorize('decide-approvals');
        abort_unless(in_array($decision, ApprovalDecisions::DECISIONS, true), 422);

        $approval = EventApproval::whereKey($approvalId)->where('status', 'pending')->firstOrFail();
        $result = app(ApprovalDecisions::class)->decide($approval, $decision, auth()->user());

        $this->dispatch('approvals-toast', message: $result['message'], tone: $result['ok'] ? 'ok' : 'warn');
    }

    public function render()
    {
        $canDecide = Gate::allows('decide-approvals');

        $waiting = $canDecide ? app(ApprovalDecisions::class)->waitingOn(auth()->user()) : collect();

        $raised = EventApproval::with(['event', 'steps.approver'])
            ->where('status', 'pending')
            ->where('requested_by', auth()->id())
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->oldest()
            ->get();

        return view('livewire.approvals-inbox', [
            'canDecide' => $canDecide,
            'waiting' => $waiting,
            'raised' => $raised,
            'list' => $this->show === 'raised' || ! $canDecide ? $raised : $waiting,
        ]);
    }
}
