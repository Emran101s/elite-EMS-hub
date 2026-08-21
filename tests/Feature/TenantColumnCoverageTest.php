<?php

namespace Tests\Feature;

use App\Models\Tenant;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Slice 2: every table that holds customer data carries tenant_id.
 *
 * The important test here is the last one. A retrofit like this fails silently
 * — somebody adds a table in six months, forgets the column, and nothing
 * complains until two customers can see each other's data. So the coverage
 * check is mechanical and derived from the live schema, not from a list that
 * can drift out of date.
 */
class TenantColumnCoverageTest extends TestCase
{
    use RefreshDatabase;

    /** Tables that legitimately have no tenant: framework plumbing and the spine. */
    private const EXEMPT = [
        'migrations', 'cache', 'cache_locks', 'sessions', 'jobs', 'job_batches',
        'failed_jobs', 'password_reset_tokens',
        'tenants', 'workspaces', 'workspace_user', 'company_profiles',
        // Laravel Telescope (dev-only package): request/query/exception logs
        // for the app's own debugging, not customer data.
        'telescope_entries', 'telescope_entries_tags', 'telescope_monitoring',
    ];

    /** @return list<string> */
    private function tables(): array
    {
        return collect(Schema::getTableListing())
            ->map(fn ($t) => str_contains($t, '.') ? str($t)->afterLast('.')->toString() : $t)
            ->reject(fn ($t) => str_starts_with($t, 'sqlite_'))
            ->values()->all();
    }

    public function test_every_customer_data_table_has_a_tenant_id(): void
    {
        $missing = collect($this->tables())
            ->reject(fn ($t) => in_array($t, self::EXEMPT, true))
            ->reject(fn ($t) => Schema::hasColumn($t, 'tenant_id'))
            ->values();

        $this->assertTrue($missing->isEmpty(),
            "These tables hold customer data but have no tenant_id, so nothing can scope them:\n  ".
            $missing->implode("\n  ").
            "\n\nAdd the column, or add the table to self::EXEMPT with a reason.");
    }

    public function test_the_exempt_list_does_not_quietly_cover_a_real_table(): void
    {
        // An exemption list is only safe while it is small and every entry is
        // deliberate. If a future table is added to it to make this suite pass,
        // this test makes that visible in the diff.
        $this->assertCount(15, self::EXEMPT,
            'The exempt list changed. Every entry must be framework plumbing or '.
            'part of the tenancy spine — never a table that holds customer data.');
    }

    public function test_tenant_id_is_indexed_everywhere_it_exists(): void
    {
        // An unindexed tenant_id turns every scoped query into a full scan, and
        // every query becomes scoped in slice 3.
        $unindexed = [];

        foreach ($this->tables() as $table) {
            if (in_array($table, self::EXEMPT, true) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            if (! $this->hasIndexOn($table, 'tenant_id')) {
                $unindexed[] = $table;
            }
        }

        $this->assertSame([], $unindexed,
            'tenant_id must be indexed — every query is about to filter on it.');
    }

    /**
     * Driver-aware on purpose: a CI job now also runs this suite against
     * Postgres (see docs/17-postgres-cutover-plan.md), and PRAGMA is
     * SQLite-only. Postgres has no information_schema view for "which columns
     * are indexed" — that MySQL-only shortcut (information_schema.statistics)
     * does not exist here — so this reads pg_indexes' index definition text
     * instead, which is what psql itself does under \d.
     */
    private function hasIndexOn(string $table, string $column): bool
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($i) => collect(DB::select("PRAGMA index_info('{$i->name}')"))
                    ->contains(fn ($c) => $c->name === $column)),

            'pgsql' => collect(DB::select(
                'select indexdef from pg_indexes where tablename = ?', [$table]
            ))->contains(fn ($row) => preg_match('/\('.preg_quote($column, '/').'[,)]/', $row->indexdef) === 1),

            default => throw new \RuntimeException('hasIndexOn() has no implementation for driver: '.DB::connection()->getDriverName()),
        };
    }

    public function test_existing_rows_are_attributed_to_the_default_tenant(): void
    {
        // Reproduces what the migration does to a populated database: seed, then
        // stamp. Nothing may be left unattributed, because an unstamped row is
        // invisible to every scope once slice 3 lands.
        $this->seed(DemoDataSeeder::class);
        $tenant = Tenant::create(['name' => 'Elite Business Hub', 'slug' => 'ebh']);

        $stamped = 0;
        foreach ($this->tables() as $table) {
            if (in_array($table, self::EXEMPT, true) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            $stamped += DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenant->id]);
        }

        $this->assertGreaterThan(0, $stamped, 'the seeder should have produced rows to attribute');

        $orphans = [];
        foreach ($this->tables() as $table) {
            if (in_array($table, self::EXEMPT, true) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }
            if (DB::table($table)->whereNull('tenant_id')->exists()) {
                $orphans[] = $table;
            }
        }

        $this->assertSame([], $orphans, 'every row must belong to a tenant after the backfill');
    }

    public function test_an_unstamped_row_is_invisible_rather_than_shared(): void
    {
        // The reason nullable is acceptable: a NULL tenant_id fails closed.
        // `where tenant_id = ?` never matches NULL, so a row that somehow
        // escapes stamping disappears instead of leaking to every customer.
        $this->seed(DemoDataSeeder::class);
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);

        DB::table('events')->update(['tenant_id' => null]);

        $this->assertSame(0, DB::table('events')->where('tenant_id', $tenant->id)->count());
        $this->assertGreaterThan(0, DB::table('events')->count(), 'the rows still exist, they are just unattributed');
    }
}
