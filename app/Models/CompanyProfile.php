<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'logo_path', 'default_currency', 'default_timezone',
    'default_budget_categories', 'default_management_fee_pct', 'default_ticket_types', 'default_sponsor_packages',
    'country', 'city', 'email', 'phone', 'website', 'address', 'bank_accounts',
])]
class CompanyProfile extends Model
{
    /**
     * The house currency and the house fee, named once.
     *
     * Every document used to fall back to a hardcoded 'JOD', so a company
     * working in dollars had to correct the currency on every invoice it ever
     * raised, and the one place it is configured was the one place nothing
     * read. Memoised because this is a single-row table read several times a
     * request, and re-queried after a save so Settings takes effect at once.
     */
    private static ?self $house = null;

    public static function house(): ?self
    {
        return self::$house ??= static::query()->first();
    }

    public static function forgetHouse(): void
    {
        self::$house = null;
    }

    /** Settings → Company → default currency. */
    public static function currency(): string
    {
        return self::house()?->default_currency ?: 'JOD';
    }

    /** Settings → Company → default management fee. */
    public static function feePct(): float
    {
        return (float) (self::house()?->default_management_fee_pct ?? 15.0);
    }

    /** The fields one bank account holds, in the order a document prints them. */
    public const BANK_FIELDS = [
        'label' => 'Heading',
        'account_name' => 'Account Name',
        'bank_name' => 'Bank Name',
        'account_no' => 'Account No.',
        'iban' => 'IBAN',
        'swift' => 'Swift Code',
        'currency' => 'Currency',
    ];

    /**
     * Where clients send money — printed at the foot of every invoice.
     *
     * Kept here rather than typed onto each document: a bank changes once and
     * every invoice raised afterwards should be right, including the ones
     * nobody thought to check.
     *
     * Rows with no account number and no IBAN are dropped rather than printed
     * as an empty box, which is what a half-filled row would otherwise become
     * on a document that has already left the building.
     *
     * @return list<array<string,string>>
     */
    public static function bankAccounts(): array
    {
        return collect(self::house()?->bank_accounts ?? [])
            ->map(fn ($row) => collect(self::BANK_FIELDS)
                ->mapWithKeys(fn (string $_, string $key) => [$key => trim((string) ($row[$key] ?? ''))])->all())
            ->filter(fn (array $row) => $row['account_no'] !== '' || $row['iban'] !== '')
            ->values()->all();
    }

    protected static function booted(): void
    {
        // A saved profile is a new house; the next read must not be the old one.
        static::saved(fn () => self::forgetHouse());
    }

    public const DEFAULT_TICKET_TYPES = ['Delegate', 'VIP', 'Speaker', 'Exhibitor', 'Press', 'Student'];

    protected function casts(): array
    {
        return [
            'bank_accounts' => 'array',
            'default_budget_categories' => 'array',
            'default_ticket_types' => 'array',
            'default_sponsor_packages' => 'array',
            'default_management_fee_pct' => 'float',
        ];
    }

    /**
     * Default sponsorship packages for new events: [{name, price_cents, slots, benefits[]}].
     * Falls back to the built-in tier list (price 0, no benefits).
     */
    public function sponsorPackages(): array
    {
        $list = collect($this->default_sponsor_packages ?? [])
            ->filter(fn ($p) => trim((string) ($p['name'] ?? '')) !== '')
            ->map(fn ($p) => [
                'name' => trim((string) $p['name']),
                'price_cents' => (int) ($p['price_cents'] ?? 0),
                'slots' => ($p['slots'] ?? null) !== null && $p['slots'] !== '' ? (int) $p['slots'] : null,
                'benefits' => array_values(array_filter(array_map('trim', (array) ($p['benefits'] ?? [])))),
            ])->values()->all();

        if ($list) {
            return $list;
        }

        return collect(Event::DEFAULT_SPONSOR_PACKAGES)
            ->map(fn ($slots, $name) => ['name' => $name, 'price_cents' => 0, 'slots' => $slots, 'benefits' => []])
            ->values()->all();
    }

    /** Ticket types offered across events (falls back to the built-in list). */
    public function ticketTypes(): array
    {
        $list = collect($this->default_ticket_types ?? [])
            ->map(fn ($n) => trim((string) $n))->filter()->values()->all();

        return $list ?: self::DEFAULT_TICKET_TYPES;
    }

    /** Default budget categories for new events (falls back to the built-in list). */
    public function budgetCategories(): array
    {
        $list = collect($this->default_budget_categories ?? [])
            ->map(fn ($n) => trim((string) $n))->filter()->values()->all();

        return $list ?: EventBudgetItem::DEFAULT_CATEGORIES;
    }

    /** Common currencies for the workspace default. */
    public const CURRENCIES = ['USD', 'EUR', 'GBP', 'JOD', 'AED', 'SAR', 'QAR', 'BHD', 'KWD', 'EGP'];

    public const TIMEZONES = ['UTC', 'Asia/Amman', 'Asia/Dubai', 'Asia/Riyadh', 'Asia/Qatar', 'Asia/Bahrain', 'Asia/Kuwait', 'Africa/Cairo', 'Europe/London', 'America/New_York'];

    /** The single workspace profile row (created with defaults on first use). */
    public static function current(): self
    {
        return static::firstOrCreate(['id' => 1], [
            'name' => 'Elite Business Hub',
            'default_currency' => 'USD',
            'default_timezone' => 'Asia/Amman',
        ]);
    }

    public function initials(): string
    {
        return str($this->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
