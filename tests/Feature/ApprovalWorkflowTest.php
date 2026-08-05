<?php

namespace Tests\Feature;

use App\Livewire\Hub\ApprovalsTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ApprovalWorkflowTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User,2:User,3:User} coordinator (requester), and two managers */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::query()->firstOrFail(),
            User::where('email', 'omar.nassar@elitebhub.com')->firstOrFail(), // coordinator — cannot decide
            User::where('email', 'layla.haddad@elitebhub.com')->firstOrFail(), // manager
            User::where('email', 'sara.alrashid@elitebhub.com')->firstOrFail(), // manager
        ];
    }

    public function test_a_default_single_step_request_behaves_like_before(): void
    {
        [$event, $requester, $manager] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Revised catering budget')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();
        $this->assertCount(1, $approval->steps);
        $this->assertNull($approval->steps->first()->approver_id, 'unassigned means any manager, same as before');

        Livewire::actingAs($manager)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_a_chain_requires_every_step_to_approve(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'New sponsor contract')
            ->set('steps', [
                ['label' => 'Ops', 'approver_id' => $layla->id],
                ['label' => 'Finance', 'approver_id' => $sara->id],
            ])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();
        $this->assertCount(2, $approval->steps);
        $this->assertSame('pending', $approval->status);

        // Sara can't jump ahead — the current step is Layla's.
        Livewire::actingAs($sara)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $this->assertSame('pending', $approval->fresh()->status, "Sara isn't the current step, so nothing should move");
        $this->assertSame('pending', $approval->fresh()->steps->firstWhere('label', 'Finance')->status);

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $approval->refresh();
        $this->assertSame('pending', $approval->status, 'one of two steps is not enough');
        $this->assertSame('approved', $approval->steps->firstWhere('label', 'Ops')->status);
        $this->assertSame('pending', $approval->steps->firstWhere('label', 'Finance')->status);

        Livewire::actingAs($sara)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_rejecting_a_step_stops_the_chain_and_skips_the_rest(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'New sponsor contract')
            ->set('steps', [
                ['label' => 'Ops', 'approver_id' => $layla->id],
                ['label' => 'Finance', 'approver_id' => $sara->id],
            ])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'rejected');

        $approval->refresh();
        $this->assertSame('rejected', $approval->status);
        $this->assertSame('skipped', $approval->steps->firstWhere('label', 'Finance')->status);
        $this->assertNull($approval->currentStep(), 'a rejected chain has nothing left awaiting a decision');
    }

    public function test_a_step_assigned_to_someone_specific_is_not_theirs_to_decide(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Venue change')
            ->set('steps', [['label' => 'Finance', 'approver_id' => $sara->id]])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        $this->assertSame('pending', $approval->fresh()->status, "Layla isn't Sara — the step must stay untouched");
    }

    public function test_the_requester_still_cannot_decide_their_own_request_mid_chain(): void
    {
        [$event, $requester, $layla] = $this->ctx();
        $requesterAsManager = User::where('email', 'sara.alrashid@elitebhub.com')->firstOrFail();

        Livewire::actingAs($requesterAsManager)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Self-requested change')
            ->set('steps', [['label' => '', 'approver_id' => '']])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($requesterAsManager)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_a_coordinator_cannot_decide_any_step(): void
    {
        [$event, $requester] = $this->ctx();
        $otherCoordinator = User::where('email', 'khalid.mansour@elitebhub.com')->firstOrFail();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Something')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($otherCoordinator)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved')
            ->assertForbidden();
    }

    public function test_removing_a_step_leaves_at_least_one(): void
    {
        [$event, $requester] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->call('removeStep', 0)
            ->assertCount('steps', 1);
    }

    public function test_you_cannot_assign_a_step_to_yourself(): void
    {
        [$event, , , $sara] = $this->ctx();

        Livewire::actingAs($sara)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Stuck if self-assigned')
            ->set('steps', [['label' => 'Me', 'approver_id' => $sara->id]])
            ->call('save')
            ->assertHasErrors(['steps.0.approver_id']);

        $this->assertNull($event->approvals()->where('title', 'Stuck if self-assigned')->first());
    }

    public function test_needs_revision_stops_the_chain_and_skips_the_rest(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Concept to revise')
            ->set('steps', [
                ['label' => 'Ops', 'approver_id' => $layla->id],
                ['label' => 'Finance', 'approver_id' => $sara->id],
            ])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'needs_revision');

        $approval->refresh();
        $this->assertSame('needs_revision', $approval->status);
        $this->assertSame('skipped', $approval->steps->firstWhere('label', 'Finance')->status);
    }

    // ── Conditional routing: an amount over the house threshold escalates ──

    private function admin(): User
    {
        // super_admin outranks admin, so it satisfies an "at least admin" step too.
        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_escalation_is_off_by_default(): void
    {
        [$event, $requester] = $this->ctx();
        \App\Models\CompanyProfile::current()->update(['approval_threshold_cents' => null]);

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Huge budget line')
            ->set('type', 'budget')
            ->set('amount', '500000')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();
        $this->assertCount(1, $approval->steps, 'no threshold configured means no escalation, however large the amount');
    }

    public function test_a_budget_request_under_threshold_does_not_escalate(): void
    {
        [$event, $requester] = $this->ctx();
        \App\Models\CompanyProfile::current()->update(['approval_threshold_cents' => 10_000_00]);

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Small budget line')
            ->set('type', 'budget')
            ->set('amount', '500')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();
        $this->assertCount(1, $approval->steps);
    }

    public function test_a_budget_request_over_threshold_gets_an_automatic_admin_step(): void
    {
        [$event, $requester, $layla] = $this->ctx();
        \App\Models\CompanyProfile::current()->update(['approval_threshold_cents' => 10_000_00]);

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Large budget revision')
            ->set('type', 'budget')
            ->set('amount', '15000')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();
        $this->assertCount(2, $approval->steps, 'nobody configured a second step — the threshold did');
        $this->assertSame('admin', $approval->steps->last()->min_role);
        $this->assertNull($approval->steps->last()->approver_id);

        // A manager decides step 1 as usual…
        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $approval->refresh();
        $this->assertSame('pending', $approval->status, 'the admin step is still owed');

        // …but cannot decide the admin step, even though they are a manager.
        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $this->assertSame('pending', $approval->fresh()->status, "a manager isn't an admin");

        // An admin (or above) can.
        Livewire::actingAs($this->admin())->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_a_venue_request_never_escalates_regardless_of_amount(): void
    {
        [$event, $requester] = $this->ctx();
        \App\Models\CompanyProfile::current()->update(['approval_threshold_cents' => 100]);

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Venue change')
            ->set('type', 'venue')
            ->set('amount', '999999')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();
        $this->assertCount(1, $approval->steps, 'venue is not an amount-gated type');
    }

    // ── Delegate-to: hand a pending step to another eligible manager ──

    public function test_assignee_can_hand_their_step_to_another_manager(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Needs a hand-off')
            ->set('steps', [['label' => 'Ops', 'approver_id' => $layla->id]])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('startDelegate', $approval->id)
            ->set('delegateTo', (string) $sara->id)
            ->call('delegate', $approval->id);

        $step = $approval->fresh()->currentStep();
        $this->assertSame($sara->id, $step->approver_id);
        $this->assertSame('pending', $approval->fresh()->status);

        // Layla no longer owns it — Sara does.
        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $this->assertSame('pending', $approval->fresh()->status);

        Livewire::actingAs($sara)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');
        $this->assertSame('approved', $approval->fresh()->status);
    }

    public function test_a_manager_cannot_hand_off_someone_elses_named_step(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Sara owns this')
            ->set('steps', [['label' => 'Finance', 'approver_id' => $sara->id]])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('startDelegate', $approval->id)
            ->set('delegateTo', (string) $this->admin()->id)
            ->call('delegate', $approval->id);

        $this->assertSame($sara->id, $approval->fresh()->currentStep()->approver_id, 'Layla must not steal Sara’s step');
    }

    public function test_admin_can_reassign_a_stuck_named_step(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Stuck on Layla')
            ->set('steps', [['label' => 'Ops', 'approver_id' => $layla->id]])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($this->admin())->test(ApprovalsTab::class, ['event' => $event])
            ->call('startDelegate', $approval->id)
            ->set('delegateTo', (string) $sara->id)
            ->call('delegate', $approval->id);

        $this->assertSame($sara->id, $approval->fresh()->currentStep()->approver_id);
    }

    public function test_cannot_hand_a_step_to_the_requester(): void
    {
        [$event, $requester, $layla] = $this->ctx();
        $requesterAsManager = User::where('email', 'sara.alrashid@elitebhub.com')->firstOrFail();

        Livewire::actingAs($requesterAsManager)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Self-request hand-off trap')
            ->set('steps', [['label' => 'Ops', 'approver_id' => $layla->id]])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('startDelegate', $approval->id)
            ->set('delegateTo', (string) $requesterAsManager->id)
            ->call('delegate', $approval->id)
            ->assertHasErrors(['delegateTo']);

        $this->assertSame($layla->id, $approval->fresh()->currentStep()->approver_id);
    }

    public function test_admin_step_can_only_be_handed_to_an_admin(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();
        \App\Models\CompanyProfile::current()->update(['approval_threshold_cents' => 10_000_00]);

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Large budget needing admin')
            ->set('type', 'budget')
            ->set('amount', '15000')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        // Current step is the auto admin step — a manager target must be rejected.
        Livewire::actingAs($this->admin())->test(ApprovalsTab::class, ['event' => $event])
            ->call('startDelegate', $approval->id)
            ->set('delegateTo', (string) $sara->id)
            ->call('delegate', $approval->id)
            ->assertHasErrors(['delegateTo']);

        $this->assertNull($approval->fresh()->currentStep()->approver_id);
        $this->assertSame('admin', $approval->fresh()->currentStep()->min_role);
    }

    public function test_coordinator_cannot_delegate(): void
    {
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Coordinator try')
            ->set('steps', [['label' => 'Ops', 'approver_id' => $layla->id]])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->call('startDelegate', $approval->id)
            ->set('delegateTo', (string) $sara->id)
            ->call('delegate', $approval->id)
            ->assertForbidden();
    }
}
