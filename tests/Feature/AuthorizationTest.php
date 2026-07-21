<?php

namespace Tests\Feature;

use App\Livewire\Hub\ApprovalsTab;
use App\Livewire\Hub\BudgetTab;
use App\Livewire\Hub\TasksTab;
use App\Livewire\TeamRoster;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),   // super_admin
        ];
    }

    private function user(string $role): User
    {
        return User::create([
            'name' => ucfirst($role).' Person', 'email' => $role.'@ebh.test',
            'password' => bcrypt('x'), 'role' => $role,
        ]);
    }

    public function test_a_viewer_is_read_only(): void
    {
        [$event] = $this->ctx();
        $viewer = $this->user('viewer');
        $task = $event->tasks()->create(['title' => 'Untouchable', 'status' => 'todo']);

        Livewire::actingAs($viewer)->test(TasksTab::class, ['event' => $event])
            ->call('moveTask', $task->id, 'doing')
            ->assertForbidden();

        $this->assertSame('todo', $task->fresh()->status, 'a viewer must not change anything');
    }

    public function test_a_coordinator_works_but_cannot_decide_approvals(): void
    {
        [$event] = $this->ctx();
        $coordinator = $this->user('coordinator');
        $task = $event->tasks()->create(['title' => 'Day job', 'status' => 'todo']);

        // Coordinators do the work…
        Livewire::actingAs($coordinator)->test(TasksTab::class, ['event' => $event])
            ->call('moveTask', $task->id, 'doing');
        $this->assertSame('doing', $task->fresh()->status);

        // …but they don't sign it off.
        $approval = $event->approvals()->create(['title' => 'Sign-off', 'type' => 'client', 'status' => 'pending']);

        Livewire::actingAs($coordinator)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved')
            ->assertForbidden();
        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_nobody_decides_their_own_approval_request(): void
    {
        [$event] = $this->ctx();
        $manager = $this->user('manager');
        $approval = $event->approvals()->create([
            'title' => 'My own request', 'type' => 'client', 'status' => 'pending',
            'requested_by' => $manager->id,
        ]);

        Livewire::actingAs($manager)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        // Refused — still pending, and no decider recorded.
        $this->assertSame('pending', $approval->fresh()->status);
        $this->assertNull($approval->fresh()->decided_by);

        // A different manager may decide it.
        $other = $this->user('admin');
        Livewire::actingAs($other)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_only_managers_approve_budgets(): void
    {
        [$event] = $this->ctx();
        $coordinator = $this->user('coordinator');

        Livewire::actingAs($coordinator)->test(BudgetTab::class, ['event' => $event])
            ->call('approveBudget')
            ->assertForbidden();
    }

    public function test_only_admins_manage_the_team(): void
    {
        [$event] = $this->ctx();
        $manager = $this->user('manager');

        $victim = User::where('role', 'coordinator')->first();

        Livewire::actingAs($manager)->test(TeamRoster::class)
            ->call('delete', $victim->id)
            ->assertForbidden();
        $this->assertNotNull($victim->fresh());
    }
}
