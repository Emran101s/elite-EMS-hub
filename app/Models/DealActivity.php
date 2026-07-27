<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Something that happened with a client: a call, a meeting, an email, a note.
 *
 * Logged against a deal where there is one, and against the client where there
 * is not — a relationship does not pause between opportunities.
 */
#[Fillable([
    'deal_id', 'client_id', 'contact_id', 'user_id', 'type',
    'subject', 'body', 'happened_at', 'follow_up_on', 'follow_up_done',
])]
class DealActivity extends Model
{
    /** key => [label, icon] */
    public const TYPES = [
        'call' => ['Call', 'chat'],
        'meeting' => ['Meeting', 'calendar'],
        'email' => ['Email', 'identification'],
        'note' => ['Note', 'clipboard'],
    ];

    protected function casts(): array
    {
        return [
            'happened_at' => 'datetime',
            'follow_up_on' => 'date',
            'follow_up_done' => 'boolean',
        ];
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

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type][0] ?? ucfirst($this->type);
    }

    /** Follow-ups that are due and not yet ticked off. */
    public function scopeDue(Builder $query): Builder
    {
        return $query->whereNotNull('follow_up_on')
            ->where('follow_up_done', false)
            ->whereDate('follow_up_on', '<=', now());
    }
}
