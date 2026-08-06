<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A driver you hire, kept company-wide like the vehicle catalogue — the same
 * people come back event after event.
 *
 * Deliberately not a User: drivers are contracted per event, often per day, and
 * will never log in. They get a printed trip sheet and a WhatsApp message.
 */
#[Fillable(['tenant_id',
    'name', 'phone', 'whatsapp', 'licence_no', 'supplier_id', 'languages', 'rating', 'notes', 'is_active'])]
class TransportDriver extends Model
{
    use BelongsToTenant;

    /** Beyond this a day is unsafe, not merely long. */
    public const DUTY_LIMIT_MINUTES = 12 * 60;

    protected function casts(): array
    {
        return ['rating' => 'integer', 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->where('is_active', true);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function movements(): HasMany
    {
        return $this->hasMany(EventTransport::class, 'driver_id');
    }

    /** The number to message — falls back to the phone when no separate one is set. */
    public function whatsappNumber(): ?string
    {
        return $this->whatsapp ?: $this->phone;
    }

    /**
     * Minutes on duty for a given day: first pickup to last drop, which is what
     * a driver actually experiences — the gaps between runs are still waiting.
     */
    public function dutyMinutes(string $date): int
    {
        $runs = $this->movements()
            ->whereDate('depart_at', $date)
            ->whereNotIn('status', ['cancelled'])
            ->get();

        // Both ends of every run, because on an arrival `arrive_at` is the flight's
        // landing and can fall BEFORE the pickup — earliest and latest, not first
        // and last, is the only reading that can't go negative.
        $stamps = $runs
            ->flatMap(fn (EventTransport $m) => [$m->depart_at?->timestamp, $m->arrive_at?->timestamp])
            ->filter()
            ->values();

        if ($stamps->count() < 2) {
            return 0;
        }

        return (int) round(($stamps->max() - $stamps->min()) / 60);
    }

    public function isOverloadedOn(string $date): bool
    {
        return $this->dutyMinutes($date) > self::DUTY_LIMIT_MINUTES;
    }

    /** "13h 30m" */
    public static function readableMinutes(int $minutes): string
    {
        return intdiv($minutes, 60).'h'.($minutes % 60 ? ' '.($minutes % 60).'m' : '');
    }

    public function label(): string
    {
        return $this->name.($this->supplier ? ' · '.$this->supplier->name : '');
    }
}
