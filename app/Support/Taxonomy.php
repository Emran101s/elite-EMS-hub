<?php

namespace App\Support;

use App\Models\ContractSignatory;
use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Event;
use App\Models\EventAccommodation;
use App\Models\EventAgendaSession;
use App\Models\EventApproval;
use App\Models\EventExhibitor;
use App\Models\EventIncomeItem;
use App\Models\EventRisk;
use App\Models\EventRoom;
use App\Models\EventScopeItem;
use App\Models\EventTransportPassenger;
use App\Models\ServiceItem;
use App\Models\Supplier;
use App\Models\Task;
use App\Models\TaxonomyTerm;
use App\Models\Venue;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The platform's editable lists.
 *
 * Every one of these was a PHP constant, so adding an event type your company
 * actually runs meant editing code. The constants are still here as the seed
 * and the fallback — if the table is empty, or a value was written before the
 * list was editable, the code still resolves it. Nothing depends on the table
 * existing, which is what makes this safe to introduce over live data.
 *
 * `stores` is the load-bearing bit. Some lists were written as machine keys
 * (`gala_dinner`) and some as the words themselves (`Referral`, `Deluxe`).
 * A term's key must match whatever the existing records hold, or those records
 * quietly stop resolving — so a new term derives its key the same way the list
 * it joins always has.
 *
 * What is NOT here, on purpose:
 *   - Workflow states (stages, statuses). Deleting "Done" breaks code; those
 *     are relabelled and recoloured on their own screen, never removed.
 *   - Budget categories and ticket types, which Defaults & Templates owns.
 *   - Seating arrangements and room equipment, which are a geometry engine and
 *     a priced catalogue respectively, not vocabulary.
 */
