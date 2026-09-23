<?php

namespace Tests\Feature;

use App\Livewire\TasksIndex;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The global Tasks list: filter it, then work it — one row or a selection —
 * behind the same `write` gate as the event board.
 */
class TasksIndexTest extends TestCase
{
    use RefreshDatabase;

    private function person(string $role = 'coordinator', ?string $name = null): User
    {
        return User::factory()->create(array_filter(['role' => $role, 'name' => $name]));
    }

    public function test_old_status_links_still_filter_the_list(): void
    {
        $event = Event::factory()->create();
        Task::factory()->for($event)->create(['title' => 'Todo task', 'status' => 'todo']);
        Task::factory()->for($event)->create(['title' => 'Finished task', 'status' => 'done']);

        $this->actingAs($this->person())->get(route('tasks.index', ['status' => 'todo']))
            ->assertOk()
            ->assertSee('Todo task')
            ->assertDontSee('Finished task');
    }

    public function test_filters_narrow_by_owner_event_due_date_and_search(): void
    {
        $me = $this->person();
        $gala = Event::factory()->create();
        $expo = Event::factory()->create();

        Task::factory()->for($gala)->create(['title' => 'Mine on gala', 'assignee_id' => $me->id, 'status' => 'todo', 'due_on' => now()->subDay()]);
        Task::factory()->for($expo)->create(['title' => 'Nobodys on expo', 'assignee_id' => null, 'status' => 'todo', 'due_on' => null]);

        $list = Livewire::actingAs($me)->test(TasksIndex::class);

        $list->set('who', 'me')->assertSee('Mine on gala')->assertDontSee('Nobodys on expo');
        $list->set('who', 'unassigned')->assertSee('Nobodys on expo')->assertDontSee('Mine on gala');
        $list->set('who', '')->set('event', (string) $gala->id)->assertSee('Mine on gala')->assertDontSee('Nobodys on expo');
        $list->set('event', '')->set('due', 'overdue')->assertSee('Mine on gala')->assertDontSee('Nobodys on expo');
        $list->set('due', 'none')->assertSee('Nobodys on expo')->assertDontSee('Mine on gala');
        $list->set('due', '')->set('q', 'expo')->assertSee('Nobodys on expo')->assertDontSee('Mine on gala');
    }

    public function test_a_row_changes_status_and_owner_in_place(): void
    {
        $me = $this->person();
        $khalid = $this->person('coordinator', 'Khalid Mansour');
        $task = Task::factory()->for(Event::factory())->create(['status' => 'todo', 'assignee_id' => null]);

        Livewire::actingAs($me)->test(TasksIndex::class)
            ->call('setStatus', $task->id, 'doing')
            ->call('assign', $task->id, (string) $khalid->id);

        $this->assertSame('doing', $task->fresh()->status);
        $this->assertSame($khalid->id, $task->fresh()->assignee_id);

        Livewire::actingAs($me)->test(TasksIndex::class)->call('assign', $task->id, '');
        $this->assertNull($task->fresh()->assignee_id);
    }

    public function test_a_selection_moves_and_reassigns_together(): void
    {
        $me = $this->person();
        $dana = $this->person('coordinator', 'Dana Qasem');
        [$a, $b, $untouched] = Task::factory()->count(3)->for(Event::factory())->create(['status' => 'todo'])->all();

        Livewire::actingAs($me)->test(TasksIndex::class)
            ->call('toggleSelect', $a->id)
            ->call('toggleSelect', $b->id)
            ->call('bulkStatus', 'done')
            ->assertDispatched('tasks-toast')
            ->assertSet('selected', []);

        $this->assertSame(['done', 'done', 'todo'], [$a->fresh()->status, $b->fresh()->status, $untouched->fresh()->status]);

        Livewire::actingAs($me)->test(TasksIndex::class)
            ->call('selectMany', [$a->id, $b->id])
            ->call('bulkAssign', (string) $dana->id);

        $this->assertSame([$dana->id, $dana->id], [$a->fresh()->assignee_id, $b->fresh()->assignee_id]);
        $this->assertNotSame($dana->id, $untouched->fresh()->assignee_id);
    }

    public function test_an_unknown_status_is_refused(): void
    {
        $task = Task::factory()->for(Event::factory())->create(['status' => 'todo']);

        Livewire::actingAs($this->person())->test(TasksIndex::class)
            ->call('setStatus', $task->id, 'shipped')
            ->assertStatus(422);
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_a_viewer_can_read_but_not_change_anything(): void
    {
        $viewer = $this->person('viewer');
        $task = Task::factory()->for(Event::factory())->create(['title' => 'Read only task', 'status' => 'todo']);

        $this->actingAs($viewer)->get(route('tasks.index'))
            ->assertOk()
            ->assertSee('Read only task')
            ->assertDontSee('status-'.$task->id);

        Livewire::actingAs($viewer)->test(TasksIndex::class)->call('setStatus', $task->id, 'done')->assertForbidden();
        Livewire::actingAs($viewer)->test(TasksIndex::class)->call('toggleSelect', $task->id)->call('bulkStatus', 'done')->assertForbidden();
        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_the_workflow_strip_counts_within_the_other_filters(): void
    {
        $me = $this->person();
        $event = Event::factory()->create();
        Task::factory()->for($event)->count(2)->create(['assignee_id' => $me->id, 'status' => 'todo']);
        Task::factory()->for($event)->count(5)->create(['assignee_id' => null, 'status' => 'todo']);

        Livewire::actingAs($me)->test(TasksIndex::class)
            ->set('who', 'me')
            ->assertViewHas('counts', fn ($counts) => $counts['todo'] === 2);
    }
}
