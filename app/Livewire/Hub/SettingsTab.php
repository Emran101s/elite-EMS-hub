<?php

namespace App\Livewire\Hub;

use App\Models\Client;
use App\Models\Event;
use App\Models\EventAvatar;
use App\Models\User;
use App\Models\Venue;
use Livewire\Component;

class SettingsTab extends Component
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

    public Event $event;

    // Details
    public string $name = '';
    public string $description = '';
    public ?int $client_id = null;
    public string $new_client = '';
    public string $expected_participants = '';
    public string $city = '';
    public string $country = 'Jordan';
    public string $starts_at = '';
    public string $ends_at = '';
    public string $budget = '';
    public string $stage = 'planning';

    // Ownership
    public ?int $project_manager_id = null;
    public ?int $venue_id = null;

    // Identity
    public ?int $avatar_id = null;
    public string $primary_color = '#0B1F3A';
    public string $secondary_color = '#F8FAFC';
    public string $accent_color = '#D4AF37';
    public string $text_color = '#0F172A';

    // Modules
    public array $modules = [];

    public function mount(): void
    {
        $e = $this->event;
        $this->name = $e->name;
        $this->description = (string) $e->description;
        $this->client_id = $e->client_id;
        $this->expected_participants = (string) ($e->expected_participants ?? '');
        $this->city = $e->city;
        $this->country = $e->country;
        $this->starts_at = $e->starts_at?->format('Y-m-d') ?? '';
        $this->ends_at = $e->ends_at?->format('Y-m-d') ?? '';
        $this->budget = $e->budget_cents ? (string) ($e->budget_cents / 100) : '';
        $this->stage = $e->stage;
        $this->project_manager_id = $e->project_manager_id;
        $this->venue_id = $e->venue_id;
        $this->avatar_id = $e->avatar_id;
        $theme = $e->theme();
        $this->primary_color = $theme['primary'];
        $this->secondary_color = $theme['secondary'];
        $this->accent_color = $theme['accent'];
        $this->text_color = $theme['text'];
        // null enabled_modules (legacy) = everything on
        $this->modules = $e->enabled_modules ?? array_keys(Event::HUB_MODULES);
    }

    public function usePalette(string $key): void
    {
        if (isset(self::PALETTES[$key])) {
            [, $this->primary_color, $this->secondary_color, $this->accent_color, $this->text_color] = self::PALETTES[$key];
        }
    }

    public function chooseAvatar(int $avatarId): void
    {
        $this->avatar_id = $avatarId;
    }

    public function toggleModule(string $key): void
    {
        if (! array_key_exists($key, Event::HUB_MODULES)) {
            return;
        }
        $this->modules = in_array($key, $this->modules, true)
            ? array_values(array_diff($this->modules, [$key]))
            : [...$this->modules, $key];
    }

    public function save()
    {
        $validated = $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:1000'],
            'client_id' => ['nullable', 'exists:clients,id'],
            'new_client' => ['nullable', 'string', 'max:120'],
            'expected_participants' => ['nullable', 'integer', 'min:0'],
            'city' => ['required', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'budget' => ['nullable', 'numeric', 'min:0'],
            'stage' => ['required', 'in:'.implode(',', Event::STAGES)],
            'project_manager_id' => ['nullable', 'exists:users,id'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'avatar_id' => ['nullable', 'exists:event_avatars,id'],
        ]);

        if ($this->new_client !== '' && ! $this->client_id) {
            $this->client_id = Client::firstOrCreate(['name' => trim($this->new_client)])->id;
        }

        $this->event->update([
            'name' => $this->name,
            'description' => $this->description ?: null,
            'client_id' => $this->client_id,
            'expected_participants' => $this->expected_participants !== '' ? (int) $this->expected_participants : null,
            'city' => $this->city,
            'country' => $this->country,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at ?: null,
            'budget_cents' => (int) round((float) ($this->budget ?: 0) * 100),
            'stage' => $this->stage,
            'project_manager_id' => $this->project_manager_id,
            'venue_id' => $this->venue_id,
            'avatar_id' => $this->avatar_id,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'text_color' => $this->text_color,
            'enabled_modules' => array_values(array_intersect(array_keys(Event::HUB_MODULES), $this->modules)),
        ]);

        if ($this->project_manager_id) {
            $this->event->teamMembers()->syncWithoutDetaching([$this->project_manager_id => ['role' => 'project_manager']]);
        }

        session()->flash('status', 'Event settings saved.');

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'settings']);
    }

    public function duplicate()
    {
        $copy = $this->event->replicate(['progress']);
        $copy->name = $this->event->name.' (Copy)';
        $copy->stage = 'draft';
        $copy->status = 'planning';
        $copy->progress = 0;
        $copy->archived_at = null;
        $copy->save();

        session()->flash('status', "Duplicated as “{$copy->name}”.");

        return $this->redirectRoute('events.hub', $copy);
    }

    public function archive()
    {
        $this->event->update(['archived_at' => now()]);

        return $this->redirectRoute('events.index');
    }

    public function render()
    {
        return view('livewire.hub.settings-tab', [
            'clients' => Client::orderBy('name')->get(),
            'managers' => User::orderBy('name')->get(),
            'venues' => Venue::orderBy('name')->get(),
            'avatars' => EventAvatar::active()->orderBy('sort_order')->get(),
            'palettes' => self::PALETTES,
            'hubModules' => Event::HUB_MODULES,
        ]);
    }
}
