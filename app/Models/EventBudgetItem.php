<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'category', 'description', 'estimated_cents', 'actual_cents', 'supplier_id', 'payment_status', 'invoice_number', 'due_on', 'notes'])]
class EventBudgetItem extends Model
{
    public const CATEGORIES = ['venue', 'catering', 'av', 'production', 'branding', 'transportation', 'printing', 'entertainment', 'security', 'misc'];

    public const PAYMENT_STATUSES = ['pending', 'partial', 'paid'];

    protected function casts(): array
    {
        return [
            'estimated_cents' => 'integer',
            'actual_cents' => 'integer',
            'due_on' => 'date',
        ];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
