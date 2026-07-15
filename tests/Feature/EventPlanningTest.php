<?php

namespace Tests\Feature;

use App\Livewire\Hub\PlanningTab;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventPlanningTest extends TestCase
{
    use RefreshDatabase;

    private function ctx(): array
    {
        $this->seed(DemoDataSeeder::class);

        return [
            Event::where('name', 'ICFT 2026')->firstOrFail(),
            User::where('email', 'emran.itan@elitebhub.com')->firstOrFail(),
        ];
    }

    public function test_a_task_can_have_several_owners(): void
    {
        [$event, $user] = $this->ctx();
        $event->ensurePlanCategories();
        $second = User::create(['name' => 'Lina Haddad', 'email' => 'lina@ebh.test', 'password' => bcrypt('x')]);
        $third = User::create(['name' => 'Omar Nasser', 'email' => 'omar@ebh.test', 'password' => bcrypt('x')]);

        $item = $event->planItems()->create([
            'category_id' => $event->planCategories()->value('id'),
            'title' => 'Secure main venue', 'status' => 'todo', 'priority' => 'high',
        ]);

        $c = Livewire::actingAs($user)->test(PlanningTab::class, ['event' => $event])
            ->call('editItem', $item->id)
            ->call('toggleOwner', $user->id)
            ->call('toggleOwner', $second->id)
            ->call('toggleOwner', $third->id);

        $this->assertEqualsCanonicalizing(
            [$user->id, $second->id, $third->id],
            $item->fresh()->owners->pluck('id')->all()
        );
        // assignee_id mirrors the first owner for anything still reading it
        $this->assertNotNull($item->fresh()->assignee_id);

        // toggling one off removes just that person
        $c->call('toggleOwner', $second->id);
        $this->assertEqualsCanonicalizing([$user->id, $third->id], $item->fresh()->owners->pluck('id')->all());

        // reopening the task reloads the owner list
        $c->call('editItem', $item->id)->assertCount('owner_ids', 2);
    }

    public function test_inspector_autosaves_and_closes(): void
    {
        [$event, $user] = $this->ctx();
        $event->ensurePlanCategories();
        $phase = $event->planCategories()->firstOrFail();
        $item = $event->planItems()->create(['category_id' => $phase->id, 'title' => 'Draft task', 'status' => 'todo', 'priority' => 'medium']);

        $c = Livewire::actingAs($user)->test(PlanningTab::class, ['event' => $event])
            ->call('editItem', $item->id)
            ->assertSet('showForm', true);

        // Editing a field saves immediately — no Save button.
        $c->set('title', 'Confirm main sponsor');
        $this->assertSame('Confirm main sponsor', $item->fresh()->title);

        $c->set('status', 'in_progress')->set('priority', 'critical');
        $this->assertSame('in_progress', $item->fresh()->status);
        $this->assertSame('critical', $item->fresh()->priority);

        // The explicit Save button commits and confirms.
        $c->set('notes', 'Call the venue on Monday')
            ->call('saveItem')
            ->assertHasNoErrors()
            ->assertSet('justSaved', true);
        $this->assertSame('Call the venue on Monday', $item->fresh()->notes);

        // Selecting another item clears the "Saved" confirmation.
        $c->call('editItem', $item->id)->assertSet('justSaved', false);

        // Closing leaves the item intact; deleting the selected item closes the panel.
        $c->call('closePanel')->assertSet('showForm', false);
        $c->call('editItem', $item->id)->call('deleteItem', $item->id)->assertSet('showForm', false);
        $this->assertNull($item->fresh());
    }

    public function test_gantt_nests_workstream_task_and_subtask(): void
    {
        [$event, $user] = $this->ctx();
        $event->ensurePlanCategories();
        $phase = $event->planCategories()->where('name', 'Planning & Design')->firstOrFail();

        $ws = $event->planItems()->create(['category_id' => $phase->id, 'title' => 'Venue Management', 'status' => 'todo', 'priority' => 'high']);
        $task = $event->planItems()->create(['category_id' => $phase->id, 'parent_id' => $ws->id, 'title' => 'Space allocation', 'status' => 'todo', 'priority' => 'medium']);
        $event->planItems()->create(['category_id' => $phase->id, 'parent_id' => $task->id, 'title' => 'Ballroom layout', 'status' => 'todo', 'priority' => 'medium']);

        $c = Livewire::actingAs($user)->test(PlanningTab::class, ['event' => $event]);

        // Collapsed: only the workstream shows.
        $c->assertSee('Venue Management')->assertDontSee('Space allocation')->assertDontSee('Ballroom layout');

        // Expand the workstream → its task appears, but not the 3rd level yet.
        $c->call('toggleExpand', $ws->id)
            ->assertSee('Space allocation')->assertDontSee('Ballroom layout');

        // Expand the task → the subtask (3rd level) appears.
        $c->call('toggleExpand', $task->id)->assertSee('Ballroom layout');

        // Collapsing the workstream hides the whole branch again.
        $c->call('toggleExpand', $ws->id)->assertDontSee('Ballroom layout');
    }

    public function test_plan_categories_seed_and_can_be_managed(): void
    {
        [$event, $user] = $this->ctx();
        $c = Livewire::actingAs($user)->test(PlanningTab::class, ['event' => $event]);

        // the 7 default phases seed on mount, in order
        $this->assertSame(7, $event->planCategories()->count());
        $this->assertSame(
            ['Initiation & Strategy', 'Planning & Design', 'Marketing & Registration', 'Pre-Event Readiness', 'Event Execution', 'Event Close-Out', 'Post-Event'],
            $event->planCategories()->orderBy('position')->pluck('name')->all()
        );

        // add + duplicate rejected
        $c->set('newCategoryName', 'Sustainability')->call('addCategory');
        $this->assertSame(8, $event->fresh()->planCategories()->count());
        $c->set('newCategoryName', 'sustainability')->call('addCategory')->assertHasErrors('newCategoryName');

        // reorder — move the last to front
        $ids = $event->planCategories()->orderBy('position')->pluck('id')->all();
        $last = array_pop($ids);
        array_unshift($ids, $last);
        $c->call('reorderCategories', $ids);
        $this->assertSame(0, $event->planCategories()->whereKey($last)->value('position'));
    }

    public function test_item_and_subitem_with_status(): void
    {
        [$event, $user] = $this->ctx();
        $event->ensurePlanCategories();
        $cat = $event->planCategories()->first();
        $c = Livewire::actingAs($user)->test(PlanningTab::class, ['event' => $event]);

        // top-level item lands in the category
        $c->call('newItem', $cat->id)->set('title', 'Book venue')->set('priority', 'high')->call('saveItem')->assertHasNoErrors();
        $item = $event->planItems()->where('title', 'Book venue')->firstOrFail();
        $this->assertSame($cat->id, $item->category_id);
        $this->assertNull($item->parent_id);

        // sub-item inherits the parent's category
        $c->call('newSubItem', $item->id)->set('title', 'Sign contract')->call('saveItem');
        $sub = $event->planItems()->where('title', 'Sign contract')->firstOrFail();
        $this->assertSame($item->id, $sub->parent_id);
        $this->assertSame($cat->id, $sub->category_id);

        // status controls
        $c->call('setStatus', $item->id, 'in_progress');
        $this->assertSame('in_progress', $item->fresh()->status);
        $c->call('toggleDone', $item->id);
        $this->assertSame('done', $item->fresh()->status);

        // deleting a parent cascades its sub-items
        $c->call('deleteItem', $item->id);
        $this->assertNull($event->planItems()->find($sub->id));
    }

    public function test_planning_tab_and_pdf_render(): void
    {
        [$event, $user] = $this->ctx();
        $event->update(['enabled_modules' => ['planning']]);
        $event->ensurePlanCategories();
        $cat = $event->planCategories()->first();
        $event->planItems()->create(['category_id' => $cat->id, 'title' => 'Kickoff', 'status' => 'done', 'priority' => 'high']);

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'planning']))
            ->assertOk()->assertSee('Countdown to Event Day')->assertSee('Initiation & Strategy');

        $pdf = $this->actingAs($user)->get(route('events.planning.pdf', $event));
        $pdf->assertOk();
        $this->assertSame('application/pdf', $pdf->headers->get('content-type'));
    }

    public function test_task_dates_and_drag_reschedule(): void
    {
        [$event, $user] = $this->ctx();
        $event->ensurePlanCategories();
        $cat = $event->planCategories()->first();
        $c = Livewire::actingAs($user)->test(PlanningTab::class, ['event' => $event]);

        // create a dated task (a bar spanning a range)
        $c->call('newItem', $cat->id)
            ->set('title', 'Book venue')
            ->set('starts_on', '2026-08-01')
            ->set('due_on', '2026-08-10')
            ->call('saveItem')->assertHasNoErrors();
        $task = $event->planItems()->where('title', 'Book venue')->firstOrFail();
        $this->assertSame('2026-08-01', $task->starts_on->format('Y-m-d'));

        // drag +7 days → both dates shift, duration preserved
        $c->call('moveTask', $task->id, 7);
        $task->refresh();
        $this->assertSame('2026-08-08', $task->starts_on->format('Y-m-d'));
        $this->assertSame('2026-08-17', $task->due_on->format('Y-m-d'));

        // due before start is rejected
        $c->call('editItem', $task->id)->set('starts_on', '2026-08-20')->set('due_on', '2026-08-10')
            ->call('saveItem')->assertHasErrors('due_on');
    }

    public function test_resize_moves_a_single_edge_without_crossing(): void
    {
        [$event, $user] = $this->ctx();
        $event->ensurePlanCategories();
        $task = $event->planItems()->create([
            'category_id' => $event->planCategories()->value('id'),
            'title' => 'Sponsorship', 'starts_on' => '2026-08-01', 'due_on' => '2026-08-20', 'status' => 'todo',
        ]);
        $c = Livewire::actingAs($user)->test(PlanningTab::class, ['event' => $event]);

        // extend the right edge by 5 days — start unchanged
        $c->call('resizeTask', $task->id, 'right', 5);
        $task->refresh();
        $this->assertSame('2026-08-01', $task->starts_on->format('Y-m-d'));
        $this->assertSame('2026-08-25', $task->due_on->format('Y-m-d'));

        // pull the left edge; it cannot cross past the due date
        $c->call('resizeTask', $task->id, 'left', 999);
        $task->refresh();
        $this->assertTrue($task->starts_on->lte($task->due_on));
    }
}
