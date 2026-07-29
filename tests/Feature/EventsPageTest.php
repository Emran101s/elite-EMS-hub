<?php

namespace Tests\Feature;

use App\Livewire\EventsIndex;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\CommandCenterService;
use App\Services\EventHealthService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class EventsPageTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_list_view_selects_and_bulk_deletes_events(): void
    {
        $user = $this->actor();
        $a = Event::where('name', 'ICFT 2026')->firstOrFail();
        $b = Event::where('name', 'Tech Expo 2026')->firstOrFail();
        $before = Event::count();

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->set('view', 'list')
            ->call('toggleSelect', $a->id)
            ->call('toggleSelect', $b->id)
            ->assertCount('selectedIds', 2)
            ->call('deleteSelected')
            ->assertCount('selectedIds', 0);

        $this->assertSame($before - 2, Event::count());
        $this->assertNull(Event::find($a->id));
        $this->assertNull(Event::find($b->id));
    }

    public function test_ticking_a_row_twice_clears_it_and_select_all_respects_the_filter(): void
    {
        $user = $this->actor();
        $icft = Event::where('name', 'ICFT 2026')->firstOrFail();

        // Toggling the same row twice leaves nothing selected.
        Livewire::actingAs($user)->test(EventsIndex::class)
            ->set('view', 'list')
            ->call('toggleSelect', $icft->id)
            ->assertCount('selectedIds', 1)
            ->call('toggleSelect', $icft->id)
            ->assertCount('selectedIds', 0);

        // "Select all matching" only picks up what the current filter returns.
        $c = Livewire::actingAs($user)->test(EventsIndex::class)
            ->set('view', 'list')
            ->set('q', 'ICFT')
            ->call('selectAllMatching');

        $selected = $c->get('selectedIds');
        $this->assertContains($icft->id, $selected);
        $this->assertCount(Event::whereNull('archived_at')->where('name', 'like', '%ICFT%')->count(), $selected);
    }

    public function test_the_deck_opens_on_a_mission_and_steps_through_the_book(): void
    {
        $user = $this->actor();
        $icft = Event::where('name', 'ICFT 2026')->firstOrFail();

        $c = Livewire::actingAs($user)->test(EventsIndex::class)
            ->assertSet('view', 'deck')
            ->assertSee('Active mission');

        // Picking a card brings it into the centre; the detail travels with it.
        $c->call('activate', $icft->id)
            ->assertSet('activeId', $icft->id)
            ->assertSee('ICFT 2026')
            ->assertSee('Next milestone');

        $deck = $c->viewData('deck');
        $at = $c->viewData('activeAt');

        // Stepping moves along the order on screen, not the database order.
        $c->call('step', 1);
        $this->assertSame($deck[min($deck->count() - 1, $at + 1)]['id'], $c->get('activeId'));

        // And it stops at the ends rather than wrapping round.
        $c->call('step', -1)->call('step', -1)->call('step', -1)->call('step', -1)->call('step', -1);
        $this->assertSame($deck->first()['id'], $c->get('activeId'));
    }

    public function test_manager_can_permanently_delete_an_event_and_the_audit_trail_survives(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $id = $event->id;
        $event->tasks()->create(['title' => 'child task', 'status' => 'todo', 'priority' => 'normal']);

        Livewire::actingAs($user)->test(EventsIndex::class)->call('deleteEvent', $id);

        $this->assertNull(Event::find($id));
        $this->assertSame(0, Task::where('event_id', $id)->count(), 'children must cascade');

        // The record of the deletion outlives the event it describes.
        $this->assertDatabaseHas('audit_logs', [
            'auditable_type' => 'Event', 'auditable_id' => $id, 'action' => 'deleted', 'label' => 'ICFT 2026',
        ]);
    }

    public function test_deleting_an_event_requires_manager(): void
    {
        $this->actor();
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test', 'password' => bcrypt('x'), 'role' => 'viewer']);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        Livewire::actingAs($viewer)->test(EventsIndex::class)
            ->call('deleteEvent', $event->id)
            ->assertForbidden();

        $this->assertNotNull(Event::find($event->id));
    }

    public function test_star_persists_and_starred_filter_scopes(): void
    {
        $user = $this->actor();
        $icft = Event::where('name', 'ICFT 2026')->firstOrFail();

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('toggleFavorite', $icft->id);

        $this->assertTrue($user->favoriteEvents()->whereKey($icft->id)->exists());

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('toggleStarred')
            ->assertSee('ICFT 2026')
            ->assertDontSee('Tech Expo 2026');

        // Toggling again removes it.
        Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('toggleFavorite', $icft->id);
        $this->assertFalse($user->favoriteEvents()->whereKey($icft->id)->exists());
    }

    public function test_archive_hides_event_everywhere(): void
    {
        $user = $this->actor();
        $expo = Event::where('name', 'Tech Expo 2026')->firstOrFail();

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('archive', $expo->id)
            ->assertDontSee('Tech Expo 2026');

        $this->assertNotNull($expo->fresh()->archived_at);

        // Gone from the Operations Hub islands too.
        $islands = app(CommandCenterService::class)->islands();
        $this->assertNotContains('Tech Expo 2026', $islands->pluck('name')->all());
    }

    public function test_duplicate_creates_a_draft_copy(): void
    {
        $user = $this->actor();
        $icft = Event::where('name', 'ICFT 2026')->firstOrFail();

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('duplicate', $icft->id);

        $copy = Event::where('name', 'ICFT 2026 (Copy)')->firstOrFail();
        $this->assertSame('draft', $copy->stage);
        $this->assertSame(0, $copy->progress);
        $this->assertSame($icft->cover_path, $copy->cover_path);
        $this->assertSame($icft->primary_color, $copy->primary_color);
    }

    public function test_sort_by_health_orders_highest_first(): void
    {
        $user = $this->actor();
        $pulse = app(EventHealthService::class);

        // Sorting belongs to the List. The Deck is a line you walk along, so
        // it is always chronological — "next" has to mean later, not sicker.
        $component = Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('setView', 'list')
            ->set('sort', 'health');

        // The rows are missions now — one description of an event, shared by
        // all three views — so the score is read off the event inside it.
        $scores = collect($component->viewData('rows')->items())
            ->map(fn (array $m) => $pulse->breakdown($m['event'])['score'])->all();

        $this->assertSame($scores, collect($scores)->sortDesc()->values()->all());
    }

    public function test_the_flight_path_lays_missions_out_in_lanes_and_months(): void
    {
        $user = $this->actor();

        $c = Livewire::actingAs($user)->test(EventsIndex::class)->call('setView', 'path');

        $months = $c->viewData('months');
        $lanes = $c->viewData('lanes');

        // Months run left to right and every mission falls inside them.
        $this->assertNotEmpty($months['list']);
        $this->assertGreaterThanOrEqual(4, count($months['list']));

        // A lane is only drawn when something flies in it.
        $this->assertNotEmpty($lanes);
        foreach ($lanes as $lane) {
            $this->assertNotEmpty($lane['missions']);
        }

        // The old calendar URL lands here rather than on a blank page.
        $this->actingAs($user)->get('/events?view=calendar')->assertOk()->assertSee('Flight Path');
    }
}
