<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'source_type', 'source_id', 'room_id', 'category', 'description', 'quantity', 'unit_cents', 'estimated_cents', 'approved_cents', 'actual_cents', 'paid_cents', 'sell_cents', 'markup_pct', 'billable', 'supplier_id', 'vendor', 'payment_status', 'flagged', 'invoice_number', 'due_on', 'notes'])]
class EventBudgetItem extends Model
{
    /**
     * Event cost taxonomy, structured guests-first the way we actually budget.
     * key => [label, group]. The 15% events-management fee is NOT a line here —
     * it is derived on top of the subtotal (see Event::management_fee_pct).
     */
    public const CATEGORY_META = [
        // Guests & Guest Services — the priority block
        'guest_accommodation' => ['Guest Accommodation', 'Guests & Guest Services'],
        'guest_flights' => ['Guest Flights', 'Guests & Guest Services'],
        'guest_transport' => ['Guest Transportation', 'Guests & Guest Services'],
        'guest_meals' => ['Guest Meals & Hospitality', 'Guests & Guest Services'],
        'giveaways' => ['Giveaways', 'Guests & Guest Services'],
        'tours' => ['Tours & Excursions', 'Guests & Guest Services'],
        'prizes' => ['Prizes & Awards', 'Guests & Guest Services'],
        // Programme
        'speakers' => ['Speakers & Program', 'Programme'],
        // Venue & Exhibition
        'venue' => ['Venue Hire', 'Venue & Exhibition'],
        'exhibition' => ['Exhibition', 'Venue & Exhibition'],
        // Catering (all attendees)
        'catering' => ['Food & Beverage', 'Catering'],
        // Content & Digital
        'website' => ['Website & Registration', 'Content & Digital'],
        'design' => ['Presentation, Design & Documents', 'Content & Digital'],
        // Marketing & Press
        'marketing' => ['Marketing & Advertising', 'Marketing & Press'],
        'press' => ['Press & Media', 'Marketing & Press'],
        // Logistics & Production
        'av' => ['AV, Hybrid & Streaming', 'Logistics & Production'],
        'crew' => ['Crew, Ushers & Operators', 'Logistics & Production'],
        'insurance' => ['Insurance & Permits', 'Logistics & Production'],
        'decor' => ['Decoration & Floral', 'Logistics & Production'],
        'stationery' => ['Stamps, Invitations & Print', 'Logistics & Production'],
        'logistics' => ['Other Logistics', 'Logistics & Production'],
        'misc' => ['Miscellaneous', 'Logistics & Production'],
        // Documentation
        'documentation' => ['Photography, Video & Voice-over', 'Documentation'],
    ];

    /**
     * The default budget categories, in display order. These are seeded per
     * event (see Event::ensureBudgetCategories) and are fully user-editable —
     * they can rename, reorder, add and remove categories from the Budget tab.
     * A line links to its category by name (the `category` column).
     */
    public const DEFAULT_CATEGORIES = [
        'Attendee & Guest Services',
        'Venues',
        'Registration',
        'Food & Beverage',
        'Media',
        'Other',
        'Event Documentation',
        'Logistics',
    ];

    /** Ordered group list for the ledger. */
    public const GROUPS = [
        'Guests & Guest Services', 'Programme', 'Venue & Exhibition', 'Catering',
        'Content & Digital', 'Marketing & Press', 'Logistics & Production', 'Documentation',
    ];

    public const CATEGORIES = [
        'guest_accommodation', 'guest_flights', 'guest_transport', 'guest_meals', 'giveaways', 'tours', 'prizes',
        'speakers', 'venue', 'exhibition', 'catering', 'website', 'design', 'marketing', 'press',
        'av', 'crew', 'insurance', 'decor', 'stationery', 'logistics', 'misc', 'documentation',
    ];

    /** Module a linked line is synced from (or null for a manual line). */
    public function isLinked(): bool
    {
        return $this->source_type !== null;
    }

    public function linkedTab(): string
    {
        return match ($this->source_type) {
            'accommodation' => 'accommodation',
            'transport' => 'transportation',
            'speaker' => 'speakers',
            'room' => 'venue',
            'event_req' => 'venue',
            default => 'budget',
        };
    }

    public const PAYMENT_STATUSES = ['pending', 'partial', 'paid'];

    /** Default events-management company fee (% of subtotal). */
    public const DEFAULT_FEE_PCT = 15.0;

