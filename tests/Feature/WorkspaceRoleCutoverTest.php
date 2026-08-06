<?php

namespace Tests\Feature;

use App\Livewire\TeamRoster;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Slice 5: isAtLeast() reads workspace_user.role, not users.role — with a
 * single implicit workspace per tenant (no switcher, none planned until a
 * real customer needs a second — see the AskUserQuestion decision this slice
 * was built against).
 *
 * The test that matters is the first one: it constructs a user whose two role
 * fields DISAGREE and asserts which one wins. Every other test in this file
 * would pass even if isAtLeast() silently kept reading the old column —
 * matching values prove nothing. Divergent values are the only proof the
 * cutover actually happened rather than users.role and workspace_user.role
 * coincidentally agreeing.
 */
class WorkspaceRoleCutoverTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Tenant,1:User} a user with a workspace whose pivot role differs from users.role */
    private function userWithDivergentRoles(string $columnRole, string $pivotRole): array
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace = $tenant->workspaces()->create(['name' => 'Acme', 'slug' => 'default']);
        $user = User::factory()->create(['tenant_id' => $tenant->id, 'role' => $columnRole]);
        $workspace->grant($user, $pivotRole);

        return [$tenant, $user];
    }

    public function test_isatleast_reads_the_workspace_role_not_the_column_when_they_disagree(): void
    {
        // users.role says viewer (the lowest rank); workspace_user.role says
        // admin (near the top). If isAtLeast() is still reading the column,
        // this user fails an admin check. If the cutover is real, they pass.
        [, $user] = $this->userWithDivergentRoles(columnRole: 'viewer', pivotRole: 'admin');

        $this->assertTrue($user->isAtLeast('admin'), 'isAtLeast() must read workspace_user.role, not users.role');
        $this->assertTrue($user->isAtLeast('manager'));
    }

    public function test_the_reverse_also_proves_it_a_high_column_role_does_not_leak_through(): void
    {
        // The opposite disagreement: users.role says admin, workspace_user
        // says viewer. If isAtLeast() were reading the column (or taking the
        // max of both), this would wrongly pass an admin check.
        [, $user] = $this->userWithDivergentRoles(columnRole: 'admin', pivotRole: 'viewer');

        $this->assertFalse($user->isAtLeast('coordinator'), 'a stale users.role must not grant access the workspace role denies');
    }

    public function test_a_user_with_no_workspace_falls_back_to_the_column_rather_than_being_denied_everything(): void
    {
        // The defensive path: activeWorkspace() returns null (no membership
        // row at all), and isAtLeast() must degrade to users.role rather than
        // treat every such user as rank zero. This is the shape every
        // pre-tenancy test fixture in this codebase is in today.
        $user = User::factory()->create(['role' => 'manager']);

        $this->assertNull($user->activeWorkspace());
        $this->assertTrue($user->isAtLeast('manager'));
        $this->assertFalse($user->isAtLeast('admin'));
    }

    public function test_creating_a_team_member_grants_them_the_tenants_workspace_at_the_chosen_role(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace = $tenant->workspaces()->create(['name' => 'Acme', 'slug' => 'default']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $workspace->grant($admin, 'admin');

        Livewire::actingAs($admin)->test(TeamRoster::class)
            ->set('name', 'New Coordinator')->set('email', 'nc@acme.test')
            ->set('role', 'manager')->call('save')->assertHasNoErrors();

        $created = User::where('email', 'nc@acme.test')->firstOrFail();
        $this->assertSame('manager', $workspace->roleFor($created), 'workspace_user must carry the role TeamRoster set');
        $this->assertTrue($created->isAtLeast('manager'), 'the new member must actually be able to act as one, not just have the column say so');
    }

    public function test_editing_a_members_role_updates_the_workspace_grant_not_just_the_column(): void
    {
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace = $tenant->workspaces()->create(['name' => 'Acme', 'slug' => 'default']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $workspace->grant($admin, 'admin');
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'coordinator']);
        $workspace->grant($member, 'coordinator');

        Livewire::actingAs($admin)->test(TeamRoster::class)
            ->call('edit', $member->id)
            ->set('role', 'manager')
            ->call('save')->assertHasNoErrors();

        $this->assertSame('manager', $workspace->roleFor($member->fresh()));
        $this->assertTrue($member->fresh()->isAtLeast('manager'));
    }

    public function test_removing_a_team_member_removes_their_workspace_grant_too(): void
    {
        // Enforced by the foreign key (cascadeOnDelete on workspace_user.user_id
        // from slice 1), not by application code — this proves the constraint
        // actually does what its comment says, against a real delete.
        $tenant = Tenant::create(['name' => 'Acme', 'slug' => 'acme']);
        $workspace = $tenant->workspaces()->create(['name' => 'Acme', 'slug' => 'default']);
        $admin = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'admin']);
        $workspace->grant($admin, 'admin');
        $member = User::factory()->create(['tenant_id' => $tenant->id, 'role' => 'coordinator']);
        $workspace->grant($member, 'coordinator');

        Livewire::actingAs($admin)->test(TeamRoster::class)->call('delete', $member->id);

        $this->assertDatabaseMissing('workspace_user', ['user_id' => $member->id]);
    }
}
