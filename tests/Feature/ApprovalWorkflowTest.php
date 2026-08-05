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
}