    /**
     * Reusable skeleton that GENERATES the first budget to study — common line
     * names under the default categories, amounts at 0, ready to fill & track.
     */
    public const STARTER_TEMPLATE = [
        ['Attendee & Guest Services', 'Speaker & VIP hotel rooms'],
        ['Attendee & Guest Services', 'Delegate accommodation'],
        ['Attendee & Guest Services', 'Flights & travel'],
        ['Attendee & Guest Services', 'Airport transfers & shuttles'],
        ['Attendee & Guest Services', 'Gifts & giveaways'],
        ['Attendee & Guest Services', 'Tours & excursions'],
        ['Venues', 'Main hall / plenary (per day)'],
        ['Venues', 'Breakout & meeting rooms'],
        ['Venues', 'Exhibition floor build'],
        ['Registration', 'Registration system & app'],
        ['Registration', 'Event website'],
        ['Food & Beverage', 'Delegate lunches'],
        ['Food & Beverage', 'Coffee breaks & refreshments'],
        ['Food & Beverage', 'Gala / VIP dinner'],
        ['Media', 'Advertising & media buy'],
        ['Media', 'Press conference & media relations'],
        ['Media', 'Branding, creative & print design'],
        ['Event Documentation', 'Photography & videography'],
        ['Event Documentation', 'Video production & voice-over'],
        ['Logistics', 'Sound, screens & lighting'],
        ['Logistics', 'Hybrid streaming & operators'],
        ['Logistics', 'Ushers, hostesses & crew'],
        ['Logistics', 'Insurance & permits'],
        ['Logistics', 'Decoration & floral'],
        ['Logistics', 'Stamps, invitations & mailing'],
        ['Other', 'Contingency & miscellaneous'],
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'integer',
            'unit_cents' => 'integer',
            'estimated_cents' => 'integer',
            'approved_cents' => 'integer',
            'actual_cents' => 'integer',
            'paid_cents' => 'integer',
            'sell_cents' => 'integer',
            'markup_pct' => 'float',
            'billable' => 'boolean',
            'flagged' => 'boolean',
            'due_on' => 'date',
        ];
    }

    /** The category is now a free-text name; fall back to the legacy label map. */
    public function categoryLabel(): string
    {
        return self::CATEGORY_META[$this->category][0] ?? ($this->category ?: 'Uncategorised');
    }

    public function categoryGroup(): string
    {
        return self::CATEGORY_META[$this->category][1] ?? 'Overheads';
    }

    // ══════════════════════════════════════════════════════════════════════
    //  Cost, charge, margin
    //
    //  Cost is what this line takes out of the business; charge is what it
    //  puts back in. Everything else here is those two subtracted.
    // ══════════════════════════════════════════════════════════════════════

    /**
     * What this line costs: the actual once it is known, the estimate until then.
     *
     * Same rule the Budget tab and the portfolio have always used, named once
     * so the three of them cannot drift.
     */
    public function costCents(): int
    {
        return (int) ($this->actual_cents ?: $this->estimated_cents);
    }

    /**
     * What the client is charged for this line.
     *
     * Three ways of saying it, in the order they win:
     *   1. a price typed by hand — "we quoted 12,000 for this"
     *   2. a markup on cost — "this one is cost plus 20%"
     *   3. nothing said, so the event's management fee applies, which is what
     *      the platform did for every line before any of this existed
     *
     * A line that is not billable charges nothing. It still costs.
     */
    public function sellCents(?float $defaultFeePct = null): int
    {
        if (! ($this->billable ?? true)) {
            return 0;
        }

        if ($this->sell_cents !== null) {
            return (int) $this->sell_cents;
        }

        $pct = $this->markup_pct ?? $defaultFeePct ?? $this->event?->management_fee_pct ?? self::DEFAULT_FEE_PCT;

        return (int) round($this->costCents() * (1 + $pct / 100));
    }

    /** What the line earns. Negative means it is being sold below cost. */
    public function marginCents(?float $defaultFeePct = null): int
    {
        return $this->sellCents($defaultFeePct) - $this->costCents();
    }

    /**
     * Margin as a share of the charge, the way a P&L reads it.
     *
     * Null rather than zero when nothing is charged: a line you absorb has no
     * margin to speak of, and calling that 0% would put it in the same bucket
     * as one sold at exactly cost.
     */
    public function marginPct(?float $defaultFeePct = null): ?int
    {
        $sell = $this->sellCents($defaultFeePct);

        return $sell > 0 ? (int) round($this->marginCents($defaultFeePct) / $sell * 100) : null;
    }

    /** Outstanding = actual (or estimated if no actual) minus paid. */
    public function outstandingCents(): int
    {
        $due = $this->actual_cents ?: $this->estimated_cents;

        return max(0, $due - $this->paid_cents);
    }

    /** Derive payment status from paid vs the amount due. */
    public function derivePaymentStatus(): string
    {
        $due = $this->actual_cents ?: $this->estimated_cents;
        if ($due > 0 && $this->paid_cents >= $due) {
            return 'paid';
        }

        return $this->paid_cents > 0 ? 'partial' : 'pending';
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(EventRoom::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
