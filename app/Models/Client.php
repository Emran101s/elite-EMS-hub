<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['tenant_id',
    'name', 'logo_path', 'organization', 'contact_name', 'email', 'phone', 'website', 'notes'])]
class Client extends Model
{
    use BelongsToTenant;

    public function events(): HasMany
    {
        return $this->hasMany(Event::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function primaryContact(): HasOne
    {
        return $this->hasOne(Contact::class)->where('is_primary', true);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(Deal::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(DealActivity::class)->latest('happened_at');
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
