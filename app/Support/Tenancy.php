<?php

namespace App\Support;

use App\Models\Tenant;

/**
 * Who the current request belongs to.
 *
 * Deliberately a static holder rather than a container binding: the global
 * scope on every model reads this on every query, and it has to work in
 * contexts where no container-resolved dependency can be injected — model
 * boot methods, observers, queued jobs.
 *
 * TRANSITIONAL BEHAVIOUR, AND WHY
 *
 * When no tenant is bound, nothing is filtered. That is not an oversight; it
 * is the only way to retrofit isolation onto a running single-tenant system
 * without breaking every console command, seeder and test on the first commit.
 * The gap is closed by making ResolveTenant a global middleware rather than a
 * per-route opt-in, so no HTTP request can reach a controller unbound.
 *
 * Slice 4 tightens this: once every entry point is known to bind a tenant, an
 * unbound query becomes an exception rather than an unfiltered result.
 */
final class Tenancy
{
    private static ?int $tenantId = null;

    /** Set while inside withoutScoping(), so the scope stands down. */
    private static bool $suspended = false;

    /** The tenant every query is filtered to, or null when unbound. */
    public static function id(): ?int
    {
        return self::$suspended ? null : self::$tenantId;
    }

    public static function check(): bool
    {
        return self::id() !== null;
    }

    public static function use(Tenant|int|null $tenant): void
    {
        self::$tenantId = $tenant instanceof Tenant ? $tenant->getKey() : $tenant;
    }

    public static function forget(): void
    {
        self::$tenantId = null;
        self::$suspended = false;
    }

    /**
     * Run something across every tenant.
     *
     * The one legitimate escape hatch — super-admin tooling, a nightly job that
     * must see all customers, a migration. Named so it is obvious in a diff and
     * greppable in review; anything reaching for it in ordinary request code is
     * a bug.
     */
    public static function withoutScoping(callable $callback): mixed
    {
        $was = self::$suspended;
        self::$suspended = true;

        try {
            return $callback();
        } finally {
            self::$suspended = $was;
        }
    }

    /** Run something as a specific tenant, restoring whatever was bound before. */
    public static function actingAs(Tenant|int|null $tenant, callable $callback): mixed
    {
        $was = self::$tenantId;
        self::use($tenant);

        try {
            return $callback();
        } finally {
            self::$tenantId = $was;
        }
    }
}
