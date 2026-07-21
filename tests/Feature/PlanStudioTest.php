<?php

namespace Tests\Feature;

use App\Livewire\Hub\PlanStudio;
use App\Models\Event;
use App\Models\PlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PlanStudioTest extends TestCase
{
    use RefreshDatabase;

    private function make(string $role = 'coordinator'): array
    {
        $user = User::create(['name' => 'Dana Project', 'email' => 'dana@ebh.test', 'password' => bcrypt('x'), 'role' => $role]);
        $event = Event::create(['name' => 'World Summit', 'type' => 'conference', 'city' => 'Amman', 'country' => 'Jordan', 'starts_at' => now()->addMonths(3), 'status' => 'planning']);

        return [$user, $event];
    }

    public function test_mount_seeds_the_default_tracks(): void
    {
        [$user, $event] = $this->make();
        Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event]);

        $this->assertSame(count(Event::DEFAULT_TRACKS), $event->planTracks()->count());
        $this->assertSame('Initiation & Strategy', $event->planTracks()->orderBy('position')->value('name'));
    }

    public function test_add_item_creates_and_opens_the_drawer(): void
    {
        [$user, $event] = $this->make();

        $c = Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event])
            ->call('addItem', null, 'in_progress');

        $item = $event->planItems()->firstOrFail();
        $this->assertSame('in_progress', $item->status);
        $c->assertSet('selectedId', $item->id);
    }

    public function test_moving_to_a_signed_gate_stamps_and_clears_the_approval_seal(): void
    {
        [$user, $event] = $this->make();
        $item = $event->planItems()->create(['title' => 'Sign venue', 'status' => 'needs_approval', 'priority' => 'high']);

        $c = Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event]);

        $c->call('setStatus', $item->id, 'approved');
        $item->refresh();
        $this->assertSame('approved', $item->status);
        $this->assertSame($user->id, $item->approved_by);
        $this->assertNotNull($item->approved_at);
        $this->assertTrue($item->isSigned());

        // Moving back out of a signed gate revokes the seal.
        $c->call('setStatus', $item->id, 'in_progress');
        $item->refresh();
        $this->assertNull($item->approved_by);
        $this->assertNull($item->approved_at);
        $this->assertFalse($item->isSigned());
    }

    public function test_subtasks_add_toggle_and_roll_up_progress(): void
    {
        [$user, $event] = $this->make();
        $item = $event->planItems()->create(['title' => 'Programme', 'status' => 'in_progress', 'priority' => 'medium']);

        $c = Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event])
            ->call('openItem', $item->id)
            ->set('newSubtask', 'Draft agenda')->call('addSubtask', $item->id)
            ->set('newSubtask', 'Confirm speakers')->call('addSubtask', $item->id);

        $this->assertSame(2, $item->subtasks()->count());
        $this->assertSame(0, $item->fresh()->progress());

        $first = $item->subtasks()->orderBy('id')->first();
        $c->call('toggleSubtask', $first->id);
        $this->assertSame(50, $item->fresh()->progress());
    }

    public function test_manual_progress_override_wins(): void
    {
        [$user, $event] = $this->make();
        $item = $event->planItems()->create(['title' => 'Setup', 'status' => 'in_progress', 'priority' => 'low']);

        Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event])
            ->call('openItem', $item->id)
            ->set('progress_override', 80);

        $this->assertSame(80, $item->fresh()->progress());
    }

    public function test_tracks_can_be_added_renamed_and_deleted_with_items_reassigned(): void
    {
        [$user, $event] = $this->make();
        $c = Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event]);

        $c->set('newTrackName', 'Legal')->call('addTrack');
        $legal = $event->planTracks()->where('name', 'Legal')->firstOrFail();

        $item = $event->planItems()->create(['title' => 'NDA', 'status' => 'todo', 'priority' => 'medium', 'track_id' => $legal->id]);

        $c->call('startRenameTrack', $legal->id)->set('trackEditName', 'Legal & Compliance')->call('saveTrackName');
        $this->assertSame('Legal & Compliance', $legal->fresh()->name);

        $c->call('deleteTrack', $legal->id);
        $this->assertNull($event->planTracks()->find($legal->id));
        $this->assertNotNull($item->fresh()->track_id); // reassigned, not orphaned
    }

    public function test_all_four_views_render_the_items(): void
    {
        [$user, $event] = $this->make();
        $track = $event->planTracks()->create(['name' => 'Venue', 'color' => '#3B82F6', 'position' => 0]);
        $event->planItems()->create(['title' => 'Confirm ballroom', 'status' => 'approved', 'priority' => 'high', 'track_id' => $track->id, 'start_on' => now(), 'due_on' => now()->addDays(7)]);

        foreach (['board', 'timeline', 'list', 'gallery'] as $view) {
            Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event])
                ->set('view', $view)
                ->assertOk()
                ->assertSee('Confirm ballroom');
        }
    }

    public function test_search_and_track_filters_narrow_the_items(): void
    {
        [$user, $event] = $this->make();
        $t1 = $event->planTracks()->create(['name' => 'Venue', 'color' => '#3B82F6', 'position' => 0]);
        $t2 = $event->planTracks()->create(['name' => 'Marketing', 'color' => '#EC4899', 'position' => 1]);
        $event->planItems()->create(['title' => 'Book the hall', 'status' => 'todo', 'priority' => 'medium', 'track_id' => $t1->id]);
        $event->planItems()->create(['title' => 'Launch campaign', 'status' => 'todo', 'priority' => 'medium', 'track_id' => $t2->id]);

        Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event])
            ->set('view', 'list')
            ->call('filterByTrack', $t1->id)
            ->assertSee('Book the hall')->assertDontSee('Launch campaign')
            ->call('filterByTrack', $t1->id) // toggles off
            ->set('search', 'campaign')
            ->assertSee('Launch campaign')->assertDontSee('Book the hall');
    }

    public function test_track_name_goal_and_colour_can_be_edited(): void
    {
        [$user, $event] = $this->make();
        $event->ensurePlanTracks();
        $track = $event->planTracks()->firstOrFail();

        Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event])
            ->call('updateTrack', $track->id, 'goal', 'Nail the foundations')
            ->call('updateTrack', $track->id, 'name', 'Kickoff')
            ->call('updateTrack', $track->id, 'color', '#123456');

        $track->refresh();
        $this->assertSame('Nail the foundations', $track->goal);
        $this->assertSame('Kickoff', $track->name);
        $this->assertSame('#123456', $track->color);
    }

    public function test_plan_pdf_downloads(): void
    {
        [$user, $event] = $this->make();
        $event->planItems()->create(['title' => 'Confirm venue', 'status' => 'approved', 'priority' => 'high', 'track_id' => $event->planTracks()->value('id')]);

        $res = $this->actingAs($user)->get(route('events.planning.pdf', $event));
        $res->assertOk();
        $this->assertSame('application/pdf', $res->headers->get('content-type'));
    }

    public function test_viewers_cannot_mutate(): void
    {
        [$user, $event] = $this->make('viewer');

        Livewire::actingAs($user)->test(PlanStudio::class, ['event' => $event])
            ->call('addItem')
            ->assertForbidden();

        $this->assertSame(0, $event->planItems()->count());
    }
}
