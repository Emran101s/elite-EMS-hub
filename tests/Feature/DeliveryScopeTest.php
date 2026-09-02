<?php

namespace Tests\Feature;

use App\Livewire\Hub\ScopeTab;
use App\Models\Event;
use App\Models\EventScopeItem;
use App\Models\Task;
use App\Models\User;
use App\Models\Venue;
use App\Support\ScopeStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Delivery Scope register.
 *
 * The rule worth guarding above all others is that a deliverable's status is
 * READ from the module that owns it and never stored here. Every recurring
 * defect in this platform has been a second copy of a truth drifting from the
 * first, and this is the page people are held to.
 */
class DeliveryScopeTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::factory()->create(['stage' => 'planning', 'starts_at' => now()->addDays(60)]);
    }

    private function item(Event $event, array $attributes = []): EventScopeItem
    {
        return $event->scopeItems()->create(array_merge([
            'workstream' => 'delivery',
            'title' => 'Main stage built and handed over',
            'definition_of_done' => 'The AV walkthrough passes.',
            'offset_days' => -14,
        ], $attributes));
    }

    /* ══ the rule ══ */

    public function test_status_is_read_from_the_source_and_never_stored(): void
    {
        $this->assertNotContains('status', Schema::getColumnListing('event_scope_items'),
            'a stored status would be a second copy of a truth another module owns');

        $event = $this->event();
        $item = $this->item($event, ['source_type' => 'venue_secured']);

        $this->assertSame(ScopeStatus::OPEN, ScopeStatus::for($item)['state']);

        $event->update(['venue_id' => Venue::create(['name' => 'The St Regis', 'city' => 'Amman'])->id]);

        // Nothing was written to the deliverable; the answer moved because the
        // venue did.
        $this->assertSame(ScopeStatus::MET, ScopeStatus::for($item->fresh()->load('event'))['state']);
    }

    public function test_a_deliverable_with_no_source_is_unmeasured_rather_than_open(): void
    {
        $item = $this->item($this->event());

        $this->assertSame(ScopeStatus::UNMEASURED, ScopeStatus::for($item)['state'],
            'nobody has assessed it, which is not the same as it being outstanding');
    }

    public function test_a_linked_task_treats_approved_as_still_open(): void
    {
        $event = $this->event();
        $task = Task::create(['event_id' => $event->id, 'title' => 'Rig the stage', 'status' => 'approved']);
        $item = $this->item($event, ['source_type' => 'task', 'source_id' => $task->id]);

        // Task::STAGES marks 'approved' open — approved to proceed is not
        // delivered, the same rule the Tasks page and its meter now follow.
        $this->assertNotSame(ScopeStatus::MET, ScopeStatus::for($item)['state']);

        $task->update(['status' => 'done']);
        $this->assertSame(ScopeStatus::MET, ScopeStatus::for($item->fresh()->load('event'))['state']);
    }

    /* ══ T-minus ══ */

    public function test_dates_are_relative_so_moving_the_event_moves_the_scope(): void
    {
        $event = $this->event();
        $item = $this->item($event, ['offset_days' => -14]);

        $this->assertSame($event->starts_at->copy()->startOfDay()->subDays(14)->toDateString(),
            $item->dueOn()->toDateString());

        $event->update(['starts_at' => $event->starts_at->copy()->addMonth()]);

        $this->assertSame($event->fresh()->starts_at->copy()->startOfDay()->subDays(14)->toDateString(),
            $item->fresh()->load('event')->dueOn()->toDateString(),
            'the offset is stored, not the date, so the whole scope re-dates itself');
        $this->assertSame('T−14', $item->tMinus());
    }

    public function test_an_undated_event_has_no_due_dates_rather_than_overdue_ones(): void
    {
        $event = Event::factory()->create(['stage' => 'planning', 'starts_at' => null]);
        $item = $this->item($event, ['offset_days' => -14]);

        $this->assertNull($item->dueOn());
        $this->assertFalse($item->isOverdue(), 'an offset against no date is not a date, let alone a late one');
    }

    /* ══ the register ══ */

    public function test_the_register_groups_by_workstream_and_counts_what_matters(): void
    {
        $user = User::factory()->create();
        $event = $this->event();

        $this->item($event, ['workstream' => 'programme', 'title' => 'Programme confirmed', 'owner_id' => $user->id]);
        $this->item($event, ['workstream' => 'venue_build', 'title' => 'Stage built']);   // no owner

        $screen = Livewire::actingAs($user)->test(ScopeTab::class, ['event' => $event])
            ->assertSee('Programme confirmed')
            ->assertSee('Stage built')
            ->assertSee('Nobody accountable');

        $this->assertSame(2, $screen->viewData('summary')['total']);
        $this->assertSame(1, $screen->viewData('summary')['unowned']);
        $this->assertSame(2, $screen->viewData('groups')->count(), 'one group per workstream');
    }

    public function test_filtering_by_owner_shows_only_that_persons_accountability(): void
    {
        $mine = User::factory()->create(['name' => 'Omar Nassar']);
        $theirs = User::factory()->create(['name' => 'Layla Haddad']);
        $event = $this->event();

        $this->item($event, ['title' => 'Stage handed over', 'owner_id' => $mine->id]);
        $this->item($event, ['title' => 'Speakers locked', 'owner_id' => $theirs->id]);

        Livewire::actingAs($mine)->test(ScopeTab::class, ['event' => $event])
            ->call('filterOwner', $mine->id)
            ->assertSee('Stage handed over')
            ->assertDontSee('Speakers locked');
    }

    public function test_filtering_keeps_every_owner_on_offer(): void
    {
        $mine = User::factory()->create(['name' => 'Omar Nassar']);
        $theirs = User::factory()->create(['name' => 'Layla Haddad']);
        $event = $this->event();

        $this->item($event, ['title' => 'Stage handed over', 'owner_id' => $mine->id]);
        $this->item($event, ['title' => 'Speakers locked', 'owner_id' => $theirs->id]);

        $screen = Livewire::actingAs($mine)->test(ScopeTab::class, ['event' => $event])
            ->call('filterOwner', $mine->id);

        $this->assertCount(2, $screen->viewData('owners'),
            'filtering to one person must not remove the way back to the others');
    }

    public function test_a_deliverable_can_be_added_and_removed(): void
    {
        $user = User::factory()->create();
        $event = $this->event();

        Livewire::actingAs($user)->test(ScopeTab::class, ['event' => $event])
            ->call('newItem')
            ->set('title', 'Site safety file issued')
            ->set('workstream', 'compliance')
            ->set('out_of_scope', 'Public liability insurance — held by the client.')
            ->call('save');

        $item = $event->scopeItems()->firstOrFail();
        $this->assertSame('Site safety file issued', $item->title);
        $this->assertStringContainsString('Public liability', $item->out_of_scope);

        Livewire::actingAs($user)->test(ScopeTab::class, ['event' => $event])->call('delete', $item->id);
        $this->assertSame(0, $event->scopeItems()->count());
    }

    public function test_a_viewer_cannot_change_the_scope(): void
    {
        $viewer = User::create(['name' => 'Vic', 'email' => 'v@scope.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(ScopeTab::class, ['event' => $this->event()])
            ->call('newItem')->assertForbidden();
    }

    /* ══ it must not cost a query per deliverable ══ */

    public function test_reading_a_large_scope_does_not_scale_its_queries(): void
    {
        $user = User::factory()->create();
        $event = $this->event();

        $count = function () use ($user, $event) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            Livewire::actingAs($user)->test(ScopeTab::class, ['event' => $event->fresh()]);
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        for ($i = 0; $i < 3; $i++) {
            $this->item($event, ['title' => 'Deliverable '.$i, 'source_type' => 'suppliers_contracted']);
        }
        $before = $count();

        for ($i = 3; $i < 30; $i++) {
            $this->item($event, ['title' => 'Deliverable '.$i, 'source_type' => 'suppliers_contracted']);
        }

        $this->assertLessThan($before * 1.5, $count(),
            'status is resolved against loaded relations, not one lookup per row');
    }
}
