<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'title', 'type', 'status', 'requested_by', 'decided_by', 'decided_at', 'notes', 'source_type', 'source_id'])]
class EventApproval extends Model
{
    use Auditable;

    /** Only these changes are audit-worthy — decisions, not noise. */
    public const AUDIT_FIELDS = ['status', 'decided_by', 'title'];

    public const TYPES = ['budget', 'supplier', 'design', 'venue', 'agenda', 'client', 'payment', 'report'];

    public const STATUSES = ['pending', 'approved', 'rejected', 'needs_revision'];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