class Taxonomy
{
    /**
     * Every editable list.
     *
     *   label   what it is called on screen
     *   group   which section of the picker it sits in
     *   note    what it drives, in one line
     *   color   whether a colour means anything for this list
     *   stores  'key' (records hold gala_dinner) | 'label' (records hold the words)
     *   on      [table, column] the records that use it live in
     *
     * @var array<string,array{label:string,group:string,note:string,color:bool,stores:string,on:array{0:string,1:string}}>
     */
    public const LISTS = [
        // ── Events ────────────────────────────────────────────────────────
        'event_type' => [
            'label' => 'Event types', 'group' => 'Events',
            'note' => 'The kinds of event you run — chosen when an event is created and shown on its crest.',
            'color' => false, 'stores' => 'key', 'on' => ['events', 'type'],
        ],
        'approval_type' => [
            'label' => 'Approval types', 'group' => 'Events',
            'note' => 'What can be sent for sign-off on an event.',
            'color' => false, 'stores' => 'key', 'on' => ['event_approvals', 'type'],
        ],
        'risk_category' => [
            'label' => 'Risk categories', 'group' => 'Events',
            'note' => 'How risks are grouped on an event risk register.',
            'color' => false, 'stores' => 'key', 'on' => ['event_risks', 'category'],
        ],
        'task_area' => [
            'label' => 'Task areas', 'group' => 'Events',
            'note' => 'The part of the operation a task belongs to.',
            'color' => true, 'stores' => 'key', 'on' => ['tasks', 'area'],
        ],
        'scope_type' => [
            'label' => 'Scope types', 'group' => 'Events',
            'note' => 'How an event\'s Scope of Work is grouped — the kinds of service a client asks for.',
            'color' => true, 'stores' => 'key', 'on' => ['event_scope_items', 'type'],
        ],

        // ── Programme ─────────────────────────────────────────────────────
        'session_type' => [
            'label' => 'Session types', 'group' => 'Programme',
            'note' => 'Offered when you add a session to a programme.',
            'color' => false, 'stores' => 'key', 'on' => ['event_agenda_sessions', 'type'],
        ],
        'session_format' => [
            'label' => 'Session formats', 'group' => 'Programme',
            'note' => 'How a session is delivered — in the room, online, or both.',
            'color' => false, 'stores' => 'key', 'on' => ['event_agenda_sessions', 'format'],
        ],
        'speaker_role' => [
            'label' => 'Speaker roles', 'group' => 'Programme',
            'note' => 'What a speaker is doing in a session.',
            'color' => false, 'stores' => 'key', 'on' => ['agenda_session_speaker', 'role'],
        ],
        'exhibitor_package' => [
            'label' => 'Exhibitor packages', 'group' => 'Programme',
            'note' => 'The stand packages you sell on an exhibition floor.',
            'color' => false, 'stores' => 'key', 'on' => ['event_exhibitors', 'package'],
        ],

        // ── Places & rooms ────────────────────────────────────────────────
        'venue_type' => [
            'label' => 'Venue types', 'group' => 'Places & rooms',
            'note' => 'How the venue directory is classified — hotels included.',
            'color' => false, 'stores' => 'label', 'on' => ['venues', 'type'],
        ],
        'venue_room_type' => [
            'label' => 'Room types', 'group' => 'Places & rooms',
            'note' => 'What a room inside a venue is for — main hall, breakout, registration.',
            'color' => false, 'stores' => 'key', 'on' => ['event_rooms', 'type'],
        ],
        'room_category' => [
            'label' => 'Bedroom grades', 'group' => 'Places & rooms',
            'note' => 'The room grades offered when you build an accommodation block.',
            'color' => false, 'stores' => 'label', 'on' => ['event_accommodations', 'room_type'],
        ],

        // ── People ────────────────────────────────────────────────────────
        'team_role' => [
            'label' => 'Team roles', 'group' => 'People',
            'note' => 'What someone is on an event team.',
            'color' => false, 'stores' => 'key', 'on' => ['event_team_members', 'role'],
        ],
        'signatory_role' => [
            'label' => 'Signatory roles', 'group' => 'People',
            'note' => 'Who signs a contract, and the wording above their line.',
            'color' => false, 'stores' => 'key', 'on' => ['contract_signatories', 'role'],
        ],
        'passenger_category' => [
            'label' => 'Passenger categories', 'group' => 'People',
            'note' => 'Who is being moved. VIP and Speaker also pull a movement up the priority order.',
            'color' => true, 'stores' => 'key', 'on' => ['event_transport_passengers', 'category'],
        ],

        // ── Money & suppliers ─────────────────────────────────────────────
        'income_source' => [
            'label' => 'Income sources', 'group' => 'Money & suppliers',
            'note' => 'Where money coming in is booked against on every event.',
            'color' => true, 'stores' => 'key', 'on' => ['event_income_items', 'source'],
        ],
        'service_section' => [
            'label' => 'Price list sections', 'group' => 'Money & suppliers',
            'note' => 'Where a service comes from — what the hotel provides, what is brought in from outside, what is rented. Each section has its own tab and its own import.',
            'color' => false, 'stores' => 'key', 'on' => ['service_items', 'section'],
        ],
        'supplier_category' => [
            'label' => 'Supplier categories', 'group' => 'Money & suppliers',
            'note' => 'How the supplier directory is organised.',
            'color' => false, 'stores' => 'key', 'on' => ['suppliers', 'category'],
        ],
        'deal_source' => [
            'label' => 'Deal sources', 'group' => 'Money & suppliers',
            'note' => 'Where an opportunity came from — this is what tells you which channels bring work in.',
            'color' => false, 'stores' => 'label', 'on' => ['deals', 'source'],
        ],
        'activity_type' => [
            'label' => 'Activity types', 'group' => 'Money & suppliers',
            'note' => 'The kinds of contact you log against a client or a deal.',
            'color' => false, 'stores' => 'key', 'on' => ['deal_activities', 'type'],
        ],
    ];

    /** In-request memo: these are read on nearly every screen. */
    private static array $memo = [];

    /** One piece of a list's metadata, with a sane answer for an unknown list. */
    public static function meta(string $taxonomy, string $field): mixed
    {
        return self::LISTS[$taxonomy][$field] ?? match ($field) {
            'label' => str($taxonomy)->headline()->toString(),
            'group' => 'Other',
            'note' => '',
            'color' => false,
            'stores' => 'key',
            default => null,
        };
    }

    /** The lists arranged as the picker shows them. */
    public static function groups(): Collection
    {
        return collect(self::LISTS)->map(fn ($m, $k) => $m + ['key' => $k])->groupBy('group');
    }

