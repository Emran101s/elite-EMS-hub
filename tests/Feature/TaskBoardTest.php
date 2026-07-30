<?php

namespace Tests\Feature;

use App\Livewire\Hub\TasksTab;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TaskBoardTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $event->tasks()->delete();

        return [$event, User::where('email', 'emran.itan@elitebhub.com')->firstOrFail()];
    }

    public function test_the_board_has_six_lanes_covering_every_status(): void
    {
        [$event, $user] = $this->ctx();

        $lanes = Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->viewData('lanes');

        $this->assertSame(Task::statuses(), $lanes->pluck('status')->all());
        $this->assertCount(6, $lanes);
    }

    public function test_quick_add_drops_a_task_into_a_lane(): void
    {
        [$event, $user] = $this->ctx();

        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('quickAdd', 'doing', '  Book the crane  ')
            ->assertDispatched('task-toast')
            ->assertNoRedirect();   // instant, no page reload

        $task = $event->tasks()->firstOrFail();
        $this->assertSame('Book the crane', $task->title);   // trimmed
        $this->assertSame('doing', $task->status);
    }

    public function test_dragging_moves_a_task_and_stays_in_place(): void
    {
        [$event, $user] = $this->ctx();
        $task = $event->tasks()->create(['title' => 'Move me', 'status' => 'todo']);

        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('moveTask', $task->id, 'review')
            ->assertNoRedirect();

        $this->assertSame('review', $task->fresh()->status);
    }

    public function test_advance_steps_forward_and_stops_at_terminal_stages(): void
    {
        [$event, $user] = $this->ctx();
        $task = $event->tasks()->create(['title' => 'Flow', 'status' => 'todo']);
        $c = Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event]);

        $c->call('advance', $task->id);
        $this->assertSame('doing', $task->fresh()->status);

        // done is terminal — advancing does nothing.
        $task->update(['status' => 'done']);
        $c->call('advance', $task->id);
        $this->assertSame('done', $task->fresh()->status);
    }

    public function test_the_detail_panel_edits_and_the_checklist_works(): void
    {
        [$event, $user] = $this->ctx();
        $task = $event->tasks()->create(['title' => 'Rich', 'status' => 'todo']);

        $c = Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('openTask', $task->id)
            ->assertSet('openId', $task->id)
            ->call('patch', 'priority', 'urgent')
            ->call('patch', 'area', 'venue')
            ->set('checkText', 'Sign the contract')
            ->call('addCheckItem')
            ->call('toggleCheckItem', 0);

        $task->refresh();
        $this->assertSame('urgent', $task->priority);
        $this->assertSame('venue', $task->area);
        $this->assertSame([1, 1], $task->checklistProgress());
        $this->assertTrue($task->checklist[0]['done']);
    }

    public function test_patch_rejects_unknown_fields_and_bad_values(): void
    {
        [$event, $user] = $this->ctx();
        $task = $event->tasks()->create(['title' => 'Guarded', 'status' => 'todo']);

        Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('openTask', $task->id)
            ->call('patch', 'status', 'done')   // status is not a patchable field
            ->assertStatus(422);

        $this->assertSame('todo', $task->fresh()->status);
    }

    public function test_focus_and_department_filters_narrow_the_lanes(): void
    {
        [$event, $user] = $this->ctx();
        $event->tasks()->create(['title' => 'Mine venue', 'status' => 'todo', 'assignee_id' => $user->id, 'area' => 'venue']);
        $event->tasks()->create(['title' => 'Theirs marketing', 'status' => 'todo', 'area' => 'marketing']);

        $c = Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event]);

        $count = fn () => $c->viewData('lanes')->firstWhere('status', 'todo')['tasks']->count();

        $c->call('setFocus', 'mine');
        $this->assertSame(1, $count());

        $c->call('setFocus', 'all')->call('filterByArea', 'marketing');
        $this->assertSame(1, $count());
    }

    public function test_only_writers_may_change_the_board(): void
    {
        [$event] = $this->ctx();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test', 'password' => bcrypt('x'), 'role' => 'viewer']);
        $task = $event->tasks()->create(['title' => 'Locked', 'status' => 'todo']);

        Livewire::actingAs($viewer)->test(TasksTab::class, ['event' => $event])
            ->call('moveTask', $task->id, 'doing')
            ->assertForbidden();

        $this->assertSame('todo', $task->fresh()->status);
    }

    /**
     * In List view a row's checklist expands in place. The list owns which row
     * is open, so opening one closes the last — otherwise a long list turns
     * into a wall of nested rows you have to scroll past to reach the next task.
     */
    public function test_the_list_expands_one_row_at_a_time(): void
    {
        [$event, $user] = $this->ctx();

        $a = $event->tasks()->create(['title' => 'Confirm the stage build', 'status' => 'todo', 'priority' => 'normal', 'area' => 'venue',
            'checklist' => [['text' => 'Measure the room', 'done' => false]]]);
        $b = $event->tasks()->create(['title' => 'Brief the AV crew', 'status' => 'todo', 'priority' => 'normal', 'area' => 'venue',
            'checklist' => [['text' => 'Send the cue sheet', 'done' => false]]]);

        $html = Livewire::actingAs($user)->test(TasksTab::class, ['event' => $event])
            ->call('setView', 'list')->html();

        $this->assertStringContainsString('x-data="{ at: null }"', $html, 'the list owns the open row');

        // The row-actions dropdown keeps its own `open` and should — it closes
        // on outside click. What must not come back is a row wrapper with one.
        $this->assertStringNotContainsString('x-data="{ open: false }" class="border-b', $html);

        foreach ([$a, $b] as $task) {
            $this->assertStringContainsString("at = (at === {$task->id} ? null : {$task->id})", $html);
            $this->assertStringContainsString("x-show=\"at === {$task->id}\" x-collapse.duration.300ms", $html);
        }
    }
}
