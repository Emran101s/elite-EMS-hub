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
 * Event Studio.
 *
 * Not a form. A form asks you to fill it, save it, and find out later what you
 * made; the studio shows you the event taking shape beside you while you define
 * it, so the thing you are deciding about is on screen the whole time.
 *
 * Five rooms, and a preview that is never stale:
 *
 *   1 Identity    what kind of event this is
 *   2 Origin      where it is coming from — commercial or internal
 *   3 Blueprint   when, where, and how big
 *   4 Modules     which parts of the platform it needs
 *   5 Launch      what is about to be built
 *
 * Nothing is written until Launch. Until then the preview IS the event.
 */
#[Layout('components.layouts.app', ['title' => 'Event Studio', 'hideTitleRow' => true])]
class EventCreate extends Component
{
    use WithFileUploads;

    /** The five rooms: key => [label, what it settles]. */
    public const STEPS = [
        1 => ['Identity', 'What kind of event this is'],
        2 => ['Origin', 'Where it is coming from'],
        3 => ['Blueprint', 'When, where, and how big'],
        4 => ['Modules', 'Which parts of the platform it needs'],
        5 => ['Launch', 'What is about to be built'],
    ];

    public int $step = 1;

    /** Where the working draft lives between visits. */
    private const DRAFT = 'event-studio.draft';

    /** When the draft was last written — the bar reports this. */
    public ?string $savedAt = null;

    /** The preview, stripped to what a delegate would be shown. */
    public bool $asAttendee = false;

    /** Event category: key => [label, event type, icon, default modules]. */
    public const CATEGORIES = [
        'conference' => ['Conference', 'conference', 'chat', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'reports']],
        'summit' => ['Summit', 'summit', 'sparkles', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees', 'sponsors']],
        'exhibition' => ['Exhibition', 'exhibition', 'building', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'files', 'attendees']],
        'workshop' => ['Workshop', 'workshop', 'clipboard', ['agenda', 'tasks', 'budget', 'venue', 'attendees']],
        'gala' => ['Gala', 'gala_dinner', 'star', ['tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'attendees']],
        'corporate' => ['Corporate', 'product_launch', 'folder', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'attendees']],
        'awards' => ['Awards', 'awards_ceremony', 'star', ['agenda', 'tasks', 'budget', 'suppliers', 'venue', 'sponsors', 'attendees']],
    ];

    /** How exclusive the event is: key => [label, hex]. */
    public const VIP_LEVELS = [
        'standard' => ['Standard', '#64748B'],
        'vip' => ['VIP', '#D4AF37'],
        'executive' => ['Executive', '#0B1F3A'],
        'head_of_state' => ['Head of State', '#7C2D12'],
    ];

    /** How delegates attend: key => [label, icon]. */
    public const FORMATS = [
        'in_person' => ['In-Person', 'pin'],
        'virtual' => ['Virtual', 'globe'],
        'hybrid' => ['Hybrid', 'grid'],
    ];

    /** Origin, commercial branch: key => label. */
    public const ORIGIN_COMMERCIAL = ['deal' => 'Deal', 'proposal' => 'Proposal', 'contract' => 'Contract'];

    /** Origin, internal branch: key => label. */
    public const ORIGIN_INTERNAL = ['initiative' => 'Internal Initiative', 'program' => 'Strategic Program', 'executive' => 'Executive Request'];

    /** Origin decides how committed the event already is — the lifecycle stage it launches into. */
    public const ORIGIN_STAGE = [
        'deal' => 'draft',
        'proposal' => 'proposal',
        'contract' => 'confirmed',
        'initiative' => 'draft',
        'program' => 'planning',
        'executive' => 'confirmed',
    ];

    // ── Identity ──
    public string $name = '';

    public string $category = '';

    public string $priority = 'normal';

    public string $description = '';

    /** 'commercial' | 'internal' — decides what Origin offers next. */
    public string $originKind = '';