    /** The seed for each list, taken from the constant it replaced. */
    public static function defaults(string $taxonomy): array
    {
        return match ($taxonomy) {
            'event_type' => self::fromKeys(Event::TYPES),
            'approval_type' => self::fromKeys(EventApproval::TYPES),
            'risk_category' => self::fromKeys(EventRisk::CATEGORIES),
            'task_area' => self::fromKeys(Task::AREAS),
            // defaults() is key => label; the colour lives on the term row,
            // and EventScopeItem::TYPES carries the seed colour for it.
            'scope_type' => collect(EventScopeItem::TYPES)->map(fn (array $t) => $t[0])->all(),

            'session_type' => self::fromKeys(EventAgendaSession::TYPES),
            'session_format' => EventAgendaSession::FORMATS,
            'speaker_role' => EventAgendaSession::SPEAKER_ROLES,
            'exhibitor_package' => self::fromKeys(EventExhibitor::PACKAGES),

            'venue_type' => self::fromWords(Venue::TYPES),
            'venue_room_type' => self::fromKeys(EventRoom::TYPES),
            'room_category' => self::fromWords(EventAccommodation::CATEGORIES),

            'team_role' => Event::TEAM_ROLE_LABELS,
            'signatory_role' => ContractSignatory::ROLES,
            'passenger_category' => EventTransportPassenger::CATEGORIES,

            'income_source' => EventIncomeItem::SOURCES,
            'service_section' => ServiceItem::SECTIONS,
            'supplier_category' => collect(Supplier::CATEGORIES)
                ->mapWithKeys(fn (string $c) => [$c => str($c)->replace('_', ' & ')->title()->toString()])->all(),
            'deal_source' => self::fromWords(Deal::SOURCES),
            'activity_type' => collect(DealActivity::TYPES)
                ->mapWithKeys(fn (array $meta, string $k) => [$k => $meta[0]])->all(),

            default => [],
        };
    }

    /** A list stored as machine keys: `gala_dinner` => `Gala Dinner`. */
    private static function fromKeys(array $keys): array
    {
        return collect($keys)
            ->mapWithKeys(fn (string $k) => [$k => str($k)->replace('_', ' ')->title()->toString()])->all();
    }

    /** A list stored as the words themselves: `Referral` => `Referral`. */
    private static function fromWords(array $words): array
    {
        return collect($words)->mapWithKeys(fn (string $w) => [$w => $w])->all();
    }

    /**
     * The key a new term gets, derived the way the list it joins was written.
     * Once set it is frozen — records store it.
     */
    public static function deriveKey(string $taxonomy, string $label): string
    {
        $label = trim($label);

        return self::meta($taxonomy, 'stores') === 'label'
            ? $label
            : str($label)->snake()->toString();
    }

    /** Every term in a list, active ones only unless asked otherwise. */
    public static function terms(string $taxonomy, bool $withInactive = false): Collection
    {
        $cacheKey = $taxonomy.($withInactive ? ':all' : ':active');

        return self::$memo[$cacheKey] ??= TaxonomyTerm::query()
            ->in($taxonomy)
            ->when(! $withInactive, fn ($q) => $q->active())
            ->get();
    }

    /**
     * key => label, ready for a select. Falls back to the constant while the
     * table is empty, so a fresh install and a seeded one behave the same.
     *
     * Ordered as the list is displayed: each parent followed by its children.
     * Hiding a parent hides its branch — a sub-category of something you no
     * longer offer is not something you offer either. label() still resolves
     * every one of them, so records keep reading correctly.
     */
    public static function options(string $taxonomy): array
    {
        return collect(self::optionRows($taxonomy))->pluck('label', 'key')->all();
    }

    /** Just the values, for a free-text field's datalist. */
    public static function values(string $taxonomy): array
    {
        return array_keys(self::options($taxonomy));
    }

    /** Just the labels, for a datalist on a field that stores a prettified key. */
    public static function labels(string $taxonomy): array
    {
        return array_values(self::options($taxonomy));
    }

