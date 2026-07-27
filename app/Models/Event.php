<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Services\EventHealthService;
use App\Support\Workflow;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'name', 'description', 'type', 'stage', 'city', 'country', 'timezone',
    'venue_id', 'project_id', 'client_id', 'project_manager_id', 'cover_path', 'logo_path',
    'starts_at', 'ends_at', 'budget_cents', 'client_target_cents', 'sponsorship_target_cents', 'exhibition_target_cents', 'exhibition_fixtures', 'event_requirements', 'currency', 'management_fee_pct', 'planner_config', 'budget_status', 'budget_locked_at', 'progress', 'expected_participants',
    'registration_token', 'registration_open', 'registration_capacity', 'registration_note',
    'primary_color', 'secondary_color', 'accent_color', 'text_color', 'archived_at', 'enabled_modules',
])]
class Event extends Model
{
    use Auditable;

    /** Only these changes are audit-worthy — decisions, not noise. */
    public const AUDIT_FIELDS = ['stage', 'archived_at', 'budget_status', 'budget_cents', 'name', 'starts_at', 'ends_at'];

    /** @use HasFactory<EventFactory> */
    use HasFactory;

    public const TYPES = [
        'conference', 'summit', 'workshop', 'gala_dinner', 'exhibition', 'career_fair',
        'vip_reception', 'embassy_event', 'training_program', 'product_launch',
        'awards_ceremony', 'outdoor_event', 'public_event', 'private_dinner',
        'hybrid_event', 'online_event',
    ];

    /**
     * Toggleable Event Hub modules (Overview / AI Insights / Settings are always on).
     * key => [tab label, category, icon].
     */
    public const HUB_MODULES = [
        'brief' => ['Event Brief', 'Plan', 'clipboard'],
        'contract' => ['Contract', 'Plan', 'identification'],
        'planning' => ['Planning', 'Plan', 'list'],
        'agenda' => ['Agenda', 'Programme', 'calendar'],
        'speakers' => ['Speakers', 'Programme', 'identification'],
        'tasks' => ['Tasks', 'Plan', 'clipboard'],
        'budget' => ['Budget', 'Plan', 'currency'],
        'suppliers' => ['Suppliers', 'Logistics', 'truck'],
        'venue' => ['Venue', 'Logistics', 'building'],
        'transportation' => ['Transport', 'Logistics', 'truck'],
        'accommodation' => ['Accommodation', 'Logistics', 'home'],
        'exhibition' => ['Exhibition', 'Exhibition', 'grid'],
        'sponsors' => ['Sponsors', 'Exhibition', 'star'],
        'attendees' => ['Attendees', 'Sell', 'users'],
        'files' => ['Documents', 'Grow', 'archive'],
        'risks' => ['Risks', 'Plan', 'bell'],
        'approvals' => ['Approvals', 'Plan', 'identification'],
        'reports' => ['Reports', 'Grow', 'chart'],
    ];

    /**
     * A colour per module, used for document folders and module chips.
     *
     * Grouped by the HUB_MODULES category so the palette reads as a system —
     * Plan is blue, Programme teal, Logistics amber, Exhibition violet, Sell
     * green — rather than eighteen unrelated hues. Risks keeps a semantic red.
     */
    public const MODULE_COLORS = [
        'brief' => '#3B6FD4', 'contract' => '#2E5AA8', 'planning' => '#4C7FE0',
        'tasks' => '#5B8DEF', 'budget' => '#1F4B99', 'risks' => '#E2574C', 'approvals' => '#7C6BD9',
        'agenda' => '#0E9488', 'speakers' => '#14B8A6',
        'suppliers' => '#C2761E', 'venue' => '#B45309', 'transportation' => '#D97706', 'accommodation' => '#A16207',
        'exhibition' => '#A855F7', 'sponsors' => '#C026D3',
        'attendees' => '#16A34A',
        'files' => '#B08D2F', 'reports' => '#64748B',
    ];

