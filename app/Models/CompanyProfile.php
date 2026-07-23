<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'name', 'logo_path', 'default_currency', 'default_timezone',
    'default_budget_categories', 'default_management_fee_pct', 'default_ticket_types', 'default_sponsor_packages',
    'country', 'city', 'email', 'phone', 'website', 'address',
])]
class CompanyProfile extends Model
{
    public const DEFAULT_TICKET_TYPES = ['Delegate', 'VIP', 'Speaker', 'Exhibitor', 'Press', 'Student'];

    protected function casts(): array
    {
        return [
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
