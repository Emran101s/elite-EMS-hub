<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['event_id', 'title', 'category', 'probability', 'impact', 'owner_id', 'mitigation', 'status', 'due_on'])]
class EventRisk extends Model
{
    public const CATEGORIES = ['venue', 'supplier', 'budget', 'client_approval', 'speaker', 'logistics', 'production', 'weather', 'attendance', 'technical'];

    public const STATUSES = ['open', 'monitoring', 'mitigated', 'escalated', 'closed'];

    protected function casts(): array
    {
        return [
            'probability' => 'integer',
            'impact' => 'integer',
            'due_on' => 'date',
        ];
    }

    /** Severity 1–25: probability × impact. */
    public function severity(): int
    {
        return $this->probability * $this->impact;
    }

    public function isOpen(): bool
    {
        return in_array($this->status, ['open', 'monitoring', 'escalated']);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
