<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Slice 1 of the tenancy retrofit: the spine, and nothing else.
 *
 * The platform is single-tenant today — CompanyProfile is a memoised singleton
 * returning row 1, and users.role is one global string, so "a different role
 * per workspace" cannot be expressed at all. This adds the three tables that
 * make multi-tenancy possible without changing a single existing behaviour.
 *
 * Deliberately NOT in this migration:
 *   - tenant_id on the other 71 tables (slice 2)
 *   - the global scope that enforces isolation (slice 3)
 *   - moving role off users (slice 4)
 *
 * Everything existing keeps working because every row in the database is
 * backfilled into one default tenant and one default workspace. users.role
 * stays authoritative; workspace_user.role is written alongside it so slice 4
 * has data to switch over to. Dual-write now, cut over later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();

            // Billing lands in Q3; the column exists now so nothing has to
            // rewrite the table to add it later.
            $table->string('status')->default('active');   // active | trialing | suspended
            $table->timestamp('trial_ends_at')->nullable();

            $table->timestamps();

            // A tenant carries every event, contract and attendee its customer
            // owns. Removing one must never be a single mistyped command.
            $table->softDeletes();
        });

        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('slug');
            $table->timestamps();

            // Slugs are unique per tenant, not globally — two agencies may both
            // have a "corporate" workspace and neither should block the other.
            $table->unique(['tenant_id', 'slug']);
        });

        Schema::create('workspace_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // The role lives here, not on users. This is the whole point: one
            // person can be a manager on one workspace and a viewer on another.
            $table->string('role');

            $table->timestamps();
            $table->unique(['workspace_id', 'user_id']);
            $table->index(['user_id', 'workspace_id']);   // "my workspaces"
        });

        Schema::table('company_profiles', function (Blueprint $table) {
            $table->foreignId('tenant_id')->nullable()->after('id')
                ->constrained()->nullOnDelete();
        });

        $this->backfillDefaultTenant();
    }

    /**
     * Everything that exists today belongs to one customer. Give that customer
     * a real row so the rest of the retrofit has something to point at.
     *
     * Skipped entirely on an empty database (a fresh install or a test using
     * RefreshDatabase) — seeding a tenant nobody asked for would leave every
     * test asserting against a row it did not create.
     */
    private function backfillDefaultTenant(): void
    {
        $profile = DB::table('company_profiles')->first();
        $userIds = DB::table('users')->pluck('role', 'id');

        if (! $profile && $userIds->isEmpty()) {
            return;
        }

        $name = $profile->name ?? 'Elite Business Hub';
        $now = now();

        $tenantId = DB::table('tenants')->insertGetId([
            'name' => $name,
            'slug' => Str::slug($name) ?: 'default',
            'status' => 'active',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $workspaceId = DB::table('workspaces')->insertGetId([
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => 'default',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        // Membership mirrors users.role exactly, so nobody gains or loses an
        // ability at the moment this runs. users.role stays the source of truth
        // until the gates are rewritten in slice 4.
        foreach ($userIds as $id => $role) {
            DB::table('workspace_user')->insert([
                'workspace_id' => $workspaceId,
                'user_id' => $id,
                'role' => $role ?: 'viewer',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        if ($profile) {
            DB::table('company_profiles')->where('id', $profile->id)
                ->update(['tenant_id' => $tenantId]);
        }
    }

    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropConstrainedForeignId('tenant_id');
        });

        Schema::dropIfExists('workspace_user');
        Schema::dropIfExists('workspaces');
        Schema::dropIfExists('tenants');
    }
};
