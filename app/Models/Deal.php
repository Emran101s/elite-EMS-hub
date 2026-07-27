<?php

namespace App\Models;

use App\Support\Workflow;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Work you are trying to win.
 *
 * A deal is what an event is before it exists. Winning one creates the Event
 * and links the two, so an event can always be traced back to the conversation
 * that produced it.
 */
// event_id and the won/lost stamps are written only by DealPipeline, but they
// have to be fillable: without them update() discards the link to the event a
// deal became, silently, and the win looks like it worked.
#[Fillable([
    'client_id', 'contact_id', 'owner_id', 'title', 'stage', 'type',
    'value_cents', 'currency', 'probability', 'expected_close_on',
    'expected_event_on', 'source', 'notes', 'position',
    'event_id', 'won_at', 'lost_at', 'lost_reason',
])]
class Deal extends Model
{
    /**
     * The pipeline. key => [label, default probability, hex].
     * Order is the order of the board.
     */
    public const STAGES = [
        'enquiry' => ['Enquiry', 10, '#94A3B8'],
        'qualified' => ['Qualified', 30, '#3B82F6'],
        'proposal' => ['Proposal', 55, '#D4AF37'],
        'negotiation' => ['Negotiation', 75, '#F97316'],
        'won' => ['Won', 100, '#22C55E'],
        'lost' => ['Lost', 0, '#94A3B8'],
    ];

    /** Stages still in play — the board's working lanes. */
    public const OPEN = ['enquiry', 'qualified', 'proposal', 'negotiation'];

    public const SOURCES = ['Referral', 'Inbound', 'Tender', 'Repeat client', 'Event', 'Outbound'];

    protected function casts(): array
    {
        return [
            'expected_close_on' => 'date',
            'expected_event_on' => 'date',
            'won_at' => 'datetime',
            'lost_at' => 'datetime',
            'value_cents' => 'integer',
            'probability' => 'integer',
            'position' => 'integer',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /** The event this deal became, once it was won. */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealActivity::class)->latest('happened_at');
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('stage', self::OPEN);
    }

    public function isOpen(): bool
    {
        return in_array($this->stage, self::OPEN, true);
    }

    public function stageLabel(): string
    {
        return Workflow::label('deal_stage', $this->stage);
    }

    public function stageHex(): string
    {
        return Workflow::color('deal_stage', $this->stage);
    }

    /** Value weighted by the chance of winning — what a forecast is made of. */
    public function weightedCents(): int
    {
        return (int) round($this->value_cents * $this->probability / 100);
    }

    /** Past its expected decision date and still open. */
    public function isStale(): bool
    {
        return $this->isOpen()
            && $this->expected_close_on !== null
            && $this->expected_close_on->isPast();
    }

    /** Days since anything was logged — a pipeline's real health signal. */
    public function daysSinceContact(): ?int
    {
        $last = $this->activities()->max('happened_at');

        return $last ? (int) now()->diffInDays($last, absolute: true) : null;
    }
}
