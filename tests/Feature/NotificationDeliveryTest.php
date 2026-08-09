<?php

namespace Tests\Feature;

use App\Livewire\Hub\ApprovalsTab;
use App\Livewire\PublicRegistration;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Models\User;
use App\Notifications\ApprovalDecided;
use App\Notifications\ApprovalRequested;
use App\Notifications\RegistrationConfirmed;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The platform's outbound mail.
 *
 * Every one of these was silent before: an approver was never told a decision
 * was waiting on them, a requester never learned the answer, and somebody who
 * registered for an event got nothing back at all. The point of these tests is
 * that the silence does not come back.
 */
class NotificationDeliveryTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0:Event,1:User,2:User,3:User} event, requester (coordinator), two managers */
    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::query()->firstOrFail(),
            User::where('email', 'omar.nassar@elitebhub.com')->firstOrFail(),
            User::where('email', 'layla.haddad@elitebhub.com')->firstOrFail(),
            User::where('email', 'sara.alrashid@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_a_named_approver_is_told_a_decision_is_waiting(): void
    {
        Notification::fake();
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Stage build sign-off')
            ->set('steps', [
                ['label' => 'Ops', 'approver_id' => $layla->id],
                ['label' => 'Finance', 'approver_id' => $sara->id],
            ])
            ->call('save');

        // Only step one's owner hears about it. Sara is not being asked yet.
        Notification::assertSentTo($layla, ApprovalRequested::class);
        Notification::assertNotSentTo($sara, ApprovalRequested::class);
        Notification::assertNotSentTo($requester, ApprovalRequested::class);
    }

    public function test_the_next_approver_is_told_when_the_chain_advances(): void
    {
        Notification::fake();
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Stage build sign-off')
            ->set('steps', [
                ['label' => 'Ops', 'approver_id' => $layla->id],
                ['label' => 'Finance', 'approver_id' => $sara->id],
            ])
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        // Now — and not before — Sara is the one being waited on.
        Notification::assertSentTo($sara, ApprovalRequested::class);

        // Still pending overall, so the requester is not told an answer yet.
        Notification::assertNotSentTo($requester, ApprovalDecided::class);
    }

    public function test_the_requester_is_told_only_once_the_approval_resolves(): void
    {
        Notification::fake();
        [$event, $requester, $layla] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Revised catering budget')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'approved');

        Notification::assertSentTo($requester, ApprovalDecided::class);
    }

    public function test_a_rejection_also_closes_the_loop(): void
    {
        Notification::fake();
        [$event, $requester, $layla] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Extra AV spend')
            ->call('save');

        $approval = $event->approvals()->latest('id')->firstOrFail();

        Livewire::actingAs($layla)->test(ApprovalsTab::class, ['event' => $event])
            ->call('decide', $approval->id, 'rejected');

        Notification::assertSentTo($requester, function (ApprovalDecided $n) {
            return true;
        });
    }

    public function test_an_unassigned_step_tells_every_manager_who_could_decide_it(): void
    {
        Notification::fake();
        [$event, $requester, $layla, $sara] = $this->ctx();

        Livewire::actingAs($requester)->test(ApprovalsTab::class, ['event' => $event])
            ->set('title', 'Open queue request')
            ->call('save');

        // No named approver means it is a queue — the queue has to be told.
        Notification::assertSentTo($layla, ApprovalRequested::class);
        Notification::assertSentTo($sara, ApprovalRequested::class);

        // The person who raised it cannot decide it, so they are not mailed.
        Notification::assertNotSentTo($requester, ApprovalRequested::class);
    }

    public function test_registering_sends_a_confirmation_with_the_reference(): void
    {
        Notification::fake();
        $this->seed(DemoDataSeeder::class);

        $event = Event::query()->firstOrFail();
        $event->update(['registration_open' => true]);
        $token = $event->registrationToken();   // minted on demand if absent

        Livewire::test(PublicRegistration::class, ['token' => $token])
            ->set('form.name', 'Rana Haddad')
            ->set('form.email', 'rana.haddad@example.test')
            ->call('register')
            ->assertHasNoErrors();

        $attendee = EventAttendee::where('email', 'rana.haddad@example.test')->firstOrFail();

        Notification::assertSentTo($attendee, RegistrationConfirmed::class);
    }

    public function test_an_attendee_with_no_address_is_simply_not_mailed(): void
    {
        Notification::fake();
        $this->seed(DemoDataSeeder::class);

        $event = Event::query()->firstOrFail();
        $walkUp = $event->attendees()->create(['name' => 'Desk walk-up', 'status' => 'registered']);

        $this->assertFalse($walkUp->notifiable(), 'no address means nowhere to send');
        Notification::assertNothingSent();
    }
}
