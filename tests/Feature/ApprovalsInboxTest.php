<?php

namespace Tests\Feature;

use App\Livewire\ApprovalsInbox;
use App\Models\Event;
use App\Models\EventApproval;
use App\Models\User;
use App\Notifications\ApprovalDecided;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The inbox decides with the same ApprovalDecisions the event tab uses, so
 * everything the tab refuses, the inbox refuses too.
 */
class ApprovalsInboxTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role): User
    {
        return User::factory()->create(['role' => $role]);
    }

    private function request(User $by, string $title = 'Stage quote sign-off', ?Event $event = null): EventApproval
    {
        return ($event ?? Event::factory()->create())->approvals()->create([
            'title' => $title, 'type' => 'supplier', 'status' => 'pending', 'requested_by' => $by->id,
        ]);
    }

    public function test_it_lists_decisions_across_events_but_not_my_own_requests(): void
    {
        $manager = $this->person('manager');
        $coordinator = $this->person('coordinator');

        $this->request($coordinator, 'Stage quote sign-off');
        $this->request($coordinator, 'Hotel block release');
        $this->request($manager, 'My own request');

        $this->actingAs($manager)->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('Stage quote sign-off')
            ->assertSee('Hotel block release')
            ->assertDontSee('My own request');
    }

    public function test_approving_in_place_resolves_it_and_tells_the_requester(): void
    {
        Notification::fake();
        $manager = $this->person('manager');
        $requester = $this->person('coordinator');
        $approval = $this->request($requester);

        Livewire::actingAs($manager)->test(ApprovalsInbox::class)
            ->call('decide', $approval->id, 'approved')
            ->assertDispatched('approvals-toast', tone: 'ok')
            ->assertDontSee('Stage quote sign-off');

        $this->assertSame('approved', $approval->fresh()->status);
        Notification::assertSentTo($requester, ApprovalDecided::class);
    }

    public function test_request_changes_and_reject_are_recorded(): void
    {
        $manager = $this->person('manager');
        $revise = $this->request($this->person('coordinator'), 'Menu');
        $reject = $this->request($this->person('coordinator'), 'Fireworks');

        Livewire::actingAs($manager)->test(ApprovalsInbox::class)
            ->call('decide', $revise->id, 'needs_revision')
            ->call('decide', $reject->id, 'rejected');

        $this->assertSame('needs_revision', $revise->fresh()->status);
        $this->assertSame('rejected', $reject->fresh()->status);
    }

    public function test_it_refuses_my_own_request_just_as_the_event_tab_does(): void
    {
        $manager = $this->person('manager');
        $approval = $this->request($manager);

        Livewire::actingAs($manager)->test(ApprovalsInbox::class)
            ->call('decide', $approval->id, 'approved')
            ->assertDispatched('approvals-toast', tone: 'warn');

        $this->assertSame('pending', $approval->fresh()->status);
    }

    public function test_a_step_named_for_someone_else_is_neither_listed_nor_decidable(): void
    {
        $me = $this->person('manager');
        $named = $this->person('manager');
        $approval = $this->request($this->person('coordinator'), 'Named step');
        $approval->steps()->first()->update(['approver_id' => $named->id]);

        $this->actingAs($me)->get(route('approvals.index'))->assertDontSee('Named step');

        Livewire::actingAs($me)->test(ApprovalsInbox::class)
            ->call('decide', $approval->id, 'approved')
            ->assertDispatched('approvals-toast', tone: 'warn');
        $this->assertSame('pending', $approval->fresh()->status);

        $this->actingAs($named)->get(route('approvals.index'))->assertSee('Named step');
    }

    public function test_a_coordinator_sees_their_requests_and_cannot_decide(): void
    {
        $coordinator = $this->person('coordinator');
        $mine = $this->request($coordinator, 'My venue request');
        $other = $this->request($this->person('coordinator'), 'Someone elses request');

        $this->actingAs($coordinator)->get(route('approvals.index'))
            ->assertOk()
            ->assertSee('My venue request')
            ->assertDontSee('Someone elses request');

        Livewire::actingAs($coordinator)->test(ApprovalsInbox::class)
            ->assertSet('show', 'raised')
            ->call('decide', $other->id, 'approved')
            ->assertForbidden();
        $this->assertSame('pending', $other->fresh()->status);
    }

    public function test_the_dashboard_counts_what_the_inbox_lists_and_links_to_it(): void
    {
        $manager = $this->person('manager');
        $this->request($this->person('coordinator'));   // mine to decide
        $this->request($manager, 'Raised by me');         // not mine to decide

        $html = $this->actingAs($manager)->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString(route('approvals.index'), $html);
        $this->assertMatchesRegularExpression('/Pending Approvals.{0,600}?>\s*1\s*</s', $html);
    }
}
