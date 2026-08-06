<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One paying customer.
 *
 * Kept deliberately thin, and separate from CompanyProfile: this row is
 * identity and commercial status — who they are, whether they may use the
 * platform, what they pay for. CompanyProfile stays what it already is, the
 * settings a company edits (currency, ticket types, bank accounts), and now
 * hangs off a tenant. Merging the two would put plan and subscription data in
 * the same table as sponsor packages.
 */
#[Fillable(['name', 'slug', 'status', 'trial_ends_at'])]
class Tenant extends Model
{
    use Auditable, SoftDeletes;

    /** Suspension and plan changes are commercial decisions worth a record. */
    public const AUDIT_FIELDS = ['name', 'status', 'trial_ends_at'];

    public const STATUSES = [
        'active' => 'Active',
        'trialing' => 'Trialing',
        'suspended' => 'Suspended',
    ];

    protected function casts(): array
    {
        return ['trial_ends_at' => 'datetime'];
    }

    public function workspaces(): HasMany
    {
        return $this->hasMany(Workspace::class);
    }

    public function profile(): HasOne
    {
        return $this->hasOne(CompanyProfile::class);
    }

    /** A suspended tenant keeps its data and loses its access. */
    public function isActive(): bool
    {
        return $this->status !== 'suspended';
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
