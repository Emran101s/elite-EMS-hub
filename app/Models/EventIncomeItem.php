<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['tenant_id',
    'event_id', 'source', 'description', 'amount_cents', 'status', 'notes'])]
class EventIncomeItem extends Model
{
    use BelongsToTenant;

    /** Manual income sources (sponsorship & exhibition are pulled from their modules). */
    public const SOURCES = [
        'client' => 'Client Fee / Payment',
        'registration' => 'Registration Fees',
        'tickets' => 'Ticket Sales',
        'grant' => 'Grant / Funding',
        'other' => 'Other Income',
    ];

    public const STATUSES = ['expected', 'invoiced', 'received'];

    protected function casts(): array
    {
        return ['amount_cents' => 'integer'];
    }

    public function sourceLabel(): string
    {
        return self::SOURCES[$this->source] ?? str($this->source)->headline();
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
