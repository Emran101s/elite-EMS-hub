<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A sellable booth on an exhibition hall floor. The booth is the inventory;
 * the exhibitor is the buyer. Status is derived from that link so the floor
 * plan, the exhibitor list and the revenue numbers always agree.
 */
#[Fillable(['event_id', 'hall_id', 'exhibitor_id', 'number', 'price_cents', 'x', 'y', 'w_m', 'h_m', 'notes'])]
class EventBooth extends Model
{
    use Auditable;

    /** Only these changes are audit-worthy — decisions, not noise. */
    public const AUDIT_FIELDS = ['exhibitor_id', 'price_cents', 'number'];

    /** status => [label, hex]. */
    public const STATUS_META = [
        'available' => ['Available', '#94A3B8'],
        'reserved' => ['Reserved', '#D4AF37'],
        'sold' => ['Sold', '#22C55E'],
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'x' => 'float',
            'y' => 'float',
            'w_m' => 'float',
            'h_m' => 'float',
        ];
    }

    /**
     * Derived, never stored: no exhibitor → available; exhibitor confirmed or
     * paid → sold; anything in between → reserved.
     */
    public function status(): string
    {
        if (! $this->exhibitor_id || ! $this->exhibitor) {
            return 'available';
        }

        return in_array($this->exhibitor->status, ['confirmed', 'paid'], true) ? 'sold' : 'reserved';
    }

    public function statusLabel(): string
    {
        return self::STATUS_META[$this->status()][0];
    }

    public function statusHex(): string
    {
        return self::STATUS_META[$this->status()][1];
    }

    public function areaM2(): float
    {
        return round($this->w_m * $this->h_m, 1);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function hall(): BelongsTo
    {
        return $this->belongsTo(EventExhibitionHall::class, 'hall_id');
    }

    public function exhibitor(): BelongsTo
    {
        return $this->belongsTo(EventExhibitor::class, 'exhibitor_id');
    }

    public function auditLabel(): string
    {
        return 'Booth '.$this->number;
    }
}
