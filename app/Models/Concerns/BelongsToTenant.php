<?php

namespace App\Models\Concerns;

use App\Models\Tenant;
use App\Support\Tenancy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Filters every query on this model to the current tenant, and stamps new rows
 * with it.
 *
 * Applied to every model whose table carries tenant_id — TenancyGuardTest fails
 * the build if one is missed, which is the only reason this is safe to rely on.
 * A retrofit like this does not fail loudly when somebody forgets; it fails in
 * six months when two customers can see each other's contracts.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $query) {
            if ($id = Tenancy::id()) {
                // Qualified with the table name: an unqualified tenant_id is
                // ambiguous the moment a query joins two scoped tables, which
                // is most of the interesting queries in this codebase.
                $query->where($query->getModel()->getTable().'.tenant_id', $id);
            }
        });

        // Stamped on the way in, so no caller has to remember. Only fills a
        // blank — an explicit tenant_id (a seeder, an admin tool moving a row)
        // is left alone.
        static::creating(function ($model) {
            if ($model->tenant_id === null && $id = Tenancy::id()) {
                $model->tenant_id = $id;
            }
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Deliberately unscoped — for tooling that must cross tenants.
     *
     * Named to be obvious in review and easy to grep. Anything calling this in
     * ordinary request code is a bug, not a shortcut.
     */
    public static function acrossTenants(): Builder
    {
        return static::query()->withoutGlobalScope('tenant');
    }
}
