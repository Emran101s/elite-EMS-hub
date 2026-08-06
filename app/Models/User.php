<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['tenant_id',
    'name', 'email', 'password', 'title', 'role', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use Auditable, HasFactory, Notifiable;

    use BelongsToTenant;

    /**
     * What is worth recording about an account.
     *
     * `role` is the whole point: it decides what a person can do, and until
     * this trait was added a promotion to admin or super_admin left no trace
     * anywhere. `email` is here because it is the account-recovery address, so
     * changing it is a takeover vector.
     *
     * Deliberately absent: `password`. A change is security-relevant, but the
     * trait records before/after values and a bcrypt hash is short enough to be
     * stored whole — putting hashes in a table read by managers is a worse
     * problem than the one it solves.
     */
    public const AUDIT_FIELDS = ['role', 'email'];

    /** Workspace roles — slug => label. */
    public const ROLES = [
        'super_admin' => 'Super Admin',
        'admin' => 'Admin',
        'manager' => 'Manager',
        'coordinator' => 'Coordinator',
        'viewer' => 'Viewer',
    ];

    public function roleLabel(): string
    {
        return self::ROLES[$this->role] ?? 'Member';
    }

    /**
     * The trail names the person, not their job.
     *
     * Auditable's default reaches for `title` first, and on this model that is
     * the job title ("Operations Manager") — which would make every row in a
     * privilege-change log ambiguous between two coordinators.
     */
    public function auditLabel(): string
    {
        return $this->name.' <'.$this->email.'>';
    }

    /** Role seniority, for "at least this role" checks. */
    private const ROLE_RANK = [
        'viewer' => 0,
        'coordinator' => 1,
        'manager' => 2,
        'admin' => 3,
        'super_admin' => 4,
    ];

    private bool $activeWorkspaceResolved = false;

    private ?Workspace $activeWorkspaceCache = null;

    /**
     * The workspace this person acts in.
     *
     * Every tenant has exactly one workspace today, by design — there is no
     * switcher, and deliberately no plan to build one until a real customer
     * needs a second. first() is therefore not a placeholder standing in for
     * missing UI; it is the whole rule, and stays the whole rule until
     * "which workspace" becomes a question a real person actually has to
     * answer. Memoized: this is read on every isAtLeast() call, which fires
     * many times per request across gates and policies.
     */
    public function activeWorkspace(): ?Workspace
    {
        if (! $this->activeWorkspaceResolved) {
            $this->activeWorkspaceCache = $this->workspaces()->first();
            $this->activeWorkspaceResolved = true;
        }

        return $this->activeWorkspaceCache;
    }

    /**
     * True when this user's role is the given role or more senior.
     *
     * Reads workspace_user.role — via the pivot already loaded onto
     * activeWorkspace(), not a second query — falling back to the legacy
     * users.role column only when no workspace membership exists at all
     * (should not happen after TeamRoster::save()'s sync, but a missing
     * membership must degrade to "least privilege that still works," never
     * throw mid-request).
     */
    public function isAtLeast(string $role): bool
    {
        $effective = $this->activeWorkspace()?->pivot?->role ?? $this->role;

        return (self::ROLE_RANK[$effective] ?? 0) >= (self::ROLE_RANK[$role] ?? PHP_INT_MAX);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    /**
     * Events this user starred.
     */
    /**
     * Plan Studio deliverables this person owns.
     *
     * The pivot is plan_item_user; the Planning board filters on it, and the
     * "who has nobody on it" figure is its absence.
     */
    public function planItems(): BelongsToMany
    {
        return $this->belongsToMany(PlanItem::class, 'plan_item_user');
    }

    public function favoriteEvents(): BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_favorites');
    }

    /**
     * Workspaces this person belongs to, with their role on each.
     *
     * Not yet read by any gate — `users.role` is still authoritative until the
     * authorization rework. The pivot is populated alongside it so the cutover
     * is a change of source, not a data migration under load.
     */
    public function workspaces(): BelongsToMany
    {
        return $this->belongsToMany(Workspace::class, 'workspace_user')
            ->withPivot('role')
            ->withTimestamps();
    }

    /** This person's role in a given workspace, or null if they are not in it. */
    public function roleIn(Workspace $workspace): ?string
    {
        return $this->workspaces()->whereKey($workspace->getKey())->first()?->pivot->role;
    }

    /**
     * Up-to-two-letter initials for the avatar chip.
     */
    public function initials(): string
    {
        return str($this->name)
            ->explode(' ')
            ->filter()
            ->map(fn (string $part) => mb_strtoupper(mb_substr($part, 0, 1)))
            ->take(2)
            ->implode('');
    }
}
