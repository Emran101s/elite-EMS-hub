<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id',
    'event_id', 'transport_id', 'attendee_id', 'name', 'category', 'direction', 'airline', 'phone', 'email', 'flight_no',
    'arrival_on', 'arrival_time', 'pickup_time', 'pickup_point', 'drop_point', 'hotel', 'venue_id', 'notes', 'luggage_note',
    'protocol_note', 'no_show_at', 'position', 'vehicle_no'])]
class EventTransportPassenger extends Model
{
    use BelongsToTenant;

    /**
     * Who this person is to the event. One field, and it's what turns a single
     * undifferentiated list into the VIP sheet, the speaker sheet and the
     * shuttle manifest with no extra data entry.
     *
     * @var array<string,string>
     */
    public const CATEGORIES = [
        'vip' => 'VIP',
        'speaker' => 'Speaker',
        'sponsor' => 'Sponsor',
        'delegate' => 'Delegate',
        'staff' => 'Staff',
        'media' => 'Media',
    ];

    /** Categories that pull a whole movement up the priority order. */
    public const PRIORITY_CATEGORIES = ['vip', 'speaker'];

    public const CATEGORY_CLASSES = [
        'vip' => 'bg-gold-100 text-gold-800',
        'speaker' => 'bg-violet-100 text-violet-700',
        'sponsor' => 'bg-sky-100 text-sky-700',
        'delegate' => 'bg-navy-50 text-navy-500',
        'staff' => 'bg-emerald-50 text-emerald-700',
        'media' => 'bg-amber-50 text-amber-700',
    ];

    protected function casts(): array
    {
        return ['position' => 'integer', 'arrival_on' => 'date', 'no_show_at' => 'datetime'];
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? 'Delegate';
    }

    public function categoryClass(): string
    {
        return self::CATEGORY_CLASSES[$this->category] ?? self::CATEGORY_CLASSES['delegate'];
    }

    public function isPriority(): bool
    {
        return in_array($this->category, self::PRIORITY_CATEGORIES, true);
    }

    public function isNoShow(): bool
    {
        return $this->no_show_at !== null;
    }

    public function scopeCategory(Builder $q, string $category): Builder
    {
        return $q->where('category', $category);
    }

    public function scopePriority(Builder $q): Builder
    {
        return $q->whereIn('category', self::PRIORITY_CATEGORIES);
    }

    /** The number to message about their pickup. */
    public function whatsappNumber(): ?string
    {
        return $this->phone;
    }

    /** "RJ 512 · 17 Oct 13:50" — the flight line as a driver reads it. */
    public function flightLine(): string
    {
        return collect([
            $this->airline,
            $this->flight_no,
            $this->arrival_on?->format('j M'),
            $this->arrival_time,
        ])->filter()->implode(' · ');
    }

    /** Waiting in the pool — imported, not yet on a vehicle. */
    public function scopeUnassigned(Builder $q): Builder
    {
        return $q->whereNull('transport_id');
    }

    public function scopeDirection(Builder $q, string $direction): Builder
    {
        return $q->where('direction', $direction);
    }

    /** Flight-list order: date, then time, then flight number. */
    public function scopeFlightOrder(Builder $q): Builder
    {
        return $q->orderByRaw('arrival_on IS NULL')->orderBy('arrival_on')
            ->orderByRaw('arrival_time IS NULL')->orderBy('arrival_time')
            ->orderBy('flight_no')->orderBy('name');
    }

    public function isAssigned(): bool
    {
        return $this->transport_id !== null;
    }

    public function transport(): BelongsTo
    {
        return $this->belongsTo(EventTransport::class, 'transport_id');
    }

    /** The registration record this guest was pulled from, when there is one. */
    public function attendee(): BelongsTo
    {
        return $this->belongsTo(EventAttendee::class, 'attendee_id');
    }

    /**
     * The hotel, when it is one of yours from the directory.
     *
     * `hotel` stays alongside it as the name this row was made with — a
     * rooming list printed last month should keep reading the same.
     */
    public function venue(): BelongsTo
    {
        return $this->belongsTo(Venue::class);
    }
}
