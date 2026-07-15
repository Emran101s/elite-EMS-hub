<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'logo_path', 'organization', 'contact_name', 'email', 'phone', 'website', 'notes'])]
class Client extends Model
{
    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    /** Up-to-two-letter initials for the logo fallback chip. */
    public function initials(): string
    {
        return str($this->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
