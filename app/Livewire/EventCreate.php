<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventAvatar;
use App\Models\Project;
use App\Models\Venue;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Create Event', 'subtitle' => 'New event with its own visual identity.'])]
class EventCreate extends Component
{
    public string $name = '';

    public string $type = 'conference';

    public string $city = '';

    public string $country = 'Jordan';

    public ?int $venue_id = null;

    public ?int $project_id = null;

    public string $starts_at = '';

    public string $ends_at = '';

    public string $budget = '';

    public ?int $avatar_id = null;

    /** Once the user picks by hand, type changes stop overriding the choice. */
    public bool $avatarChosenManually = false;

    public function mount(): void
    {
        if (request()->filled('avatar')) {
            $this->avatar_id = EventAvatar::active()->whereKey(request()->integer('avatar'))->value('id');
            $this->avatarChosenManually = $this->avatar_id !== null;
        }

        if (! $this->avatar_id) {
            $this->recommendAvatar();
        }
    }

    public function updatedType(): void
    {
        if (! $this->avatarChosenManually) {
            $this->recommendAvatar();
        }
    }

    public function chooseAvatar(int $avatarId): void
    {
        $this->avatar_id = $avatarId;
        $this->avatarChosenManually = true;
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:'.implode(',', Event::TYPES)],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'avatar_id' => ['nullable', 'exists:event_avatars,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'budget' => ['nullable', 'numeric', 'min:0'],
        ]);

        Event::create([
            ...collect($validated)->except('budget')->all(),
            'budget_cents' => (int) round((float) ($validated['budget'] ?: 0) * 100),
            'status' => 'planning',
            'progress' => 0,
        ]);

        session()->flash('status', "Event “{$this->name}” created — its island is now in the Operations Hub.");

        return $this->redirectRoute('home');
    }

    private function recommendAvatar(): void
    {
        $this->avatar_id = EventAvatar::recommendedFor($this->type)->value('id');
    }

    public function render()
    {
        return view('livewire.event-create', [
            'avatars' => EventAvatar::active()->orderBy('sort_order')->get(),
            'recommendedId' => EventAvatar::recommendedFor($this->type)->value('id'),
            'venues' => Venue::orderBy('name')->get(),
            'projects' => Project::orderBy('name')->get(),
            'types' => Event::TYPES,
        ]);
    }
}
