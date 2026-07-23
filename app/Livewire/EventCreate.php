<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Event;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One canvas, not a three-step slog: answer a few questions on the left and
 * watch the event build itself on the right. Picking a type sets the crest and
 * the modules that type usually needs — all still editable before you commit.
 */
#[Layout('components.layouts.app', ['title' => 'Create Event', 'subtitle' => 'Answer a few questions — the event builds itself as you type.'])]
class EventCreate extends Component
{
    use WithFileUploads;

    /** Type templates: key => [label, event type, icon, default modules]. */
    public const TEMPLATES = [
        'conference' => ['Conference', 'conference', 'chat', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'reports']],
        'summit' => ['Summit', 'summit', 'sparkles', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'sponsors']],
        'exhibition' => ['Exhibition', 'exhibition', 'building', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'files', 'attendees']],
        'workshop' => ['Workshop', 'workshop', 'clipboard', ['agenda', 'tasks', 'budget', 'venue', 'attendees']],
        'seminar' => ['Seminar', 'training_program', 'identification', ['agenda', 'tasks', 'venue', 'attendees']],
        'gala' => ['Gala / Dinner', 'gala_dinner', 'star', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'attendees']],
        'corporate' => ['Corporate', 'product_launch', 'folder', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees']],
        'awards' => ['Awards', 'awards_ceremony', 'star', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'attendees']],
        'hybrid' => ['Hybrid', 'hybrid_event', 'globe', ['agenda', 'tasks', 'budget', 'venue', 'attendees', 'reports']],
        'outdoor' => ['Outdoor', 'outdoor_event', 'sparkles', ['tasks', 'budget', 'suppliers', 'venue', 'attendees']],
        'other' => ['Other', 'public_event', 'dots', ['tasks', 'budget', 'venue', 'attendees']],
    ];

    /** Status pills → lifecycle stage. */
    public const STATUS_PILLS = ['lead' => 'draft', 'proposal' => 'proposal', 'confirmed' => 'confirmed'];

    // ── Who & what ──
    public ?int $client_id = null;

    public string $new_client = '';

    public bool $newClientMode = false;

    public string $name = '';

    /** Uploaded cover image + logo (both optional). */
    public $cover = null;

    public $logo = null;

    // ── When & where ──
    public string $starts_at = '';

    public string $ends_at = '';

    public string $timezone = 'UTC';

    public string $city = '';

    // ── Shape ──
    public string $statusPill = 'lead';

    public ?string $template = null;

    public array $modules = [];

    public function mount(): void
    {
        $company = CompanyProfile::current();
        $this->timezone = $company->default_timezone;
        $this->city = (string) ($company->city ?: '');
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
        $this->modules = self::TEMPLATES[$key][3]; // pre-enable what this type usually needs
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

    /** The type this event will be created as (defaults to conference). */
    public function resolvedType(): string
    {
        return self::TEMPLATES[$this->template ?? 'conference'][1];
    }

    /** Agenda days the date range will scaffold — shown live in the preview. */
    public function dayCount(): int
    {
        if (! $this->starts_at) {
            return 0;
        }
        try {
            $start = Carbon::parse($this->starts_at)->startOfDay();
            $end = $this->ends_at ? Carbon::parse($this->ends_at)->startOfDay() : $start;
        } catch (\Throwable) {
            return 0;
        }

        return $end->lt($start) ? 0 : (int) $start->diffInDays($end) + 1;
    }

    public function save()
    {
        $this->validateAll();

        if ($this->new_client !== '' && ! $this->client_id) {
            $this->client_id = Client::firstOrCreate(['name' => trim($this->new_client)])->id;
        }

        $type = $this->resolvedType();
        $company = CompanyProfile::current();

        $event = Event::create([
            'name' => $this->name,
            'type' => $type,
            'stage' => self::STATUS_PILLS[$this->statusPill] ?? 'draft',
            'city' => $this->city ?: ($company->city ?: 'TBD'),
            'country' => $company->country ?: 'Jordan',
            'currency' => $company->default_currency,
            'management_fee_pct' => $company->default_management_fee_pct,
            'timezone' => $this->timezone,
            'client_id' => $this->client_id,
            'cover_path' => $this->cover ? 'storage/'.$this->cover->store('event-covers', 'public') : null,
            'logo_path' => $this->logo ? 'storage/'.$this->logo->store('event-logos', 'public') : null,
            'primary_color' => '#0B1F3A',
            'secondary_color' => '#F8FAFC',
            'accent_color' => '#D4AF37',
            'text_color' => '#0F172A',
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at ?: null,
            'progress' => 0,
            'enabled_modules' => array_values(array_intersect(array_keys(Event::HUB_MODULES), $this->modules)),
        ]);

        $event->syncAgendaDays();

        session()->flash('status', "Event “{$event->name}” created — {$event->dayCount()} agenda ".str('day')->plural($event->dayCount()).' ready in the Event Hub.');

        return $this->redirectRoute('events.hub', $event);
    }

    private function validateAll(): void
    {
        // Every problem surfaces at once — you shouldn't fix one field, resubmit,
        // and only then discover the next one is missing too.
        $this->validate([
            'client_id' => ['required_without:new_client', 'nullable', 'exists:clients,id'],
            'new_client' => ['nullable', 'string', 'max:120'],
            'name' => ['required', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['required', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:80'],
            'statusPill' => ['required', 'in:'.implode(',', array_keys(self::STATUS_PILLS))],
            'cover' => ['nullable', 'image', 'max:8192'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ], [
            'client_id.required_without' => 'Choose a client or add a new one.',
            'name.required' => 'Give the event a title.',
            'starts_at.required' => 'Pick a start date.',
            'ends_at.after_or_equal' => 'The end date can’t be before the start.',
        ]);
    }

    public function render()
    {
        $type = $this->resolvedType();

        return view('livewire.event-create', [
            'clients' => Client::orderBy('name')->get(),
            'templates' => self::TEMPLATES,
            'hubModules' => Event::HUB_MODULES,
            'timezones' => ['UTC', 'Asia/Amman', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Qatar', 'Asia/Bahrain', 'Europe/London', 'America/New_York'],
            'previewType' => $type,
            'previewDays' => $this->dayCount(),
        ]);
    }
}
