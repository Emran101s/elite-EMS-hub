<?php

namespace Tests\Feature;

use App\Livewire\MyWork;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * My Work is one person's plate: their open tasks by when they are due, the
 * approvals only they can move, and the events they are on.
 */
class MyWorkTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'coordinator'): User
    {
        return User::factory()->create(['role' => $role]);
    }

    public function test_it_lists_only_my_open_tasks_bucketed_by_due_date(): void
    {
        $me = $this->person();
        $someoneElse = $this->person();
        $event = Event::factory()->create();

        Task::factory()->for($event)->create(['assignee_id' => $me->id, 'title' => 'Late one', 'status' => 'todo', 'due_on' => now()->subDays(2)]);
        Task::factory()->for($event)->create(['assignee_id' => $me->id, 'title' => 'Due today', 'status' => 'doing', 'due_on' => now()]);
        Task::factory()->for($event)->create(['assignee_id' => $me->id, 'title' => 'Already finished', 'status' => 'done', 'due_on' => now()]);
        Task::factory()->for($event)->create(['assignee_id' => $someoneElse->id, 'title' => 'Not mine', 'status' => 'todo', 'due_on' => now()]);

        $this->actingAs($me)->get(route('tasks.mine'))
            ->assertOk()
            ->assertSeeInOrder(['Overdue', 'Late one', 'Today', 'Due today'])
            ->assertDontSee('Already finished')
            ->assertDontSee('Not mine');
    }

    public function test_i_can_start_and_finish_my_own_task_in_place(): void
    {
        $me = $this->person();
        $task = Task::factory()->for(Event::factory())->create(['assignee_id' => $me->id, 'status' => 'todo']);

        Livewire::actingAs($me)->test(MyWork::class)->call('move', $task->id, 'doing');
        $this->assertSame('doing', $task->fresh()->status);

        Livewire::actingAs($me)->test(MyWork::class)->call('move', $task->id, 'done');
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_it_is_not_a_back_door_onto_other_peoples_tasks(): void
    {
        $me = $this->person();
        $theirs = Task::factory()->for(Event::factory())->create(['assignee_id' => $this->person()->id, 'status' => 'todo']);

        try {
            Livewire::actingAs($me)->test(MyWork::class)->call('move', $theirs->id, 'done');
            $this->fail('Moving somebody else\'s task should not find it.');
        } catch (ModelNotFoundException) {
            // Scoped to assignee_id — to this page the task does not exist.
        }

        $this->assertSame('todo', $theirs->fresh()->status);
    }

    public function test_a_task_awaiting_sign_off_cannot_be_closed_from_here(): void
    {
        $me = $this->person();
        $task = Task::factory()->for(Event::factory())->create(['assignee_id' => $me->id, 'status' => 'review']);

        Livewire::actingAs($me)->test(MyWork::class)->call('move', $task->id, 'done')->assertStatus(422);
        $this->assertSame('review', $task->fresh()->status);
    }

    public function test_only_start_and_done_are_offered(): void
    {
        $me = $this->person();
        $task = Task::factory()->for(Event::factory())->create(['assignee_id' => $me->id, 'status' => 'todo']);

        Livewire::actingAs($me)->test(MyWork::class)->call('move', $task->id, 'cancelled')->assertStatus(422);
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_a_viewer_cannot_change_anything(): void
    {
        $viewer = $this->person('viewer');
        $task = Task::factory()->for(Event::factory())->create(['assignee_id' => $viewer->id, 'status' => 'todo']);

        Livewire::actingAs($viewer)->test(MyWork::class)->call('move', $task->id, 'done')->assertForbidden();
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_a_manager_sees_approvals_they_can_decide_but_not_their_own(): void
    {
        $manager = $this->person('manager');
        $requester = $this->person();
        $event = Event::factory()->create();

        $event->approvals()->create(['title' => 'Stage quote sign-off', 'type' => 'supplier', 'status' => 'pending', 'requested_by' => $requester->id]);
        $event->approvals()->create(['title' => 'My own request', 'type' => 'client', 'status' => 'pending', 'requested_by' => $manager->id]);

        $this->actingAs($manager)->get(route('tasks.mine'))
            ->assertOk()
            ->assertSee('Waiting on you')
            ->assertSee('Stage quote sign-off')
            ->assertSee("You're waiting on", false)
            ->assertSee('My own request');
    }

    public function test_a_coordinator_is_not_shown_decisions_they_cannot_make(): void
    {
        $coordinator = $this->person();
        Event::factory()->create()->approvals()->create(['title' => 'Stage quote sign-off', 'type' => 'supplier', 'status' => 'pending']);

        $this->actingAs($coordinator)->get(route('tasks.mine'))
            ->assertOk()
            ->assertDontSee('Waiting on you')
            ->assertDontSee('Stage quote sign-off');
    }

    public function test_my_events_are_the_ones_i_am_on_the_team_of(): void
    {
        $me = $this->person();
        $mine = Event::factory()->create(['name' => 'Team Gala']);
        $mine->teamMembers()->attach($me->id, ['role' => 'operations_lead']);
        Event::factory()->create(['name' => 'Someone Elses Expo']);

        $this->actingAs($me)->get(route('tasks.mine'))
            ->assertSee('Team Gala')
            ->assertDontSee('Someone Elses Expo');
    }

    public function test_it_is_linked_from_the_tasks_panel(): void
    {
        $this->actingAs($this->person())->get(route('tasks.index'))
            ->assertOk()
            ->assertSee(route('tasks.mine'), false);
    }
}
