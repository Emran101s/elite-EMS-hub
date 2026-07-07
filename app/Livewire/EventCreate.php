<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\Event;
use App\Models\EventAvatar;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Create Event', 'subtitle' => 'Basics, type, then the modules this event needs.'])]
class EventCreate extends Component
{
    /** Type templates: key => [label, event type, icon, default modules]. */
    public const TEMPLATES = [
        'conference' => ['Conference', 'conference', 'chat', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'reports']],
        'summit' => ['Summit', 'summit', 'sparkles', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'sponsors']],
        'exhibition' => ['Exhibition', 'exhibition', 'building', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'files', 'attendees']],
        'workshop' => ['Workshop', 'workshop', 'clipboard', ['agenda', 'tasks', 'budget', 'venue', 'attendees']],
        'seminar' => ['Seminar', 'training_program', 'identification', ['agenda', 'tasks', 'venue', 'attendees']],
        'gala' => ['Gala / Dinner', 'gala_dinner', 'star', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors']],
        'corporate' => ['Corporate', 'product_launch', 'folder', ['agenda', 'tasks', 'budget', 'suppliers', 'venue']],
        'awards' => ['Awards', 'awards_ceremony', 'star', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'sponsors']],
        'hybrid' => ['Hybrid', 'hybrid_event', 'globe', ['agenda', 'tasks', 'budget', 'venue', 'attendees', 'reports']],
        'outdoor' => ['Outdoor', 'outdoor_event', 'sparkles', ['tasks', 'budget', 'suppliers', 'venue']],
        'other' => ['Other', 'public_event', 'dots', ['tasks', 'budget', 'venue']],
    ];

    /** Status pills → lifecycle stage. */
    public const STATUS_PILLS = ['lead' => 'draft', 'proposal' => 'proposal', 'confirmed' => 'confirmed'];

    public int $step = 1;

    // Step 1 — basics
    public ?int $client_id = null;
    public string $new_client = '';
    public bool $newClientMode = false;
    public string $name = '';
    public string $starts_at = '';
    public string $ends_at = '';
    public string $timezone = 'UTC';
    public string $statusPill = 'lead';

    // Step 2 — type template
    public ?string $template = null;

    // Step 3 — modules (enabled keys)
    public array $modules = [];

    public function mount(): void
    {
        // Sensible default module set until a template is chosen.
        $this->modules = self::TEMPLATES['conference'][3];
    }

    public function toggleNewClient(): void
    {
        $this->newClientMode = ! $this->newClientMode;
        $this->newClientMode ? $this->client_id = null : $this->new_client = '';
    }

    public function chooseTemplate(string $key): void
    {
        if (! isset(self::TEMPLATES[$key])) {
            return;
        }
        $this->template = $key;
        $this->modules = self::TEMPLATES[$key][3]; // pre-enable the modules this type usually needs
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

    public function next(): void
    {
        if ($this->step === 1) {
            $this->validateBasics();
        }
        $this->step = min(3, $this->step + 1);
    }

    public function back(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    /** "Create & open" (any step) and "Create event" (final) both land here. */
    public function save()
    {
        $this->validateBasics();

        if ($this->new_client !== '' && ! $this->client_id) {
            $this->client_id = Client::firstOrCreate(['name' => trim($this->new_client)])->id;
        }

        [, $type] = $this->template ? self::TEMPLATES[$this->template] : self::TEMPLATES['conference'];

        $event = Event::create([
            'name' => $this->name,
            'type' => $type,
            'status' => 'planning',
            'stage' => self::STATUS_PILLS[$this->statusPill] ?? 'draft',
            'city' => 'TBD',
            'country' => 'Jordan',
            'timezone' => $this->timezone,
            'client_id' => $this->client_id,
            // Avatar auto-assigned from the type; theme defaults to the platform brand.
            'avatar_id' => EventAvatar::recommendedFor($type)->value('id'),
            'primary_color' => '#0B1F3A',
            'secondary_color' => '#F8FAFC',
            'accent_color' => '#D4AF37',
            'text_color' => '#0F172A',
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at ?: null,
            'progress' => 0,
            'enabled_modules' => array_values(array_intersect(array_keys(Event::HUB_MODULES), $this->modules)),
        ]);

        // Build the agenda day scaffold from the date range so the agenda &
        // Run of Show are per-day from the start.
        $event->syncAgendaDays();

        session()->flash('status', "Event “{$event->name}” created — {$event->dayCount()} agenda ".str('day')->plural($event->dayCount()).' ready in the Event Hub.');

        return $this->redirectRoute('events.hub', $event);
    }

    private function validateBasics(): void
    {
        $this->validate([
            'client_id' => ['nullable', 'exists:clients,id'],
            'new_client' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['required', 'string', 'max:60'],
            'statusPill' => ['required', 'in:'.implode(',', array_keys(self::STATUS_PILLS))],
        ], [
            'client_id.required' => 'Choose a client or add a new one.',
            'name.required' => 'Give the event a title.',
            'starts_at.required' => 'Pick a start date.',
        ]);

        if (! $this->client_id && $this->new_client === '') {
            $this->addError('client_id', 'Choose a client or add a new one.');
            $this->validate(['client_id' => ['required']]); // halt
        }
    }

    public function render()
    {
        return view('livewire.event-create', [
            'clients' => Client::orderBy('name')->get(),
            'templates' => self::TEMPLATES,
            'hubModules' => Event::HUB_MODULES,
            'timezones' => ['UTC', 'Asia/Amman', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Qatar', 'Asia/Bahrain', 'Europe/London', 'America/New_York'],
        ]);
    }
}
