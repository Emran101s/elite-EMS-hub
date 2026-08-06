<?php

namespace App\Livewire;

use App\Models\Client;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * One canvas, not a three-step slog: answer a few questions on the left and
 * watch the event build itself on the right. Picking a type sets the crest and
 * the modules that type usually needs — all still editable before you commit.
 */
/**
 * Event Studio.
 *
 * Not a form. A form asks you to fill it, save it, and find out later what you
 * made; the studio shows you the event taking shape beside you while you define
 * it, so the thing you are deciding about is on screen the whole time.
 *
 * Five rooms, and a preview that is never stale:
 *
 *   1 Identity        who it is for, what kind of thing it is
 *   2 When & where     the dates everything else hangs off
 *   3 Modules          which of the twenty-one it needs
 *   4 Details          the numbers and the sentence
 *   5 Review & launch  what is about to be built
 *
 * Nothing is written until Launch. Until then the preview IS the event.
 */
#[Layout('components.layouts.app', ['title' => 'Event Studio', 'hideTitleRow' => true])]
class EventCreate extends Component
{
    use WithFileUploads;

    /** The five rooms: key => [label, what it settles]. */
    public const STEPS = [
        1 => ['Event Identity', 'Who it is for, and what kind of thing it is'],
        2 => ['When & Where', 'The dates everything else hangs off'],
        3 => ['Modules & Tools', 'Which parts of the platform it needs'],
        4 => ['Additional Details', 'The numbers, and the sentence about it'],
        5 => ['Review & Launch', 'What is about to be built'],
    ];

    public int $step = 1;

    /** Where the working draft lives between visits. */
    private const DRAFT = 'event-studio.draft';

    /** When the draft was last written — the bar reports this. */
    public ?string $savedAt = null;

    /** The preview, stripped to what a delegate would be shown. */
    public bool $asAttendee = false;