    /**
     * The list walked in display order, parents followed by their children.
     *
     * This is what a <select> renders from when a list has sub-categories:
     * `depth` says how far to indent, and a parent is still selectable — you
     * can book against Production without saying which part of it.
     *
     * @return array<int,array{key:string,label:string,depth:int,color:?string}>
     */
    public static function optionRows(string $taxonomy, bool $withInactive = false): array
    {
        $terms = self::terms($taxonomy, $withInactive);

        if ($terms->isEmpty()) {
            return collect(self::defaults($taxonomy))
                ->map(fn ($label, $key) => ['key' => (string) $key, 'label' => $label, 'depth' => 0, 'color' => null])
                ->values()->all();
        }

        $byParent = $terms->groupBy('parent_id');
        $rows = [];

        foreach ($byParent->get('', $byParent->get(null, collect())) as $root) {
            $rows[] = ['key' => $root->key, 'label' => $root->label, 'depth' => 0, 'color' => $root->color];

            foreach ($byParent->get($root->id, collect()) as $child) {
                $rows[] = ['key' => $child->key, 'label' => $child->label, 'depth' => 1, 'color' => $child->color ?: $root->color];
            }
        }

        return $rows;
    }

    /**
     * The label for one key. A key that has been deactivated or predates the
     * list still resolves — records keep their history either way.
     */
    public static function label(string $taxonomy, ?string $key): string
    {
        if (! $key) {
            return '—';
        }

        return self::terms($taxonomy, withInactive: true)->firstWhere('key', $key)?->label
            ?? self::defaults($taxonomy)[$key]
            ?? str($key)->replace('_', ' ')->title()->toString();
    }

    /**
     * How many records are on each term right now, in one grouped query.
     *
     * This is the number that makes hiding or deleting a term a decision
     * rather than a gamble — you can see what it is carrying before you touch
     * it. It counts the column directly, so it also catches values written
     * before the list existed.
     *
     * @return array<string,int> key => count
     */
    public static function usage(string $taxonomy): array
    {
        [$table, $column] = self::LISTS[$taxonomy]['on'] ?? [null, null];

        if (! $table || ! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
            return [];
        }

        return self::$memo['usage:'.$taxonomy] ??= DB::table($table)
            ->select($column, DB::raw('count(*) as n'))
            ->whereNotNull($column)
            ->groupBy($column)
            ->pluck('n', $column)
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** Fill any list that has no terms yet from its constant. Idempotent. */
    public static function seed(): int
    {
        $made = 0;

        foreach (array_keys(self::LISTS) as $taxonomy) {
            $position = 0;

            foreach (self::defaults($taxonomy) as $key => $label) {
                $term = TaxonomyTerm::firstOrCreate(
                    ['taxonomy' => $taxonomy, 'key' => (string) $key],
                    ['label' => $label, 'position' => $position, 'is_system' => true],
                );

                $made += $term->wasRecentlyCreated ? 1 : 0;
                $position++;
            }
        }

        self::$memo = [];

        return $made;
    }

    /**
     * Give a term to every value the data is already using.
     *
     * The constants these lists came from had drifted from reality: suppliers
     * were filed under thirteen categories `Supplier::CATEGORIES` never listed.
     * A value with no term is invisible in Settings, uncounted, and unprotected
     * by the guard that stops you deleting something in use — so it gets one,
     * added at the end and marked non-system, because the platform's own code
     * does not name it. The data does.
     *
     * Only ever adds. A value nothing uses is not invented, and a term that
     * already exists is not touched.
     */
    public static function adopt(): int
    {
        $made = 0;

        foreach (self::LISTS as $taxonomy => $meta) {
            [$table, $column] = $meta['on'];

            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $known = TaxonomyTerm::in($taxonomy)->pluck('key')->all();
            $position = (int) TaxonomyTerm::in($taxonomy)->max('position');

            $inUse = DB::table($table)->distinct()
                ->whereNotNull($column)->where($column, '!=', '')
                ->pluck($column)->map(fn ($v) => (string) $v);

            foreach ($inUse->diff($known) as $key) {
                TaxonomyTerm::create([
                    'taxonomy' => $taxonomy,
                    'key' => $key,
                    'label' => str($key)->replace('_', ' ')->title()->toString(),
                    'position' => ++$position,
                    'is_system' => false,
                ]);

                $made++;
            }
        }

        self::$memo = [];

        return $made;
    }

    public static function forget(): void
    {
        self::$memo = [];
    }
}
