<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Event;
use App\Models\EventAvatar;
use App\Models\Project;
use App\Models\User;
use App\Models\Venue;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Create Event', 'subtitle' => 'Identity first — operate everything else inside the Event Hub.'])]
class EventCreate extends Component
{
    /** Preset color themes: [label, primary, secondary, accent, text]. */
    public const PALETTES = [
        'navy-gold' => ['Navy + Gold', '#0B1F3A', '#F8FAFC', '#D4AF37', '#0F172A'],
        'white-gold' => ['White + Gold', '#FFFFFF', '#F8FAFC', '#D4AF37', '#0B1F3A'],
        'black-gold' => ['Black + Gold', '#10141A', '#F8FAFC', '#D4AF37', '#0F172A'],
        'blue-silver' => ['Blue + Silver', '#1D4ED8', '#F8FAFC', '#94A3B8', '#0F172A'],
        'green-navy' => ['Green + Navy', '#166534', '#F8FAFC', '#0B1F3A', '#0F172A'],
        'maroon-gold' => ['Maroon + Gold', '#7F1D1D', '#F8FAFC', '#D4AF37', '#0F172A'],
        'purple-gold' => ['Purple + Gold', '#6D28D9', '#F8FAFC', '#D4AF37', '#0F172A'],
    ];

    public int $step = 1;

    // Step 1 — basics
    public string $name = '';
    public string $type = 'conference';
    public string $description = '';
    public ?int $client_id = null;
    public string $new_client = '';
    public string $expected_participants = '';
    public string $city = '';
    public string $country = 'Jordan';
    public ?int $venue_id = null;
    public ?int $project_id = null;
    public ?int $project_manager_id = null;
    public string $starts_at = '';
    public string $ends_at = '';
    public string $budget = '';

    // Step 2 — avatar
    public ?int $avatar_id = null;
    public bool $avatarChosenManually = false;

    // Step 3 — color theme
    public string $palette = 'navy-gold';
    public string $primary_color = '#0B1F3A';
    public string $secondary_color = '#F8FAFC';
    public string $accent_color = '#D4AF37';
    public string $text_color = '#0F172A';

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

    public function updatedPalette(string $key): void
    {
        if (isset(self::PALETTES[$key])) {
            [, $this->primary_color, $this->secondary_color, $this->accent_color, $this->text_color] = self::PALETTES[$key];
        }
    }

    public function chooseAvatar(int $avatarId): void
    {
        $this->avatar_id = $avatarId;
        $this->avatarChosenManually = true;
    }

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validate([
                'name' => ['required', 'string', 'max:120'],
                'type' => ['required', 'in:'.implode(',', Event::TYPES)],
                'city' => ['required', 'string', 'max:80'],
                'country' => ['required', 'string', 'max:80'],
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'budget' => ['nullable', 'numeric', 'min:0'],
                'expected_participants' => ['nullable', 'integer', 'min:1'],
            ]);
        }

        if ($this->step === 2) {
            $this->validate(['avatar_id' => ['required', 'exists:event_avatars,id']]);
        }

        $this->step = min(4, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function save()
    {
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'in:'.implode(',', Event::TYPES)],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'project_id' => ['nullable', 'exists:projects,id'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'project_manager_id' => ['nullable', 'exists:users,id'],
            'avatar_id' => ['nullable', 'exists:event_avatars,id'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'expected_participants' => ['nullable', 'integer', 'min:1'],
            'primary_color' => ['required', 'string', 'max:9'],
            'secondary_color' => ['required', 'string', 'max:9'],
            'accent_color' => ['required', 'string', 'max:9'],
            'text_color' => ['required', 'string', 'max:9'],
        ]);

        if ($this->new_client !== '' && ! $this->client_id) {
            $this->client_id = Client::firstOrCreate(['name' => trim($this->new_client)])->id;
        }

        $event = Event::create([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'type' => $this->type,
            'status' => 'planning',
            'stage' => 'draft',
            'city' => $this->city,
            'country' => $this->country,
            'venue_id' => $this->venue_id,
            'project_id' => $this->project_id,
            'client_id' => $this->client_id,
            'project_manager_id' => $this->project_manager_id,
            'avatar_id' => $this->avatar_id,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at ?: null,
            'budget_cents' => (int) round((float) ($this->budget ?: 0) * 100),
            'progress' => 0,
            'expected_participants' => $this->expected_participants ?: null,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'text_color' => $this->text_color,
        ]);

        if ($this->project_manager_id) {
            $event->teamMembers()->syncWithoutDetaching([$this->project_manager_id => ['role' => 'project_manager']]);
        }

        session()->flash('status', "Event Hub for “{$event->name}” is live — set up the agenda, budget and suppliers from the tabs.");

        return $this->redirectRoute('events.hub', $event);
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
            'clients' => Client::orderBy('name')->get(),
            'managers' => User::orderBy('name')->get(),
            'types' => Event::TYPES,
            'palettes' => self::PALETTES,
        ]);
    }
}