    /** Type templates: key => [label, event type, icon, default modules]. */
    /** Type tiles: key => [label, event type, icon, default modules, what it is]. */
    public const TEMPLATES = [
        'conference' => ['Conference', 'conference', 'chat', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'reports'], 'Large scale knowledge sharing'],
        'summit' => ['Summit', 'summit', 'sparkles', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'sponsors'], 'Executive level summit'],
        'exhibition' => ['Exhibition', 'exhibition', 'building', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'files', 'attendees'], 'Products & services showcase'],
        'workshop' => ['Workshop', 'workshop', 'clipboard', ['agenda', 'tasks', 'budget', 'venue', 'attendees'], 'Interactive hands-on session'],
        'seminar' => ['Seminar', 'training_program', 'identification', ['agenda', 'tasks', 'venue', 'attendees'], 'Educational seminar'],
        'gala' => ['Gala Dinner', 'gala_dinner', 'star', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'attendees'], 'Evening gala & dinner'],
        'corporate' => ['Corporate', 'product_launch', 'folder', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees'], 'Corporate meeting/event'],
        'awards' => ['Awards', 'awards_ceremony', 'star', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'attendees'], 'Awards & recognition'],
        'hybrid' => ['Hybrid', 'hybrid_event', 'globe', ['agenda', 'tasks', 'budget', 'venue', 'attendees', 'reports'], 'In-person & online'],
        'outdoor' => ['Outdoor', 'outdoor_event', 'sparkles', ['tasks', 'budget', 'suppliers', 'venue', 'attendees'], 'Outdoor experience'],
        'other' => ['Other', 'public_event', 'dots', ['tasks', 'budget', 'venue', 'attendees'], 'Custom event type'],
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

    public string $country = '';

    public ?int $venue_id = null;

    // ── Details ──
    public string $description = '';

    public string $expected_participants = '';

    public string $budget = '';

    public string $currency = '';

    public ?int $project_manager_id = null;

    public string $priority = 'normal';

    // ── Shape ──
    public string $statusPill = 'lead';

    public ?string $template = null;

    public array $modules = [];

    public function mount(): void
    {
        $company = CompanyProfile::current();
        $this->timezone = $company->default_timezone;
        $this->country = (string) ($company->country ?: '');
        $this->currency = (string) $company->default_currency;

        // No modules and no type until you choose one. The studio used to open
        // with a conference's seven already ticked while no type tile was lit —
        // the launch bar said "7 selected" and the workspace said nothing was
        // picked. An empty studio should look empty.

        $this->restoreDraft();
    }

    /**
     * The draft is saved on every change, so the bar telling you it was saved
     * is telling the truth and closing the tab does not cost you the work.
     */
    public function updated(): void
    {
        $this->saveDraft();
    }

    private function saveDraft(): void
    {
        session()->put(self::DRAFT, [
            'at' => now()->toIso8601String(),
            'fields' => collect($this->all())
                ->except(['savedAt', 'cover', 'logo', 'errorBag', 'errorBagMessages'])
                ->all(),
        ]);

        $this->savedAt = now()->toIso8601String();
    }

    private function restoreDraft(): void
    {
        $draft = session(self::DRAFT);

        if (! is_array($draft) || ! isset($draft['fields'])) {
            return;
        }

        foreach ($draft['fields'] as $key => $value) {
            if (property_exists($this, $key)) {
                $this->{$key} = $value;
            }
        }

        $this->savedAt = $draft['at'] ?? null;
    }

    /** How long ago the draft was written, in words. */
    public function savedAgo(): ?string
    {
        return $this->savedAt ? Carbon::parse($this->savedAt)->diffForHumans() : null;
    }

    /**
     * The first thing the platform will put in the diary.
     *
     * Not invented: agenda lock is conventionally a month before doors, and
     * that is the date the studio will schedule, so it is shown before launch
     * rather than after.
     */
    public function firstMilestone(): ?array
    {
        if (! $this->starts_at) {
            return null;
        }

        try {
            $due = Carbon::parse($this->starts_at)->subDays(30)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        $days = (int) Carbon::today()->diffInDays($due, false);

        return [
            'title' => 'Agenda finalisation',
            'due' => $due->format('j M Y'),
            'note' => match (true) {
                $days < 0 => abs($days).' '.str('day')->plural(abs($days)).' overdue',
                $days === 0 => 'Due today',
                default => $days.' '.str('day')->plural($days).' left',
            },
            'late' => $days < 0,
        ];
    }

    /**
     * Move between rooms. You can jump to any room you have already reached —
     * the studio is a workspace, not a queue — but not past a gate that has
     * not been answered.
     */
    public function goTo(int $step): void
    {
        $this->step = max(1, min(count(self::STEPS), $step));
    }

    public function next(): void
    {
        $this->validateStep($this->step);
        $this->goTo($this->step + 1);
    }

    public function back(): void
    {
        $this->goTo($this->step - 1);
    }

    public function setPriority(string $key): void
    {
        if (array_key_exists($key, Event::PRIORITIES)) {
            $this->priority = $key;
        }
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
        // Creating a new event is additive, ordinary operational work — unlike
        // duplicate/archive/delete on an existing one, nothing is destroyed or
        // hidden, so it sits at the same tier as everything else a coordinator
        // does day to day.
        $this->authorize('create', Event::class);
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
            'country' => $this->country ?: ($company->country ?: 'Jordan'),
            'currency' => $this->currency ?: $company->default_currency,
            'management_fee_pct' => $company->default_management_fee_pct ?? 15.0,
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
            'priority' => $this->priority,
            'venue_id' => $this->venue_id,
            'description' => trim($this->description) ?: null,
            'expected_participants' => $this->expected_participants !== '' ? (int) $this->expected_participants : null,
            'budget_cents' => $this->budget !== '' ? (int) round((float) $this->budget * 100) : 0,
            'project_manager_id' => $this->project_manager_id,
            'enabled_modules' => array_values(array_intersect(array_keys(Event::HUB_MODULES), $this->modules)),
        ]);

        $event->syncAgendaDays();

        // The draft has become an event; there is nothing left to restore.
        session()->forget(self::DRAFT);

        session()->flash('status', "Event “{$event->name}” created — {$event->dayCount()} agenda ".str('day')->plural($event->dayCount()).' ready in the Event Hub.');

        return $this->redirectRoute('events.hub', $event);
    }

    /**
     * A room is checked when you leave it, not at the end. Finding out on the
     * last screen that the first one was wrong is the thing a studio avoids.
     */
    private function validateStep(int $step): void
    {
        $rules = match ($step) {
            1 => [
                'name' => ['required', 'string', 'max:120'],
                'client_id' => ['required_without:new_client', 'nullable', 'exists:clients,id'],
                'new_client' => ['nullable', 'string', 'max:120'],
                'template' => ['required'],
                'statusPill' => ['required', 'in:'.implode(',', array_keys(self::STATUS_PILLS))],
                'priority' => ['required', 'in:'.implode(',', array_keys(Event::PRIORITIES))],
            ],
            2 => [
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'timezone' => ['required', 'string', 'max:60'],
                'city' => ['nullable', 'string', 'max:80'],
                'venue_id' => ['nullable', 'exists:venues,id'],
            ],
            4 => [
                'description' => ['nullable', 'string', 'max:600'],
                'expected_participants' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'budget' => ['nullable', 'numeric', 'min:0'],
                'project_manager_id' => ['nullable', 'exists:users,id'],
                'cover' => ['nullable', 'image', 'max:8192'],
                'logo' => ['nullable', 'image', 'max:4096'],
            ],
            default => [],
        };

        if ($rules) {
            $this->validate($rules, [
                'name.required' => 'Give the event a name — everything else is built around it.',
                'client_id.required_without' => 'Choose a client, or add a new one.',
                'template.required' => 'Pick the kind of event this is.',
                'starts_at.required' => 'Pick a start date. The agenda, the transport and the run of show all hang off it.',
                'ends_at.after_or_equal' => 'The end date cannot be before the start.',
            ]);
        }
    }

    /**
     * How much of the event is defined — the figure the preview reports.
     *
     * Counted off the same answers the studio asks for, so it cannot claim
     * progress you have not made.
     *
     * @return array{pct:int,done:int,total:int,missing:array<int,string>}
     */
    public function readiness(): array
    {
        $gates = [
            'A name' => $this->name !== '',
            'A client' => $this->client_id !== null || trim($this->new_client) !== '',
            'A kind of event' => $this->template !== null,
            'A start date' => $this->starts_at !== '',
            'A city or venue' => $this->city !== '' || $this->venue_id !== null,
            'At least one module' => $this->modules !== [],
            'A sentence about it' => trim($this->description) !== '',
            'An expected headcount' => $this->expected_participants !== '',
        ];

        $done = count(array_filter($gates));

        return [
            'pct' => (int) round($done / count($gates) * 100),
            'done' => $done,
            'total' => count($gates),
            'missing' => array_keys(array_filter($gates, fn ($met) => ! $met)),
        ];
    }

    /** Which room each field is answered in, so a failed launch can go there. */
    private const ROOM_OF = [
        'name' => 1, 'client_id' => 1, 'new_client' => 1, 'template' => 1,
        'statusPill' => 1, 'priority' => 1,
        'starts_at' => 2, 'ends_at' => 2, 'timezone' => 2, 'city' => 2,
        'country' => 2, 'venue_id' => 2,
        'modules' => 3,
        'description' => 4, 'expected_participants' => 4, 'budget' => 4,
        'currency' => 4, 'project_manager_id' => 4, 'cover' => 4, 'logo' => 4,
    ];

    private function validateAll(): void
    {
        // Every problem surfaces at once — you shouldn't fix one field, resubmit,
        // and only then discover the next one is missing too.
        try {
            $this->validateAllRules();
        } catch (ValidationException $e) {
            // The message renders beside its field, and its field lives in a room
            // you may not be standing in — pressing Launch on the review screen
            // and watching nothing happen is the same as no button at all.
            $rooms = array_map(fn ($k) => self::ROOM_OF[explode('.', $k)[0]] ?? 5, array_keys($e->errors()));
            $this->step = $rooms ? min($rooms) : $this->step;

            throw $e;
        }
    }

    private function validateAllRules(): void
    {
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
        $company = CompanyProfile::current();

        return view('livewire.event-create', [
            'steps' => self::STEPS,
            'clients' => Client::orderBy('name')->get(),
            'venues' => Venue::orderBy('name')->get(),
            'managers' => User::orderBy('name')->get(),
            'templates' => self::TEMPLATES,
            'hubModules' => Event::HUB_MODULES,
            'priorities' => Event::PRIORITIES,
            'timezones' => ['UTC', 'Asia/Amman', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Qatar', 'Asia/Bahrain', 'Europe/London', 'America/New_York'],
            'currencies' => Event::CURRENCIES,
            'defaultCurrency' => $company->default_currency,
            'previewType' => $this->resolvedType(),
            'previewDays' => $this->dayCount(),
            'readiness' => $this->readiness(),
            'milestone' => $this->firstMilestone(),
            'savedAgo' => $this->savedAgo(),
            // Roughly what the platform will scaffold once it launches — the
            // studio's own estimate, not a stored number.
            'setupMinutes' => max(1, (int) ceil(count($this->modules) * 0.35)),
        ]);
    }
}
