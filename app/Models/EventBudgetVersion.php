<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'version', 'label', 'status', 'note', 'snapshot', 'totals', 'requested_by', 'decided_by', 'decided_at'])]
class EventBudgetVersion extends Model
{
    use Auditable;

    /** Only these changes are audit-worthy — decisions, not noise. */
    public const AUDIT_FIELDS = ['status'];

    public const STATUSES = ['pending', 'approved', 'rejected', 'superseded'];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'totals' => 'array',
            'decided_at' => 'datetime',
        ];
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

    public function auditLabel(): string
    {
        return 'Budget v'.$this->version;
    }
}
