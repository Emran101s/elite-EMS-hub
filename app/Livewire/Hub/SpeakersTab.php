<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Livewire\Concerns\RoutesCostsToBudget;
use App\Models\Event;
use App\Models\EventSpeaker;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

class SpeakersTab extends Component
{
    use BulkSelectable, RoutesCostsToBudget;

    public Event $event;

    public function deleteSelected(): void
    {
        Gate::authorize('write');
        $this->event->speakers()->whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:120')]
    public string $name = '';

    #[Validate('nullable|string|max:120')]
    public string $title = '';

    #[Validate('nullable|string|max:120')]
    public string $organization = '';

    #[Validate('nullable|string|max:160')]
    public string $topic = '';

    #[Validate('nullable|email|max:160')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|string|max:1000')]
    public string $bio = '';

    public bool $is_keynote = false;

    #[Validate('required|in:invited,confirmed,declined,cancelled')]
    public string $status = 'invited';

    #[Validate('nullable|numeric|min:0')]
    public string $fee = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'title', 'organization', 'topic', 'email', 'phone', 'bio', 'fee']);
        $this->is_keynote = false;
        $this->status = 'invited';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = $this->event->speakers()->findOrFail($id);
        $this->editingId = $s->id;
        $this->name = $s->name;
        $this->title = $s->title ?? '';
        $this->organization = $s->organization ?? '';
        $this->topic = $s->topic ?? '';
        $this->email = $s->email ?? '';
        $this->phone = $s->phone ?? '';
        $this->bio = $s->bio ?? '';
        $this->is_keynote = (bool) $s->is_keynote;
        $this->status = $s->status;
        $this->fee = $s->fee_cents ? (string) ($s->fee_cents / 100) : '';
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');
        $this->validate();

        $data = [
            'name' => $this->name,
            'title' => $this->title ?: null,
            'organization' => $this->organization ?: null,
            'topic' => $this->topic ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'bio' => $this->bio ?: null,
            'is_keynote' => $this->is_keynote,
            'status' => $this->status,
            'fee_cents' => (int) round((float) ($this->fee ?: 0) * 100),
        ];

        $this->editingId
            ? $this->event->speakers()->findOrFail($this->editingId)->update($data)
            : $this->event->speakers()->create($data);

        $this->showForm = false;
        session()->flash('status', 'Speaker saved.');
    }

    public function setStatus(int $id, string $status): void
    {
        Gate::authorize('write');
        abort_unless(in_array($status, EventSpeaker::STATUSES, true), 422);
        $this->event->speakers()->findOrFail($id)->update(['status' => $status]);
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        $this->event->speakers()->whereKey($id)->delete();
    }

    public function budgetModule(): string
    {
        return 'speakers';
    }

    public function render()
    {
        $speakers = $this->event->speakers()->orderByDesc('is_keynote')->with('sessions')->get();

        return view('livewire.hub.speakers-tab', [
            'speakers' => $speakers,
            'confirmed' => $speakers->where('status', 'confirmed')->count(),
            'keynotes' => $speakers->where('is_keynote', true)->count(),
            'feeTotal' => $speakers->sum('fee_cents'),
        ]);
    }
}
