<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventRisk;
use App\Models\User;
use App\Support\Taxonomy;
use Livewire\Component;

class RisksTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public string $title = '';

    public string $category = 'venue';

    public int $probability = 3;

    public int $impact = 3;

    public string $mitigation = '';

    public ?int $owner_id = null;

    public string $due_on = '';

    public function mount(): void
    {
        $this->showForm = request('action') === 'add';
    }

    public function save()
    {
        $this->validate([
            'title' => ['required', 'string', 'max:160'],
            'category' => ['required', 'in:'.implode(',', array_keys(Taxonomy::options('risk_category')))],
            'probability' => ['required', 'integer', 'between:1,5'],
            'impact' => ['required', 'integer', 'between:1,5'],
            'mitigation' => ['nullable', 'string', 'max:500'],
            'owner_id' => ['nullable', 'exists:users,id'],
            'due_on' => ['nullable', 'date'],
        ]);

        $this->event->risks()->create([
            'title' => $this->title,
            'category' => $this->category,
            'probability' => $this->probability,
            'impact' => $this->impact,
            'mitigation' => $this->mitigation ?: null,
            'owner_id' => $this->owner_id,
            'status' => 'open',
            'due_on' => $this->due_on ?: null,
        ]);

        session()->flash('status', "Risk “{$this->title}” registered.");

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'risks']);
    }

    public function setStatus(int $riskId, string $status)
    {
        abort_unless(in_array($status, EventRisk::STATUSES, true), 422);

        $this->event->risks()->whereKey($riskId)->firstOrFail()->update(['status' => $status]);

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'risks']);
    }

    public function render()
    {
        return view('livewire.hub.risks-tab', [
            'risks' => $this->event->risks()->with('owner')->get()->sortByDesc->severity()->values(),
            'users' => User::orderBy('name')->get(),
        ]);
    }
}
