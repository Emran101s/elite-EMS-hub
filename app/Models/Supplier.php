<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\SupplierFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['tenant_id',
    'name', 'category', 'rating', 'email', 'phone', 'city', 'country'])]
class Supplier extends Model
{
    use BelongsToTenant;

    /** @use HasFactory<SupplierFactory> */
    use HasFactory;

    public const CATEGORIES = ['catering', 'av_lighting', 'production', 'support', 'logistics', 'decor'];

    protected function casts(): array
    {
        return [
            'rating' => 'float',
        ];
    }

    public function events(): BelongsToMany
    {
        return $this->belongsToMany(Event::class);
    }
}
