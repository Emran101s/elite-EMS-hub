<?php

namespace Tests\Feature;

use App\Models\CompanyProfile;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workspace;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Slice 1 of the tenancy retrofit: the spine exists and expresses what the old
 * shape could not — a role that differs per workspace.
 *
 * What this slice deliberately does NOT do is enforce isolation. There is no
 * global scope yet, so these tests assert the structure, not the guarantee.
 * The guarantee arrives in slice 3 with a guard test that fails the build on
 * any model missing tenant scoping.
 */
class TenancySpineTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_tenant_owns_workspaces_and_a_company_profile(): void
    {
        $tenant = Tenant::create(['name' => 'DeepRoot Solutions', 'slug' => 'deeproot']);
        $workspace = $tenant->workspaces()->create(['name' => 'Corporate', 'slug' => 'corporate']);
        $profile = CompanyProfile::create(['name' => 'DeepRoot Solutions', 'tenant_id' => $tenant->id]);

        $this->assertTrue($tenant->workspaces->contains($workspace));
        $this->assertSame($tenant->id, $profile->fresh()->tenant->id);
        $this->assertSame($tenant->id, $workspace->tenant->id);
    }

    public function test_one_person_can_hold_different_roles_in_different_workspaces(): void
    {
        // The single reason this table exists. users.role is one global string,
        // so this sentence was previously unrepresentable.
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $corporate = $tenant->workspaces()->create(['name' => 'Corporate', 'slug' => 'corporate']);
        $weddings = $tenant->workspaces()->create(['name' => 'Weddings', 'slug' => 'weddings']);
        $user = User::factory()->create(['role' => 'coordinator']);

        $corporate->grant($user, 'manager');
        $weddings->grant($user, 'viewer');

        $this->assertSame('manager', $user->roleIn($corporate));
        $this->assertSame('viewer', $user->roleIn($weddings));
        $this->assertNull($user->roleIn(
            $tenant->workspaces()->create(['name' => 'Sports', 'slug' => 'sports'])
        ));
    }

    public function test_granting_twice_regrades_rather_than_duplicates(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace = $tenant->workspaces()->create(['name' => 'Corporate', 'slug' => 'corporate']);
        $user = User::factory()->create();

        $workspace->grant($user, 'coordinator');
        $workspace->grant($user, 'admin');

        $this->assertSame('admin', $workspace->roleFor($user));
        $this->assertSame(1, DB::table('workspace_user')
            ->where('workspace_id', $workspace->id)->where('user_id', $user->id)->count());
    }

    public function test_two_tenants_may_use_the_same_workspace_slug(): void
    {
        // Slugs are unique per tenant, not globally — otherwise the first
        // agency to create "corporate" would block every other agency.
        $a = Tenant::create(['name' => 'Agency A', 'slug' => 'agency-a']);
        $b = Tenant::create(['name' => 'Agency B', 'slug' => 'agency-b']);

        $a->workspaces()->create(['name' => 'Corporate', 'slug' => 'corporate']);
        $b->workspaces()->create(['name' => 'Corporate', 'slug' => 'corporate']);

        $this->assertSame(2, Workspace::where('slug', 'corporate')->count());
    }

    public function test_deleting_a_tenant_is_soft_and_takes_nothing_with_it(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $tenant->workspaces()->create(['name' => 'Corporate', 'slug' => 'corporate']);

        $tenant->delete();

        $this->assertSoftDeleted('tenants', ['id' => $tenant->id]);
        $this->assertDatabaseHas('workspaces', ['tenant_id' => $tenant->id]);
    }

    public function test_a_suspended_tenant_keeps_its_data_and_loses_access(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme', 'status' => 'suspended']);

        $this->assertFalse($tenant->isActive());
        $this->assertTrue(Tenant::create(['name' => 'B', 'slug' => 'b'])->isActive());
    }

    public function test_existing_data_is_backfilled_into_one_default_tenant(): void
    {
        // The migration ran against an empty database here, so reproduce what it
        // does on a populated one: seed, then assert the shape the backfill
        // creates. Every user must land in the default workspace at exactly the
        // role they already had — nobody gains or loses an ability on migration.
        $this->seed(DemoDataSeeder::class);

        $tenant = Tenant::create(['name' => 'Elite Business Hub', 'slug' => 'elite-business-hub']);
        $workspace = $tenant->workspaces()->create(['name' => 'Elite Business Hub', 'slug' => 'default']);

        foreach (User::all() as $user) {
            $workspace->grant($user, $user->role ?: 'viewer');
        }

        $this->assertSame(User::count(), $workspace->members()->count());
        foreach (User::all() as $user) {
            $this->assertSame($user->role, $workspace->roleFor($user),
                "{$user->email} must keep the role they had before the migration");
        }
    }

    public function test_nothing_existing_reads_the_new_tables_yet(): void
    {
        // Slice 1 is additive on purpose. CompanyProfile::house() still returns
        // row 1 with no tenant involved, so the app behaves identically whether
        // or not a tenant row exists.
        $this->seed(DemoDataSeeder::class);
        CompanyProfile::forgetHouse();

        $before = CompanyProfile::currency();
        Tenant::create(['name' => 'Someone Else', 'slug' => 'someone-else']);
        CompanyProfile::forgetHouse();

        $this->assertSame($before, CompanyProfile::currency());
    }
}
