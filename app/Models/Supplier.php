<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['name', 'category', 'rating', 'email', 'phone', 'city', 'country'])]
class Supplier extends Model
{
    /** @use HasFactory<\Database\Factories\SupplierFactory> */
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
