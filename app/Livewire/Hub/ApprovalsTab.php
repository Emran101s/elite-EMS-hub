<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventApproval;
use Livewire\Component;

class ApprovalsTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public string $title = '';

    public string $type = 'budget';

    public string $notes = '';

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    public function save()
    {
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'type' => ['required', 'in:'.implode(',', EventApproval::TYPES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $this->event->approvals()->create([
            'title' => $this->title,
            'type' => $this->type,
            'notes' => $this->notes ?: null,
            'status' => 'pending',
            'requested_by' => auth()->id(),
        ]);

        session()->flash('status', "Approval requested: “{$this->title}”.");

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
    }

    public function decide(int $approvalId, string $decision)
    {
        abort_unless(in_array($decision, ['approved', 'rejected', 'needs_revision'], true), 422);

        $approval = $this->event->approvals()->whereKey($approvalId)->where('status', 'pending')->firstOrFail();

        $approval->update([
            'status' => $decision,
            'decided_by' => auth()->id(),
            'decided_at' => now(),
        ]);

        session()->flash('status', "“{$approval->title}” ".str($decision)->replace('_', ' ').'.');

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'approvals']);
    }

    public function render()
    {
        return view('livewire.hub.approvals-tab', [
            'pending' => $this->event->approvals()->with('requester')->where('status', 'pending')->latest()->get(),
            'decided' => $this->event->approvals()->with(['requester', 'decider'])->whereNot('status', 'pending')->latest('decided_at')->get(),
        ]);
    }
}
