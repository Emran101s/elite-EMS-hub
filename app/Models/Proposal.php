<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Services\DealPipeline;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * The priced offer that goes out before there is anything to sign.
 *
 * The pipeline already had a "proposal" stage with nothing behind it, so the
 * number a deal was worth got typed twice — once into the document, once into
 * the deal — and the two drifted. A proposal carries its own lines and belongs
 * to the deal it is trying to win.
 *
 * Accepting one wins that deal, and winning is what creates the event, so the
 * figure the client agreed to becomes the figure the event is budgeted at
 * without anybody retyping it.
 *
 * Only what the office decided is stored — draft, sent, accepted, declined.
 * Expiry is derived, the same way an invoice derives overdue: a date passing is
 * not an act, and a proposal nobody sent cannot lapse.
 */
class Proposal extends Model
{
    use Auditable;
    use BelongsToTenant;

    public const AUDIT_FIELDS = ['status', 'issued_on', 'valid_until', 'decided_on'];

    /** What the office did with it. */
    public const STATUSES = ['draft', 'sent', 'accepted', 'declined'];

    /** state => [label, hex]. Includes the derived one. */
    public const STATE_META = [
        'draft' => ['Draft', '#94A3B8'],
        'sent' => ['Out', '#3B82F6'],
        'expired' => ['Expired', '#F97316'],
        'accepted' => ['Accepted', '#22C55E'],
        'declined' => ['Declined', '#DC2626'],
    ];

    protected $fillable = ['tenant_id', 'deal_id', 'client_id', 'contact_id', 'event_id', 'owner_id',
        'number', 'title', 'status', 'currency', 'issued_on', 'valid_until', 'tax_pct', 'fee_pct',
        'summary', 'terms', 'decided_on', 'decline_reason'];

    protected function casts(): array
    {
        return [
            'issued_on' => 'date',
            'valid_until' => 'date',
            'decided_on' => 'date',
            'tax_pct' => 'float',
            'fee_pct' => 'float',
        ];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(ProposalLine::class)->orderBy('sort')->orderBy('id');
    }

