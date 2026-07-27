<?php

namespace App\Livewire\Hub;

use App\Models\Client;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use App\Support\Taxonomy;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\WithFileUploads;

class SettingsTab extends Component
{
    use WithFileUploads;

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

    public string $currency = 'USD';

    public string $stage = 'planning';

    /** The kind of event. It sets the crest, and it was previously fixed at creation. */
    public string $type = 'conference';

    // Ownership
    public ?int $project_manager_id = null;

    public ?int $venue_id = null;

    // Event team assignment
    public ?int $teamUserId = null;

    public string $teamRole = 'operations_lead';

    // Identity
    public $cover = null;

    public $logo = null;

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
        $this->currency = $e->currency ?? 'USD';
        $this->stage = $e->stage;
        $this->type = $e->type;
        $this->project_manager_id = $e->project_manager_id;
        $this->venue_id = $e->venue_id;
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

    public function removeCover(): void
    {
        if ($this->event->cover_path) {
            $this->event->update(['cover_path' => null]);
        }
        $this->cover = null;
    }

    public function removeLogo(): void
    {
        if ($this->event->logo_path) {
            $this->event->update(['logo_path' => null]);
        }
        $this->logo = null;
    }

    /**
     * Turning a module on takes effect immediately rather than waiting for the
     * form's Save. Previously this only moved component state, so switching a
     * module on and navigating away lost the change silently — and the module
     * you wanted stayed missing from the hub with no indication why.
     */
    public function toggleModule(string $key): void
    {
        Gate::authorize('write');

        if (! array_key_exists($key, Event::HUB_MODULES)) {
            return;
        }

        $on = in_array($key, $this->modules, true);
        $this->modules = $on
            ? array_values(array_diff($this->modules, [$key]))
            : [...$this->modules, $key];

        // Persist in HUB_MODULES order so the hub nav keeps a stable sequence.
        $this->event->update([
            'enabled_modules' => array_values(array_intersect(array_keys(Event::HUB_MODULES), $this->modules)),
        ]);

        session()->flash('status', Event::HUB_MODULES[$key][0].($on ? ' turned off.' : ' turned on.'));
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
            'currency' => ['required', 'in:'.implode(',', array_keys(Event::CURRENCIES))],
            'stage' => ['required', 'in:'.implode(',', Event::STAGES)],
            // Any term still offered, plus whatever this event already is: an
            // event whose type was later retired must still be saveable.
            'type' => ['required', Rule::in(array_merge(array_keys(Taxonomy::options('event_type')), [$this->event->type]))],
            'project_manager_id' => ['nullable', 'exists:users,id'],
            'venue_id' => ['nullable', 'exists:venues,id'],
            'cover' => ['nullable', 'image', 'max:8192'],
            'logo' => ['nullable', 'image', 'max:4096'],
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
            'currency' => $this->currency,
            'stage' => $this->stage,
            'type' => $this->type,
            'project_manager_id' => $this->project_manager_id,
            'venue_id' => $this->venue_id,
            'cover_path' => $this->cover ? 'storage/'.$this->cover->store('event-covers', 'public') : $this->event->cover_path,
            'logo_path' => $this->logo ? 'storage/'.$this->logo->store('event-logos', 'public') : $this->event->logo_path,
            'primary_color' => $this->primary_color,
            'secondary_color' => $this->secondary_color,
            'accent_color' => $this->accent_color,
            'text_color' => $this->text_color,
            'enabled_modules' => array_values(array_intersect(array_keys(Event::HUB_MODULES), $this->modules)),
        ]);

        $this->reset(['cover', 'logo']);

        if ($this->project_manager_id) {
            $this->event->teamMembers()->syncWithoutDetaching([$this->project_manager_id => ['role' => 'project_manager']]);
        }

        // Reconcile agenda days with the (possibly changed) date range.
        $this->event->refresh()->syncAgendaDays();

        session()->flash('status', "Event settings saved · {$this->event->dayCount()} agenda ".str('day')->plural($this->event->dayCount()).'.');

        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'settings']);
    }

    /** Assign a workspace member to this event with a role. */
    public function addTeamMember(): void
    {
        if (! $this->teamUserId || ! array_key_exists($this->teamRole, Taxonomy::options('team_role'))) {
            return;
        }
        // Unique (event, user, role) makes this idempotent — no duplicate assignments.
        DB::table('event_team_members')->insertOrIgnore([
            'event_id' => $this->event->id,
            'user_id' => $this->teamUserId,
            'role' => $this->teamRole,
        ]);
        if ($this->teamRole === 'project_manager' && ! $this->event->project_manager_id) {
            $this->event->update(['project_manager_id' => $this->teamUserId]);
            $this->project_manager_id = $this->teamUserId;
        }
        $this->teamUserId = null;
    }

    public function removeTeamMember(int $userId, string $role): void
    {
        DB::table('event_team_members')
            ->where('event_id', $this->event->id)->where('user_id', $userId)->where('role', $role)->delete();
        if ($role === 'project_manager' && $this->event->project_manager_id === $userId) {
            $this->event->update(['project_manager_id' => null]);
            $this->project_manager_id = null;
        }
    }

    public function duplicate()
    {
        Gate::authorize('manage-events');
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
        Gate::authorize('manage-events');
        $this->event->update(['archived_at' => now()]);

        return $this->redirectRoute('events.index');
    }

    public function render()
    {
        return view('livewire.hub.settings-tab', [
            'clients' => Client::orderBy('name')->get(),
            'managers' => User::orderBy('name')->get(),
            'team' => $this->event->teamMembers()->orderBy('name')->get(),
            'roleLabels' => Taxonomy::options('team_role'),
            'venues' => Venue::orderBy('name')->get(),
            'palettes' => self::PALETTES,
            'hubModules' => Event::HUB_MODULES,
        ]);
    }
}
