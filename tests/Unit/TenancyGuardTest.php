<?php

namespace Tests\Unit;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Event;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;
use ReflectionClass;

/**
 * The mechanical guarantee behind the whole tenancy retrofit.
 *
 * A missed scope does not fail loudly. It fails in six months, when a second
 * customer signs up and can read the first one's contracts. The only defence
 * that survives that timescale is a check that runs on every push, so this
 * derives its list from the live schema rather than a hardcoded one: add a
 * table with tenant_id and forget the trait, and the build breaks today.
 *
 * Modelled on AuthorizationGuardTest, which already proved the pattern works
 * on this codebase.
 */
class TenancyGuardTest extends \Tests\TestCase
{
    /**
     * Tables carrying tenant_id whose model legitimately does not scope.
     *
     * Only the spine belongs here: a Workspace is looked up *to determine* the
     * tenant, and CompanyProfile is resolved before a tenant is bound at all.
     * Nothing that holds event, client or attendee data may ever be added.
     */
    private const UNSCOPED = [
        'workspaces',
        'company_profiles',
    ];

    /** @return array<class-string<Model>, string> model => table */
    private function tenantedModels(): array
    {
        $found = [];

        foreach (glob(app_path('Models/*.php')) as $file) {
            $class = 'App\\Models\\'.basename($file, '.php');

            if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
                continue;
            }
            if ((new ReflectionClass($class))->isAbstract()) {
                continue;
            }

            $table = (new $class)->getTable();

            if (Schema::hasColumn($table, 'tenant_id')) {
                $found[$class] = $table;
            }
        }

        return $found;
    }

    public function test_every_model_on_a_tenanted_table_uses_the_trait(): void
    {
        $missing = [];

        foreach ($this->tenantedModels() as $class => $table) {
            if (in_array($table, self::UNSCOPED, true)) {
                continue;
            }
            if (! in_array(BelongsToTenant::class, class_uses_recursive($class), true)) {
                $missing[] = "{$class} (table: {$table})";
            }
        }

        $this->assertSame([], $missing,
            "These models sit on a tenanted table but do not scope to it, so every\n".
            "query against them returns every customer's rows:\n  ".
            implode("\n  ", $missing).
            "\n\nAdd `use BelongsToTenant;` to each.");
    }

    public function test_the_unscoped_allowlist_stays_tiny_and_deliberate(): void
    {
        // The allowlist is the one way this guard can be defeated: add a model
        // to it and the build goes green while the leak stays. Asserting the
        // exact contents means widening it is visible in the diff and has to be
        // argued for in review.
        $this->assertSame(['workspaces', 'company_profiles'], self::UNSCOPED,
            'Only the tenancy spine may skip scoping. Anything holding customer '.
            'data — events, clients, attendees, contracts — never qualifies.');
    }

    public function test_the_trait_registers_a_scope_and_a_stamp(): void
    {
        // Guards against the trait being hollowed out later — a version that
        // still exists and no longer filters would pass the test above while
        // protecting nothing.
        $model = new Event;

        $this->assertArrayHasKey('tenant', $model->getGlobalScopes(),
            'BelongsToTenant must register a global scope named "tenant".');
        $this->assertTrue(method_exists($model, 'acrossTenants'),
            'The deliberate escape hatch must exist, so nobody invents an ad-hoc one.');
    }
}