    /**
     * The lifecycle palette, mirroring MODULE_COLORS. Two views declared this
     * map inline and had drifted apart; stage colour now has one home.
     */
    public const STAGE_COLORS = [
        'draft' => '#94A3B8', 'proposal' => '#3B82F6', 'confirmed' => '#06B6D4',
        'planning' => '#8B5CF6', 'production' => '#D4AF37', 'live' => '#22C55E',
        'completed' => '#10B981', 'closed' => '#64748B',
        'cancelled' => '#F87171', 'on_hold' => '#F59E0B',
    ];

    public static function stageColor(?string $stage): string
    {
        return Workflow::color('event_stage', $stage);
    }

    /**
     * The 12 modules that ride the orbit, split by ORBIT's second law: distance
     * is relevance. The inner ring is what an event manager opens daily; the
     * outer ring is structure. Everything else in HUB_MODULES stays reachable
     * from the dock, ⌘K and cross-links — the orbit is a shortlist, not a menu.
     *
     * The ORBIT sprite has its own icon names, so they are mapped here rather
     * than reusing HUB_MODULES' Heroicon names.
     *
     * @return array{inner: array<int, array<string, mixed>>, outer: array<int, array<string, mixed>>}
     */
    public static function orbitRings(): array
    {
        return [
            'inner' => [
                ['key' => 'overview', 'label' => 'Overview', 'icon' => 'home'],
                ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'task'],
                ['key' => 'agenda', 'label' => 'Agenda', 'icon' => 'cal'],
                ['key' => 'venue', 'label' => 'Venue', 'icon' => 'venue'],
                ['key' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'truck'],
            ],
            'outer' => [
                ['key' => 'planning', 'label' => 'Planning', 'icon' => 'note'],
                ['key' => 'speakers', 'label' => 'Speakers', 'icon' => 'mic'],
                ['key' => 'exhibition', 'label' => 'Exhibition', 'icon' => 'grid'],
                ['key' => 'sponsors', 'label' => 'Sponsors', 'icon' => 'star'],
                ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart'],
                ['key' => 'files', 'label' => 'Documents', 'icon' => 'doc'],
                ['key' => 'settings', 'label' => 'Settings', 'icon' => 'gear'],
            ],
        ];
    }

    /** The floating dock: nine slots, the last always AI. */
    public static function orbitDock(): array
    {
        return [
            ['key' => 'overview', 'label' => 'Home', 'icon' => 'home'],
            ['key' => 'agenda', 'label' => 'Calendar', 'icon' => 'cal'],
            ['key' => 'tasks', 'label' => 'Tasks', 'icon' => 'task'],
            ['key' => 'budget', 'label' => 'Budget', 'icon' => 'money'],
            ['key' => 'venue', 'label' => 'Venue', 'icon' => 'venue'],
            ['key' => 'speakers', 'label' => 'Speakers', 'icon' => 'mic'],
            ['key' => 'suppliers', 'label' => 'Suppliers', 'icon' => 'truck'],
            ['key' => 'reports', 'label' => 'Reports', 'icon' => 'chart'],
            ['key' => 'ai', 'label' => 'AI', 'icon' => 'spark'],
        ];
    }

    /** Event-wide papers get the brand navy rather than a module hue. */
    public static function moduleColor(?string $key): string
    {
        return self::MODULE_COLORS[$key] ?? '#0B1F3A';
    }

    public static function moduleLabel(?string $key): string
    {
        return $key === null ? 'Event-wide' : (self::HUB_MODULES[$key][0] ?? ucfirst($key));
    }

    public static function moduleIcon(?string $key): string
    {
        return $key === null ? 'archive' : (self::HUB_MODULES[$key][2] ?? 'archive');
    }

    /** Supported currencies: code => [symbol, label]. */
    public const CURRENCIES = [
        'USD' => ['$', 'US Dollar'],
        'JOD' => ['JD', 'Jordanian Dinar'],
    ];

    /** Lifecycle stages — the event's single stored life story. Health is computed. */
    public const STAGES = ['draft', 'proposal', 'confirmed', 'planning', 'production', 'live', 'completed', 'closed', 'cancelled', 'on_hold'];

    public const TEAM_ROLES = ['project_manager', 'operations_lead', 'registration_lead', 'supplier_coordinator', 'finance_owner', 'design_owner', 'production_owner', 'client_rm'];

    public const TEAM_ROLE_LABELS = [
        'project_manager' => 'Project Manager',
        'operations_lead' => 'Operations Lead',
        'registration_lead' => 'Registration Lead',
        'supplier_coordinator' => 'Supplier Coordinator',
        'finance_owner' => 'Finance Owner',
        'design_owner' => 'Design Owner',
        'production_owner' => 'Production Owner',
        'client_rm' => 'Client Relationship Mgr',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'date',
            'ends_at' => 'date',
            'budget_cents' => 'integer',
            'registration_open' => 'boolean',
            'registration_capacity' => 'integer',
            'client_target_cents' => 'integer',
            'sponsorship_target_cents' => 'integer',
            'exhibition_target_cents' => 'integer',
            'exhibition_fixtures' => 'array',
            'event_requirements' => 'array',
            'management_fee_pct' => 'float',
            'planner_config' => 'array',
            'budget_locked_at' => 'datetime',
            'progress' => 'integer',
            'expected_participants' => 'integer',
            'archived_at' => 'datetime',
            'enabled_modules' => 'array',
        ];
    }

    /**
     * Is a hub module active? Legacy events (null enabled_modules) show everything.
     */
    public function moduleEnabled(string $key): bool
    {
        if (! array_key_exists($key, self::HUB_MODULES)) {
            return true; // overview / ai / settings are never gated
        }

        return $this->enabled_modules === null || in_array($key, $this->enabled_modules, true);
    }

    /**
     * Events still in play: not archived and not at the end of their lifecycle.
     * The single definition of "active" — never filter on a health word.
     */
    public function scopeActive($query)
    {
        return $query->whereNull('archived_at')
            ->whereNotIn('stage', ['completed', 'closed', 'cancelled']);
    }

    /** Currency symbol for this event ($ or JD). */
    public function currencySymbol(): string
    {
        return self::CURRENCIES[$this->currency ?? 'USD'][0] ?? '$';
    }

    /** Format a cents amount in this event's currency, e.g. "$1,250" or "JD 1,250". */
    public function money(?int $cents, bool $withSpace = true): string
    {
        return self::moneyIn($cents, $this->currency ?? 'USD');
    }

    /** Format a cents amount in any supported currency code. */
    public static function moneyIn(?int $cents, string $currency): string
    {
        $symbol = self::CURRENCIES[$currency][0] ?? '$';
        $sep = strlen($symbol) > 1 ? ' ' : '';

        return $symbol.$sep.number_format(($cents ?? 0) / 100);
    }

    /** Number of calendar days the event spans (inclusive). */
    public function dayCount(): int
    {
        if (! $this->starts_at) {
            return 0;
        }

        return (int) $this->starts_at->diffInDays($this->ends_at ?? $this->starts_at) + 1;
    }

    /**
     * Ensure one agenda day exists per date in the event's [start, end] range.
     * Never overwrites custom labels or deletes days; re-sorts everything by date.
     * Capped so an accidental far-off end date can't spawn hundreds of days.
     */
    public function syncAgendaDays(int $cap = 60): void
    {
        if (! $this->starts_at) {
            return;
        }

        $start = $this->starts_at->copy()->startOfDay();
        $end = ($this->ends_at ?? $this->starts_at)->copy()->startOfDay();

        // ── Dedupe: one day per date. Keep the busiest; drop empty duplicates. ──
        $this->agendaDays()->withCount('sessions')->get()
            ->groupBy(fn ($d) => $d->date->format('Y-m-d'))
            ->each(function ($group) {
                if ($group->count() < 2) {
                    return;
                }
                $keep = $group->sortByDesc('sessions_count')->first();
                $group->where('id', '!=', $keep->id)->where('sessions_count', 0)->each->delete();
            });

        // ── Drop empty days that fall outside the event's date range (never touch
        //    a day that already holds sessions — that would lose work). ──
        $this->agendaDays()->withCount('sessions')->get()
            ->filter(fn ($d) => $d->sessions_count === 0 && ($d->date->lt($start) || $d->date->gt($end)))
            ->each->delete();

        // ── Create any missing in-range dates. ──
        $existing = $this->agendaDays()->get()->keyBy(fn ($d) => $d->date->format('Y-m-d'));
        $cursor = $start->copy();
        $n = 0;
        while ($cursor->lte($end) && $n < $cap) {
            $key = $cursor->format('Y-m-d');
            if (! $existing->has($key)) {
                $this->agendaDays()->create(['date' => $key, 'label' => 'Day '.($n + 1), 'sort' => $n + 1]);
            }
            $n++;
            $cursor->addDay();
        }

        // ── Renumber sort strictly by date so ordering can never drift again. ──
        $this->agendaDays()->orderBy('date')->orderBy('id')->get()
            ->each(fn ($day, $i) => $day->update(['sort' => $i + 1]));
    }

    /** Memoized computed health, so list rows don't recompute per render. */
    private ?array $healthMemo = null;

    /**
     * The three health colors (track / warn / risk) — always computed from the
     * health engine, never stored. Callers that already hold a breakdown should
     * pass its group instead of calling this.
     */
    public function healthGroup(): string
    {
        $this->healthMemo ??= app(EventHealthService::class)->breakdown($this);

        return $this->healthMemo['group'];
    }

    /**
     * The event color theme. Explicit colors win; otherwise brand defaults.
     */
    public function theme(): array
    {
        return [
            'primary' => $this->primary_color ?? '#0B1F3A',
            'secondary' => $this->secondary_color ?? '#F8FAFC',
            'accent' => $this->accent_color ?? '#D4AF37',
            'text' => $this->text_color ?? '#0F172A',
        ];
    }

    /** Public URL for the uploaded cover image, or null. */
    public function coverUrl(): ?string
    {
        return $this->cover_path ? asset($this->cover_path) : null;
    }

    /** Public URL for the uploaded logo, or null. */
    public function logoUrl(): ?string
    {
        return $this->logo_path ? asset($this->logo_path) : null;
    }

    /** The deal this event was won from, when it came through the pipeline. */
    public function deal(): HasOne
    {
        return $this->hasOne(Deal::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    /**
     * Who to call when something goes wrong on the ground — printed on driver
     * sheets and VIP sheets. The project manager by name, on the company's
     * operations number, because a personal mobile is not ours to hand out.
     *
     * @return array{name:?string,phone:?string}
     */
    public function controlContact(): array
    {
        $company = CompanyProfile::first();

        return [
            'name' => $this->projectManager?->name,
            'phone' => $company?->phone,
        ];
    }

    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function suppliers(): BelongsToMany
    {
        return $this->belongsToMany(Supplier::class)->withPivot(['status', 'notes']);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(EventRoom::class);
    }

    public function agendaDays(): HasMany
    {
        // Always chronological — the agenda reads by date, never by a stale sort.
        return $this->hasMany(EventAgendaDay::class)->orderBy('date')->orderBy('id');
    }

    public function agendaSessions(): HasMany
    {
        return $this->hasMany(EventAgendaSession::class);
    }

    public function budgetItems(): HasMany
    {
        return $this->hasMany(EventBudgetItem::class);
    }

    /**
     * The two sides of every line, added up: what the event costs, and what it
     * is being charged at.
     *
     * This is the number a quote is made of, and it is not the same as income.
     * Income is what has arrived; this is what the work is priced at. An event
     * can be fully priced and have collected nothing.
     *
     * With nothing priced by hand it reproduces what the budget always showed:
     * every unpriced line falls back to the management fee, and subtotal plus
     * fee is exactly what summing those lines gives (bar a cent or two of
     * per-line rounding).
     *
     * @return array{cost:int,sell:int,margin:int,marginPct:?int,fee:float,priced:int,absorbed:int,lines:int}
     */
    public function sellSummary(): array
    {
        $pct = (float) ($this->management_fee_pct ?? EventBudgetItem::DEFAULT_FEE_PCT);
        $items = $this->budgetItems;

        $cost = (int) $items->sum(fn (EventBudgetItem $i) => $i->costCents());
        $sell = (int) $items->sum(fn (EventBudgetItem $i) => $i->sellCents($pct));

        return [
            'cost' => $cost,
            'sell' => $sell,
            'margin' => $sell - $cost,
            // Against the charge, the way a P&L reads it. Null on nothing sold.
            'marginPct' => $sell > 0 ? (int) round(($sell - $cost) / $sell * 100) : null,
            'fee' => $pct,
            // How much of this was priced deliberately rather than by the fee —
            // the difference between a quote and a default.
            'priced' => $items->filter(fn (EventBudgetItem $i) => $i->sell_cents !== null || $i->markup_pct !== null)->count(),
            // Cost you are carrying yourself, on lines that are not billable.
            'absorbed' => (int) $items->reject(fn (EventBudgetItem $i) => $i->billable ?? true)
                ->sum(fn (EventBudgetItem $i) => $i->costCents()),
            'lines' => $items->count(),
        ];
    }

    public function budgetVersions(): HasMany
    {
        return $this->hasMany(EventBudgetVersion::class)->orderByDesc('version');
    }

    public function budgetCategories(): HasMany
    {
        return $this->hasMany(EventBudgetCategory::class)->orderBy('position')->orderBy('id');
    }

    /** Seed the default budget categories the first time the budget is opened. */
    public function ensureBudgetCategories(): void
    {
        if ($this->budgetCategories()->exists()) {
            return;
        }
        foreach (CompanyProfile::current()->budgetCategories() as $i => $name) {
            $this->budgetCategories()->create(['name' => $name, 'position' => $i]);
        }
    }

    /** Find (or create) a budget category by name — used by module sync. */
    public function budgetCategory(string $name): EventBudgetCategory
    {
        return $this->budgetCategories()->firstOrCreate(
            ['name' => $name],
            ['position' => (int) $this->budgetCategories()->max('position') + 1],
        );
    }

    /** The budget is locked once an approved baseline is in place. */
    public function budgetLocked(): bool
    {
        return $this->budget_status === 'approved';
    }

    /** The event's brief document (one per event). */
    public function brief(): HasOne
    {
        return $this->hasOne(EventBrief::class);
    }

    /** The client Management Services Agreement — the primary contract. */
    public function contract(): HasOne
    {
        return $this->hasOne(EventContract::class)->where('type', 'client');
    }

    /** Every generated contract and letter for this event, newest last. */
    public function contracts(): HasMany
    {
        return $this->hasMany(EventContract::class)->orderBy('id');
    }

    // ── Plan Studio ──────────────────────────────────────────
    public function planTracks(): HasMany
    {
        return $this->hasMany(PlanTrack::class)->orderBy('position')->orderBy('id');
    }

    public function planItems(): HasMany
    {
        return $this->hasMany(PlanItem::class)->orderBy('position')->orderBy('id');
    }

    /** Default tracks seeded on first use — [name, colour, goal]. Fully user-editable. */
    public const DEFAULT_TRACKS = [
        ['Initiation & Strategy', '#8B5CF6', 'Define the event foundation and obtain approvals.'],
        ['Planning & Design', '#3B82F6', 'Transform concept into a complete plan.'],
        ['Marketing & Registration', '#EC4899', 'Generate attendance and registrations.'],
        ['Pre-Event Preparation', '#06B6D4', 'Prepare operations for execution.'],
        ['Event Execution', '#D4AF37', 'Deliver the event successfully.'],
        ['Event Close-Out', '#10B981', 'Finalize all event operations.'],
        ['Post-Event & Legacy', '#F59E0B', 'Measure success and maximize long-term value.'],
    ];

    public function ensurePlanTracks(): void
    {
        if ($this->planTracks()->exists()) {
            return;
        }
        foreach (self::DEFAULT_TRACKS as $i => [$name, $color, $goal]) {
            $this->planTracks()->create(['name' => $name, 'goal' => $goal, 'color' => $color, 'position' => $i]);
        }
    }

    public function incomeItems(): HasMany
    {
        return $this->hasMany(EventIncomeItem::class);
    }

    /**
     * Income streams for the P&L — one source of truth shared by the Budget tab
     * and the Overview so the money story is identical on both. The client
     * CONTRACT is the source of truth for the client deal: collected = payments
     * booked against it, plus any manually logged client income.
     *
     * @return array{client:int,sponsors:int,exhibitors:int,other:int,total:int,collected:int,target:int}
     */
    public function incomeSummary(): array
    {
        $contract = $this->contract;
        $contractCollected = $contract ? (int) $contract->payments->sum('paid_cents') : 0;
        $contractValue = $contract
            ? (int) ($contract->data['financials']['contract_value_cents']
                ?? $contract->data['financials']['estimated_total_cents'] ?? 0)
            : 0;

        $items = $this->incomeItems;
        $manualClient = (int) $items->where('source', 'client')->sum('amount_cents');
        $other = (int) $items->where('source', '!=', 'client')->sum('amount_cents');
        $client = $manualClient + $contractCollected;
        $sponsors = (int) $this->sponsors->sum('amount_cents');
        $exhibitors = (int) $this->exhibitors->where('status', '!=', 'cancelled')->sum('fee_cents');

        $clientTarget = ($this->client_target_cents ?? 0) ?: $contractValue;
        $target = $clientTarget + (int) ($this->sponsorship_target_cents ?? 0)
            + (int) ($this->exhibition_target_cents ?? 0) + $other;

        return [
            'client' => $client,
            'sponsors' => $sponsors,
            'exhibitors' => $exhibitors,
            'other' => $other,
            'total' => $client + $sponsors + $exhibitors + $other,
            'collected' => $contractCollected + $manualClient
                + (int) $this->sponsors->sum('paid_cents') + (int) $this->exhibitors->sum('paid_cents') + $other,
            'target' => $target,
        ];
    }

    public function attendees(): HasMany
    {
        return $this->hasMany(EventAttendee::class);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Public registration
    // ══════════════════════════════════════════════════════════════════════

    /**
     * The token in the public link, made on first use.
     *
     * A token rather than the id, because this is the one URL a stranger is
     * meant to reach and it should not also tell them how many events you run.
     */
    public function registrationToken(): string
    {
        if (! $this->registration_token) {
            $this->forceFill(['registration_token' => Str::lower(Str::random(24))])->save();
        }

        return $this->registration_token;
    }

    public function registrationUrl(): string
    {
        return route('register.show', $this->registrationToken());
    }

    /** Everyone who has not withdrawn — what a capacity is measured against. */
    public function registeredCount(): int
    {
        return $this->attendees()->where('status', '!=', 'cancelled')->count();
    }

    public function registrationIsFull(): bool
    {
        return $this->registration_capacity !== null
            && $this->registeredCount() >= $this->registration_capacity;
    }

    /** Open, not full, and not an event that has already happened. */
    public function registrationIsLive(): bool
    {
        return (bool) $this->registration_open
            && ! $this->registrationIsFull()
            && ! $this->archived_at;
    }

    public function sponsors(): HasMany
    {
        return $this->hasMany(EventSponsor::class);
    }

    public function sponsorPackages(): HasMany
    {
        return $this->hasMany(EventSponsorPackage::class)->orderBy('position')->orderBy('id');
    }

    /** Default sponsorship packages [name => max slots] — seeded on first use (price 0, they set it). */
    public const DEFAULT_SPONSOR_PACKAGES = [
        'Strategic Partner' => 1,
        'Host Destination Partner' => 1,
        'Official Airline Partner' => 1,
        'Platinum Partner' => 3,
        'Gold Partner' => 5,
        'Silver Partner' => 10,
        'Official Media Partner' => 1,
        'Technology Partner' => 2,
        'VIP Lounge Partner' => 1,
    ];

    public function ensureSponsorPackages(): void
    {
        if ($this->sponsorPackages()->exists()) {
            return;
        }
        $i = 0;
        foreach (CompanyProfile::current()->sponsorPackages() as $pkg) {
            $this->sponsorPackages()->create([
                'name' => $pkg['name'],
                'price_cents' => (int) ($pkg['price_cents'] ?? 0),
                'slots' => $pkg['slots'] ?? null,
                'benefits' => $pkg['benefits'] ?? [],
                'position' => $i++,
            ]);
        }
    }

    public function speakers(): HasMany
    {
        return $this->hasMany(EventSpeaker::class)->orderBy('sort_order')->orderBy('id');
    }

    /** Total of the event-wide requirements (cents). */
    public function eventRequirementsTotalCents(): int
    {
        return collect($this->event_requirements ?? [])->sum(fn ($r) => (int) ($r['cost_cents'] ?? 0));
    }

    public function exhibitionHalls(): HasMany
    {
        return $this->hasMany(EventExhibitionHall::class)->orderBy('position')->orderBy('id');
    }

    /** Guarantee at least one hall exists; migrate legacy event-level fixtures into it. */
    public function auditLogs(): HasMany
    {
        return $this->hasMany(AuditLog::class)->latest('created_at');
    }

    public function booths(): HasMany
    {
        return $this->hasMany(EventBooth::class);
    }

    public function ensureExhibitionHall(): EventExhibitionHall
    {
        $hall = $this->exhibitionHalls()->first();
        if ($hall) {
            return $hall;
        }

        return $this->exhibitionHalls()->create([
            'name' => 'Exhibition Hall A',
            'width_m' => 30,
            'length_m' => 20,
            'position' => 0,
            'fixtures' => $this->exhibition_fixtures ?: null,
        ]);
    }

    public function exhibitors(): HasMany
    {
        return $this->hasMany(EventExhibitor::class);
    }

    public function transport(): HasMany
    {
        return $this->hasMany(EventTransport::class);
    }

    public function accommodations(): HasMany
    {
        return $this->hasMany(EventAccommodation::class);
    }

    /**
     * Everyone needing a transfer — the flight list. A guest with no
     * transport_id is still in the pool, waiting to be put on a vehicle.
     */
    public function transferGuests(): HasMany
    {
        return $this->hasMany(EventTransportPassenger::class);
    }

    public function roomBlocks(): HasMany
    {
        return $this->hasMany(EventRoomBlock::class)->orderBy('position')->orderBy('id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(EventDocument::class);
    }

    public function documentFolders(): HasMany
    {
        return $this->hasMany(EventDocumentFolder::class)->orderBy('position')->orderBy('name');
    }

    public function risks(): HasMany
    {
        return $this->hasMany(EventRisk::class);
    }

    public function approvals(): HasMany
    {
        return $this->hasMany(EventApproval::class);
    }

    public function teamMembers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_team_members')->withPivot('role');
    }

    public function favoritedBy(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_favorites');
    }
}
