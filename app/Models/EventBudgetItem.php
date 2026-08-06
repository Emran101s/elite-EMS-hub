<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['tenant_id',
    'event_id', 'source_type', 'source_id', 'room_id', 'category', 'description', 'quantity', 'unit_cents', 'estimated_cents', 'approved_cents', 'actual_cents', 'paid_cents', 'sell_cents', 'markup_pct', 'billable', 'supplier_id', 'vendor', 'payment_status', 'flagged', 'invoice_number', 'due_on', 'notes'])]
class EventBudgetItem extends Model
{
    use BelongsToTenant;
    use SoftDeletes;

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
        return self::SOURCES[$this->source_type][1] ?? 'budget';
    }

    /** What the module a linked line came from is called. */
    public function linkedModule(): string
    {
        return self::SOURCES[$this->source_type][0] ?? 'A module';
    }

    /** source_type => [module name, the tab it lives on] */
    public const SOURCES = [
        'room_block' => ['Stay', 'accommodation'],
        'accommodation' => ['Stay', 'accommodation'],
        'transport' => ['Transport', 'transportation'],
        'speaker' => ['Speakers', 'speakers'],
        'room' => ['Venue', 'venue'],
        'event_req' => ['Venue', 'venue'],
    ];

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
     * Whether a real cost has been recorded, as opposed to being zero.
     *
     * These are different facts and the column used to be unable to tell them
     * apart: NULL is "not costed yet", 0 is "costed, at nothing" — a venue a
     * sponsor comped, a line a supplier waived. Every reader goes through here
     * rather than testing the column, because `?:` gets it wrong and reads so
     * naturally that it did for a year.
     */
    public function hasActual(): bool
    {
        return $this->actual_cents !== null;
    }

    /**
     * What this line costs: the actual once it is known, the estimate until then.
     *
     * Named once so the tab, the PDF and the portfolio cannot drift.
     */
    public function costCents(): int
    {
        return (int) ($this->actual_cents ?? $this->estimated_cents);
    }

    /**
     * What the line was expected to cost, against what it did.
     *
     * Null until a cost is recorded: an uncosted line has not saved or overspent
     * anything yet, and counting it as zero variance dilutes the average of the
     * lines that have.
     */
    public function varianceCents(): ?int
    {
        return $this->hasActual() ? $this->estimated_cents - (int) $this->actual_cents : null;
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
        return $this->sellCentsOn($this->costCents(), $defaultFeePct);
    }

    /**
     * The same rule, priced against a cost you name.
     *
     * The Budget tab needs the charge on the ESTIMATE for its budget column and
     * on the ACTUAL for its actual column; without this it fell back to a flat
     * percentage of the subtotal, which ignores hand-typed prices, per-line
     * markups and non-billable lines — and so disagreed with the Finance page
     * about the same event by tens of thousands.
     *
     * A quoted price does not move with cost. That is what quoting one means.
     */
    public function sellCentsOn(int $cost, ?float $defaultFeePct = null): int
    {
        if (! ($this->billable ?? true)) {
            return 0;
        }

        if ($this->sell_cents !== null) {
            return (int) $this->sell_cents;
        }

        $pct = $this->markup_pct ?? $defaultFeePct ?? $this->event?->management_fee_pct ?? self::DEFAULT_FEE_PCT;

        return (int) round($cost * (1 + $pct / 100));
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

    /** Outstanding = what it costs, less what has been paid against it. */
    public function outstandingCents(): int
    {
        return max(0, $this->costCents() - (int) $this->paid_cents);
    }

    /** Derive payment status from paid vs the amount due. */
    public function derivePaymentStatus(): string
    {
        $due = $this->costCents();

        if ($due > 0 && $this->paid_cents >= $due) {
            return 'paid';
        }

        // A line costed at nothing owes nothing, so it is settled rather than
        // pending forever — the supplier waived it, there is nothing to chase.
        if ($this->hasActual() && $due === 0) {
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
