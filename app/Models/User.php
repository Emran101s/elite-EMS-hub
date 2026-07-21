<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'title', 'role', 'avatar_path'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

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

    /** Role seniority, for "at least this role" checks. */
    private const ROLE_RANK = [
        'viewer' => 0,
        'coordinator' => 1,
        'manager' => 2,
        'admin' => 3,
        'super_admin' => 4,
    ];

    /** True when this user's role is the given role or more senior. */
    public function isAtLeast(string $role): bool
    {
        return (self::ROLE_RANK[$this->role] ?? 0) >= (self::ROLE_RANK[$role] ?? PHP_INT_MAX);
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
    public function favoriteEvents(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Event::class, 'event_favorites');
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
