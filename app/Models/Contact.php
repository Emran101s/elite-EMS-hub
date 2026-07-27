<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A person at a client. Clients used to carry one `contact_name` string, which
 * is fine until the person who signs is not the person who answers the phone.
 */
#[Fillable(['client_id', 'name', 'title', 'email', 'phone', 'is_primary', 'notes'])]
class Contact extends Model
{
    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealActivity::class);
    }

    /** Up-to-two-letter initials for the avatar chip. */
    public function initials(): string
    {
        return str($this->name)->explode(' ')->filter()
            ->map(fn (string $p) => mb_strtoupper(mb_substr($p, 0, 1)))
            ->take(2)->implode('');
    }
}
