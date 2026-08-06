<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Append-only activity record. Written by the Auditable trait, never edited. */
#[Fillable(['tenant_id',
    'user_id', 'event_id', 'action', 'auditable_type', 'auditable_id', 'label', 'changes'])]
class AuditLog extends Model
{
    use BelongsToTenant;

    public const UPDATED_AT = null;

    /**
     * Machine-driven bulk work (the plan→task mirror) isn't a human decision;
     * services set this around their run so the trail stays about people.
     */
    public static bool $muted = false;

    public static function muted(callable $fn): mixed
    {
        self::$muted = true;
        try {
            return $fn();
        } finally {
            self::$muted = false;
        }
    }

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** "Contract → status: draft → sent", built from the stored diff. */
    public function summary(): string
    {
        return collect($this->changes ?? [])
            ->map(fn ($pair, $field) => str($field)->replace('_', ' ').': '
                .($pair[0] ?? '—').' → '.($pair[1] ?? '—'))
            ->implode(' · ');
    }
}
