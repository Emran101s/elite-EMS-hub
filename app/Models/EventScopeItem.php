<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\ScopeStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One deliverable on an event's Delivery Scope.
 *
 * A task says "book the lighting". A deliverable says who is answerable, when
 * it is due, what proves it finished, and what is explicitly not included.
 * That last pair is the difference between a scope and a checklist.
 *
 * This model deliberately holds no status. See App\Support\ScopeStatus.
 */
class EventScopeItem extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * The seed for the scope_workstream taxonomy — the strands a team is
     * organised around, not a copy of the module list. Editable in Settings
     * the moment the platform is used by a company that groups work
     * differently, which is the point of it being a taxonomy at all.
     *
     * @var array<string,array{0:string,1:string}> key => [label, colour]
     */
    public const WORKSTREAMS = [
        'venue_build' => ['Venue & Build', '#B45309'],
        'programme' => ['Programme', '#0E9488'],
        'guests' => ['Guests & Attendees', '#16A34A'],
        'movement' => ['Movement', '#D97706'],
        'food_beverage' => ['Food & Beverage', '#92400E'],
        'commercial' => ['Commercial', '#1F4B99'],
        'brand_content' => ['Brand & Content', '#A855F7'],
        'compliance' => ['Compliance & Safety', '#E2574C'],
        'delivery' => ['Delivery', '#64748B'],
    ];

    protected $fillable = [
        'tenant_id', 'event_id', 'workstream', 'title', 'definition_of_done',
        'out_of_scope', 'owner_id', 'contributor_ids', 'offset_days',
        'source_type', 'source_id', 'position',
    ];

    protected function casts(): array
    {
        return [
            'contributor_ids' => 'array',
            'offset_days' => 'integer',
            'position' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    /**
     * The real date this falls due, from the event's start.
     *
     * Null when the event has no start date yet — a T-minus offset against
     * nothing is not a date, and reporting today would make every deliverable
     * on an undated event look overdue.
     */
    public function dueOn(): ?Carbon
    {
        return $this->event?->starts_at
            ? $this->event->starts_at->copy()->startOfDay()->addDays($this->offset_days)
            : null;
    }

    /** "T-14", or "T+2" for the rare deliverable that lands after the event. */
    public function tMinus(): string
    {
        return $this->offset_days === 0 ? 'T' : 'T'.($this->offset_days > 0 ? '+' : '−').abs($this->offset_days);
    }

    public function isOverdue(): bool
    {
        $due = $this->dueOn();

        return $due !== null && $due->isPast() && ! ScopeStatus::isMet($this);
    }

    /** Users named as contributing, in the order they were added. */
    public function contributors()
    {
        $ids = $this->contributor_ids ?: [];

        return $ids === [] ? collect() : User::whereIn('id', $ids)->orderBy('name')->get();
    }
}
