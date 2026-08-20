<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Concerns\PricedByUnit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One thing THIS event will be invoiced for, at THIS event's prices.
 *
 * The house price list is a starting point, not a rate card. A room negotiated
 * at 95 for one summit is 78 for the next, and the cost behind it moves too —
 * so the prices an event is billed at belong to the event, and the house list
 * is never rolled into one automatically.
 *
 * Cost and sell both live here, prepared before anything is invoiced, which is
 * what makes the margin knowable before the work is done rather than after.
 */
class EventInvoiceItem extends Model
{
    use Auditable, PricedByUnit;
    use BelongsToTenant;

    public const AUDIT_FIELDS = ['name', 'cost_cents', 'sell_cents', 'unit', 'active'];

    protected $fillable = ['tenant_id', 'event_id', 'service_item_id', 'code', 'name', 'category', 'section',
        'detail', 'unit', 'cost_cents', 'sell_cents', 'currency', 'tax_pct', 'active', 'sort'];

    protected function casts(): array
    {
        return [
            // decimal:1, not integer — the underlying columns are
            // decimal(15,1) now (tenths of a cent), so a price of 127.116
            // keeps its third decimal instead of being rounded away on read.
            'cost_cents' => 'decimal:1',
            'sell_cents' => 'decimal:1',
            'tax_pct' => 'float',
            'active' => 'boolean',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** The house item it was pulled from, if it was. Provenance, not a link. */
    public function source(): BelongsTo
    {
        return $this->belongsTo(ServiceItem::class, 'service_item_id');
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('active', true);
    }

    /* ── what it earns ── */

    /** Per unit. Negative means it is being sold below what it costs. */
    public function marginCents(): float
    {
        return $this->sell_cents - $this->cost_cents;
    }

    /**
     * Margin as a share of the price, the way a P&L reads it.
     *
     * Null rather than zero when nothing is charged: an item you absorb has no
     * margin to speak of, and calling that 0% puts it in the same bucket as one
     * sold at exactly cost.
     */
    public function marginPct(): ?int
    {
        return $this->sell_cents > 0
            ? (int) round($this->marginCents() / $this->sell_cents * 100)
            : null;
    }

    /** Priced below what it costs — worth saying out loud on the screen. */
    public function isUnderwater(): bool
    {
        return $this->sell_cents > 0 && $this->marginCents() < 0;
    }

    /**
     * Copy a house item onto an event, at the house price to begin with.
     *
     * The cost starts at nothing rather than being invented: what a supplier
     * will charge for this event is a fact nobody has yet, and a guessed cost
     * reads exactly like a real one on a margin report.
     */
    public static function fromCatalogue(Event $event, ServiceItem $item): self
    {
        return static::updateOrCreate(
            ['event_id' => $event->id, 'code' => $item->code],
            [
                'service_item_id' => $item->id,
                'name' => $item->name,
                'category' => $item->category,
                'section' => $item->section,
                'detail' => $item->detail,
                'unit' => $item->unit,
                'sell_cents' => $item->unit_price_cents,
                'currency' => $item->currency,
                'tax_pct' => $item->tax_pct,
                'active' => true,
                'sort' => (int) static::where('event_id', $event->id)->max('sort') + 1,
            ],
        );
    }

    public function auditLabel(): string
    {
        return $this->name;
    }
}
