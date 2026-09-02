<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One written line of an event's Scope of Work — what the client has asked us
 * to deliver, or (is_exclusion) what is explicitly not included.
 *
 * Authored on the Scope tab and rendered by the Event Brief, which holds no
 * copy of its own: a scope typed in two places disagrees with itself the first
 * time one of them is revised.
 */
class EventScopeItem extends Model
{
    use BelongsToTenant, HasFactory;

    /**
     * The seed for the scope_area taxonomy. Editable in Settings, so a company
     * that groups its scope differently is not stuck with these.
     *
     * @var array<string,array{0:string,1:string}> key => [label, colour]
     */
    public const AREAS = [
        'management' => ['Event Management', '#1F4B99'],
        'venue_build' => ['Venue & Production', '#B45309'],
        'programme' => ['Programme & Content', '#0E9488'],
        'guests' => ['Guests & Registration', '#16A34A'],
        'movement' => ['Transport & Logistics', '#D97706'],
        'food_beverage' => ['Food & Beverage', '#92400E'],
        'brand_content' => ['Branding & Media', '#A855F7'],
        'general' => ['General', '#64748B'],
    ];

    protected $fillable = [
        'tenant_id', 'event_id', 'area', 'title', 'body', 'is_exclusion', 'position',
    ];

    protected function casts(): array
    {
        return [
            'is_exclusion' => 'boolean',
            'position' => 'integer',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function areaLabel(): string
    {
        return self::AREAS[$this->area][0] ?? ucfirst($this->area);
    }

    public function areaColor(): string
    {
        return self::AREAS[$this->area][1] ?? '#64748B';
    }
}