    public function deal(): BelongsTo
    {
        return $this->belongsTo(Deal::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    /**
     * The currency this document is written in.
     *
     * Its own, because a document may deliberately be raised in a client's
     * currency; then the event's, when it has one and never chose for itself;
     * then the house currency. What it is not is a hardcoded 'JOD' — which is
     * what the screens fell back to, so a dollar event could show a dinar
     * total to anyone whose document predated the currency being set.
     */
    /**
     * Named `currencyCode`, not `currency`.
     *
     * A method sharing a column's name is only ever consulted when the
     * attribute is missing — and then Eloquent reaches it through the relation
     * resolver and demands a relationship instance. It fails exactly on the
     * partially-loaded models that made the method necessary.
     */
    public function currencyCode(): string
    {
        // The raw attribute, not $this->currency: models run in strict mode, so
        // reading an attribute that was never loaded throws rather than
        // returning null — and a method sharing the column's name is exactly
        // where that bites.
        return ($this->attributes['currency'] ?? null)
            ?: ($this->event?->currency ?: CompanyProfile::currency());
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /* ── money ── */

    /**
     * The headline price: what the client is being asked to agree to.
     *
     * Optional lines are quoted and not counted. They are in the offer so the
     * client can say yes to one, and out of the total so the number on the
     * front page is the number under discussion.
     */
    public function subtotalCents(): int
    {
        return $this->lines->reject->optional->sum(fn (ProposalLine $l) => $l->amountCents());
    }

    /** Quoted, not counted — until somebody says yes and it becomes a line. */
    public function optionalCents(): int
    {
        return $this->lines->filter->optional->sum(fn (ProposalLine $l) => $l->amountCents());
    }

    /**
     * The management fee, on the counted work — its own row, like the tax.
     *
     * Optional extras are outside it for the same reason they are outside the
     * total: they are not being agreed to yet, and a fee on something the
     * client has not said yes to is a number they will ask about.
     */
    public function feeCents(): int
    {
        return (int) round($this->subtotalCents() * (float) $this->fee_pct / 100);
    }

    /** The work plus the fee on it, before tax. */
    public function netCents(): int
    {
        return $this->subtotalCents() + $this->feeCents();
    }

    /** Tax on the net, fee included — the fee is part of what is charged. */
    public function taxCents(): int
    {
        return (int) round($this->netCents() * $this->tax_pct / 100);
    }

    public function totalCents(): int
    {
        return $this->netCents() + $this->taxCents();
    }

    /* ── state ── */

    /**
     * What it is right now.
     *
     * Draft, accepted and declined are decisions and win. Expired is arithmetic
     * — and only applies to an offer that actually went out: a draft sitting
     * past its own date has not lapsed, it was never made.
     */
    public function state(): string
    {
        if ($this->status !== 'sent') {
            return $this->status;
        }

        return $this->valid_until?->isPast() ? 'expired' : 'sent';
    }

    public function stateLabel(): string
    {
        return self::STATE_META[$this->state()][0];
    }

    public function stateHex(): string
    {
        return self::STATE_META[$this->state()][1];
    }

    /** Still capable of being accepted. */
    public function isLive(): bool
    {
        return $this->state() === 'sent';
    }

    /** Days left to decide — negative once it has lapsed, null with no date. */
    public function daysLeft(): ?int
    {
        return $this->valid_until ? (int) now()->startOfDay()->diffInDays($this->valid_until, false) : null;
    }

    /* ── the life of an offer ── */

    /** "EBH-PRO-2026-014" — sequential within the year it is raised. */
    public static function nextNumber(): string
    {
        $year = now()->format('Y');
        $seq = static::where('number', 'like', 'EBH-PRO-'.$year.'-%')->count() + 1;

        do {
            $number = 'EBH-PRO-'.$year.'-'.str_pad((string) $seq, 3, '0', STR_PAD_LEFT);
            $seq++;
        } while (static::where('number', $number)->exists());

        return $number;
    }

    /**
     * Create a proposal with a fresh number, retrying the number on collision.
     *
     * Same contention as Invoice::createNumbered() — two requests raising a
     * proposal at nearly the same moment can both see the same free number.
     */
    public static function createNumbered(array $attributes): self
    {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            try {
                return static::create(array_merge($attributes, ['number' => static::nextNumber()]));
            } catch (UniqueConstraintViolationException $e) {
                if ($attempt === 5) {
                    throw $e;
                }
            }
        }
    }

    /** Start an offer against a deal, carrying across what is already known. */
    public static function forDeal(Deal $deal): self
    {
        $proposal = static::createNumbered([
            'deal_id' => $deal->id,
            'client_id' => $deal->client_id,
            'contact_id' => $deal->contact_id,
            'owner_id' => $deal->owner_id ?? auth()->id(),
            'title' => $deal->title,
            'status' => 'draft',
            'currency' => $deal->currency ?: CompanyProfile::currency(),
            // No fee yet, deliberately. The first line below is the DEAL'S
            // VALUE, which is already a whole-job figure — a fee on top of it
            // inflates the quote by 15% the same way charging one on a contract
            // installment bills it twice. The editor offers the house rate in
            // one click once the offer is priced from real lines, so it is a
            // decision rather than either a silent default or an oversight.
            'fee_pct' => 0,
            'issued_on' => now()->toDateString(),
            'valid_until' => now()->addDays(30)->toDateString(),
        ]);

        // The deal's value is what somebody guessed it was worth. It becomes
        // the first line so the offer starts from the conversation rather than
        // from an empty page — and is priced properly from there.
        if ($deal->value_cents > 0) {
            $proposal->lines()->create([
                'description' => $deal->title,
                'qty' => 1,
                'unit_cents' => $deal->value_cents,
                'sort' => 0,
            ]);
        }

        return $proposal->load('lines');
    }

    /**
     * The client said yes.
     *
     * Winning the deal is what creates the event, so this is the one place the
     * agreed figure crosses from the offer into the work: the deal's value
     * becomes the proposal's total first, and the event is budgeted from it.
     *
     * Idempotent — accepting twice must not open a second event.
     */
    public function accept(): ?Event
    {
        if ($this->state() === 'accepted') {
            return $this->event;
        }

        return DB::transaction(function () {
            $this->update([
                'status' => 'accepted',
                'decided_on' => now()->toDateString(),
                'decline_reason' => null,
            ]);

            $deal = $this->deal;

            if (! $deal) {
                return $this->event;
            }

            // Agreed beats estimated: the deal was worth whatever the client
            // actually signed up to.
            $deal->update(['value_cents' => $this->totalCents()]);

            $event = app(DealPipeline::class)->win($deal->fresh());

            $this->update(['event_id' => $event->id]);

            return $event;
        });
    }

    public function decline(?string $reason = null): void
    {
        $this->update([
            'status' => 'declined',
            'decided_on' => now()->toDateString(),
            'decline_reason' => $reason ?: null,
        ]);
    }

    public function auditLabel(): string
    {
        return $this->number;
    }
}
