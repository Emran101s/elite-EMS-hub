<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'event_id', 'name', 'email', 'phone', 'organization', 'job_title',
    'ticket_type', 'status', 'amount_cents', 'vip', 'dietary', 'notes', 'checked_in_at',
])]
class EventAttendee extends Model
{
    /** Registration lifecycle. */
    public const STATUSES = ['registered', 'confirmed', 'checked_in', 'cancelled'];

    /** [label, pill classes] per status. */
    public const STATUS_META = [
        'registered' => ['Registered', 'bg-navy-100 text-navy-600'],
        'confirmed' => ['Confirmed', 'bg-blue-100 text-blue-700'],
        'checked_in' => ['Checked in', 'bg-emerald-100 text-emerald-700'],
        'cancelled' => ['Cancelled', 'bg-navy-100 text-navy-400 line-through'],
    ];

    protected function casts(): array
    {
        return [
            'amount_cents' => 'integer',
            'vip' => 'boolean',
            'checked_in_at' => 'datetime',
        ];
    }

    public function statusMeta(): array
    {
        return self::STATUS_META[$this->status] ?? self::STATUS_META['registered'];
    }

    public function initials(): string
    {
        return str($this->name)->explode(' ')->filter()
            ->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->take(2)->implode('');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