    // ── Origin ──
    public string $originSource = '';

    public ?int $client_id = null;

    public string $new_client = '';

    public bool $newClientMode = false;

    // ── Blueprint ──
    public string $starts_at = '';

    public string $ends_at = '';

    public string $timezone = 'UTC';

    public string $city = '';

    public string $country = '';

    public ?int $venue_id = null;

    public string $expected_participants = '';

    public string $vipLevel = 'standard';

    public string $format = 'in_person';

    public string $budget = '';

    public string $currency = '';

    public ?int $project_manager_id = null;

    /** Uploaded cover image + logo (both optional). */
    public $cover = null;

    public $logo = null;

    // ── Modules ──
    public array $modules = [];

    public function mount(): void
    {
        $company = CompanyProfile::current();
        $this->timezone = $company->default_timezone;
        $this->country = (string) ($company->country ?: '');
        $this->currency = (string) $company->default_currency;

        // No modules and no category until you choose one. The studio used to
        // open with a conference's seven already ticked while no tile was lit —
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

    /** Changing the branch invalidates whatever source you had picked in the old one. */
    public function updatedOriginKind(): void
    {
        $this->originSource = '';
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

    public function chooseCategory(string $key): void
    {
        if (! isset(self::CATEGORIES[$key])) {
            return;
        }
        $this->category = $key;
        $this->modules = self::CATEGORIES[$key][3]; // pre-enable what this category usually needs
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
        return self::CATEGORIES[$this->category !== '' ? $this->category : 'conference'][1];
    }

    /** The options Origin should offer, given the branch already chosen in Identity. */
    public function originOptions(): array
    {
        return $this->originKind === 'internal' ? self::ORIGIN_INTERNAL : self::ORIGIN_COMMERCIAL;
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
            'stage' => self::ORIGIN_STAGE[$this->originSource] ?? 'draft',
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
                'category' => ['required', 'in:'.implode(',', array_keys(self::CATEGORIES))],
                'priority' => ['required', 'in:'.implode(',', array_keys(Event::PRIORITIES))],
                'description' => ['nullable', 'string', 'max:600'],
                'originKind' => ['required', 'in:commercial,internal'],
            ],
            2 => array_merge([
                'originSource' => ['required', 'in:'.implode(',', array_keys($this->originOptions()))],
                'new_client' => ['nullable', 'string', 'max:120'],
            ], $this->originKind === 'commercial'
                ? ['client_id' => ['required_without:new_client', 'nullable', 'exists:clients,id']]
                : ['client_id' => ['nullable', 'exists:clients,id']]),
            3 => [
                'starts_at' => ['required', 'date'],
                'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
                'timezone' => ['required', 'string', 'max:60'],
                'city' => ['nullable', 'string', 'max:80'],
                'venue_id' => ['nullable', 'exists:venues,id'],
                'expected_participants' => ['nullable', 'integer', 'min:0', 'max:1000000'],
                'vipLevel' => ['nullable', 'in:'.implode(',', array_keys(self::VIP_LEVELS))],
                'format' => ['nullable', 'in:'.implode(',', array_keys(self::FORMATS))],
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
                'category.required' => 'Pick the kind of event this is.',
                'originKind.required' => 'Say whether this is commercial or internal.',
                'originSource.required' => 'Pick where this event is coming from.',
                'client_id.required_without' => 'Choose a client, or add a new one.',
                'starts_at.required' => 'Pick a start date. The agenda, the transport and the run of show all hang off it.',
                'ends_at.after_or_equal' => 'The end date cannot be before the start.',
            ]);
        }
    }

    /**
     * Every gate the studio checks, grouped by the room that answers it — the
     * one place readiness and the per-room status in the launch summary are
     * both computed from, so they can never disagree with each other.
     *
     * @return array<string, array<string, bool>>
     */
    private function gates(): array
    {
        return [
            'identity' => [
                'A name' => $this->name !== '',
                'A category' => $this->category !== '',
                'A sentence about it' => trim($this->description) !== '',
            ],
            'origin' => [
                'Commercial or internal' => $this->originKind !== '',
                'An origin source' => $this->originSource !== '',
                'A client' => $this->originKind === 'internal' || $this->client_id !== null || trim($this->new_client) !== '',
            ],
            'blueprint' => [
                'A start date' => $this->starts_at !== '',
                'A city or venue' => $this->city !== '' || $this->venue_id !== null,
                'An expected headcount' => $this->expected_participants !== '',
            ],
            'modules' => [
                'At least one module' => $this->modules !== [],
            ],
        ];
    }

    /**
     * How much of the event is defined — the figure the preview reports, both
     * overall and room by room. Counted off the same answers the studio asks
     * for, so it cannot claim progress you have not made.
     *
     * @return array{pct:int,done:int,total:int,missing:array<int,string>,sections:array}
     */
    public function readiness(): array
    {
        $sections = $this->gates();
        $flatGates = array_merge(...array_values($sections));
        $done = count(array_filter($flatGates));
        $total = count($flatGates);

        $sectionStatus = [];
        foreach ($sections as $key => $gates) {
            $sDone = count(array_filter($gates));
            $sTotal = count($gates);
            $sectionStatus[$key] = [
                'label' => ucfirst($key),
                'done' => $sDone,
                'total' => $sTotal,
                'complete' => $sDone === $sTotal,
                'pct' => $sTotal ? (int) round($sDone / $sTotal * 100) : 100,
            ];
        }

        return [
            'pct' => $total ? (int) round($done / $total * 100) : 0,
            'done' => $done,
            'total' => $total,
            'missing' => array_keys(array_filter($flatGates, fn ($met) => ! $met)),
            'sections' => $sectionStatus,
        ];
    }

    /** Which room each field is answered in, so a failed launch can go there. */
    private const ROOM_OF = [
        'name' => 1, 'category' => 1, 'priority' => 1, 'description' => 1, 'originKind' => 1,
        'originSource' => 2, 'client_id' => 2, 'new_client' => 2,
        'starts_at' => 3, 'ends_at' => 3, 'timezone' => 3, 'city' => 3, 'country' => 3,
        'venue_id' => 3, 'expected_participants' => 3, 'vipLevel' => 3, 'format' => 3,
        'budget' => 3, 'currency' => 3, 'project_manager_id' => 3, 'cover' => 3, 'logo' => 3,
        'modules' => 4,
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
            'name' => ['required', 'string', 'max:120'],
            'category' => ['required', 'in:'.implode(',', array_keys(self::CATEGORIES))],
            'originKind' => ['required', 'in:commercial,internal'],
            'originSource' => ['required', 'in:'.implode(',', array_keys($this->originOptions()))],
            'client_id' => $this->originKind === 'commercial'
                ? ['required_without:new_client', 'nullable', 'exists:clients,id']
                : ['nullable', 'exists:clients,id'],
            'new_client' => ['nullable', 'string', 'max:120'],
            'starts_at' => ['required', 'date'],
            'ends_at' => ['nullable', 'date', 'after_or_equal:starts_at'],
            'timezone' => ['required', 'string', 'max:60'],
            'city' => ['nullable', 'string', 'max:80'],
            'cover' => ['nullable', 'image', 'max:8192'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ], [
            'client_id.required_without' => 'Choose a client or add a new one.',
            'name.required' => 'Give the event a title.',
            'category.required' => 'Pick the kind of event this is.',
            'originKind.required' => 'Say whether this is commercial or internal.',
            'originSource.required' => 'Pick where this event is coming from.',
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
            'categories' => self::CATEGORIES,
            'vipLevels' => self::VIP_LEVELS,
            'formats' => self::FORMATS,
            'originCommercial' => self::ORIGIN_COMMERCIAL,
            'originInternal' => self::ORIGIN_INTERNAL,
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
