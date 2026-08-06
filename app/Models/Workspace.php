<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * A workspace inside a tenant — the unit an agency actually organises around:
 * a client, a division, a year's programme.
 *
 * Membership carries the role, which is the reason this table exists at all.
 * `users.role` is a single global string, so today a coordinator is a
 * coordinator everywhere; through the pivot the same person can run one
 * workspace and only read another.
 */
#[Fillable(['tenant_id', 'name', 'slug'])]
class Workspace extends Model
{
    use Auditable;

    public const AUDIT_FIELDS = ['name'];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * The table is named explicitly. Laravel would derive `user_workspace`
     * from alphabetical order; `workspace_user` reads the way the relationship
     * is actually spoken about, so the convention is overridden here rather
     * than the table being given the more awkward name.
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** This person's role here, or null when they are not a member. */
    public function roleFor(User $user): ?string
    {
        return $this->members()->whereKey($user->getKey())->first()?->pivot->role;
    }

    /** Add or re-grade a member. Idempotent, so callers need not check first. */
    public function grant(User $user, string $role): void
    {
        $this->members()->syncWithoutDetaching([$user->getKey() => ['role' => $role]]);
    }
}
