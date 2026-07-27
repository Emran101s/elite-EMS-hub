<?php

namespace App\Support;

use App\Models\Deal;
use App\Models\DealActivity;
use App\Models\Event;
use App\Models\EventAccommodation;
use App\Models\EventAgendaSession;
use App\Models\EventRisk;
use App\Models\Supplier;
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
 */
class Taxonomy
{
    /**
     * taxonomy => [label, what it drives, colour is meaningful, how records
     *              store it, [table, column] the records that use it live in]
     *
     * @var array<string,array{0:string,1:string,2:bool,3:'key'|'label',4:array{0:string,1:string}}>
     */
    public const LISTS = [
        'event_type' => ['Event types', 'The kinds of event you run — chosen when an event is created and shown on its crest.', false, 'key', ['events', 'type']],
        'session_type' => ['Agenda session types', 'Offered when you add a session to a programme.', false, 'label', ['event_agenda_sessions', 'type']],
        'venue_type' => ['Venue types', 'How the venue directory is classified.', false, 'label', ['venues', 'type']],
        'room_category' => ['Room categories', 'The room grades offered when you build an accommodation block.', false, 'label', ['event_accommodations', 'room_type']],
        'supplier_category' => ['Supplier categories', 'How the supplier directory is organised.', false, 'key', ['suppliers', 'category']],
        'risk_category' => ['Risk categories', 'How risks are grouped on an event risk register.', false, 'key', ['event_risks', 'category']],
        'deal_source' => ['Deal sources', 'Where an opportunity came from — this is what tells you which channels bring work in.', false, 'label', ['deals', 'source']],
        'activity_type' => ['Activity types', 'The kinds of contact you log against a client or a deal.', false, 'key', ['deal_activities', 'type']],
    ];

    /** In-request memo: these are read on nearly every screen. */
    private static array $memo = [];

    /** The seed for each list, taken from the constant it replaced. */
    public static function defaults(string $taxonomy): array
    {
        return match ($taxonomy) {
            'event_type' => self::fromKeys(Event::TYPES),
            'session_type' => self::fromWords(collect(EventAgendaSession::TYPES)
                ->map(fn (string $t) => str($t)->replace('_', ' ')->title()->toString())->all()),
            'venue_type' => self::fromWords(Venue::TYPES),
            'room_category' => self::fromWords(EventAccommodation::CATEGORIES),
            'supplier_category' => collect(Supplier::CATEGORIES)
                ->mapWithKeys(fn (string $c) => [$c => str($c)->replace('_', ' & ')->title()->toString()])->all(),
            'risk_category' => self::fromKeys(EventRisk::CATEGORIES),
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

        return (self::LISTS[$taxonomy][3] ?? 'key') === 'label'
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
        [$table, $column] = self::LISTS[$taxonomy][4] ?? [null, null];

        if (! $table || ! Schema::hasTable($table)) {
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

    public static function color(string $taxonomy, ?string $key): ?string
    {
        return $key ? self::terms($taxonomy, withInactive: true)->firstWhere('key', $key)?->color : null;
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

    public static function forget(): void
    {
        self::$memo = [];
    }
}
