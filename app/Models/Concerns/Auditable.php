<?php

namespace App\Models\Concerns;

use App\Models\AuditLog;
use App\Models\Event;
use Illuminate\Support\Str;

/**
 * Writes an append-only AuditLog row on create / update / delete.
 *
 * Deliberately quiet: nothing is logged without an authenticated user (seeders
 * and console runs stay silent), timestamps are never diffed, and a model can
 * narrow the fields worth recording with AUDIT_FIELDS — decisions and money,
 * not cursor noise.
 */
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn ($m) => $m->writeAudit('created', []));

        static::updated(function ($m) {
            $changes = $m->auditDiff();
            if ($changes !== []) {
                $m->writeAudit('updated', $changes);
            }
        });

        static::deleted(fn ($m) => $m->writeAudit('deleted', []));
    }

    /** field => [from, to] for the fields this model considers audit-worthy. */
    private function auditDiff(): array
    {
        $watch = defined(static::class.'::AUDIT_FIELDS') ? static::AUDIT_FIELDS : null;
        $diff = [];

        foreach ($this->getChanges() as $field => $to) {
            if (in_array($field, ['created_at', 'updated_at'], true)) {
                continue;
            }
            if ($watch !== null && ! in_array($field, $watch, true)) {
                continue;
            }
            $from = $this->getOriginal($field);
            $diff[$field] = [$this->auditValue($from), $this->auditValue($to)];
        }

        return $diff;
    }

    /** Keep stored values readable and small — no arrays, no long blobs. */
    private function auditValue($value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_bool($value)) {
            return $value ? 'yes' : 'no';
        }
        if (is_array($value)) {
            return '[…]';
        }

        return Str::limit((string) $value, 120);
    }

    private function writeAudit(string $action, array $changes): void
    {
        if (! auth()->check() || AuditLog::$muted) {
            return;
        }

        // A deleted event no longer exists, so the row can't point at it — the
        // trail keeps auditable_id + label instead, and survives the cascade.
        $eventId = $this instanceof Event
            ? ($action === 'deleted' ? null : $this->id)
            : ($this->event_id ?? null);

        AuditLog::create([
            'user_id' => auth()->id(),
            'event_id' => $eventId,
            'action' => $action,
            'auditable_type' => class_basename($this),
            'auditable_id' => $this->getKey(),
            'label' => $this->auditLabel(),
            'changes' => $changes ?: null,
        ]);
    }

    /** Human subject line; models override for a better one. */
    public function auditLabel(): string
    {
        return $this->title ?? $this->name ?? $this->number ?? class_basename($this).' #'.$this->getKey();
    }
}
