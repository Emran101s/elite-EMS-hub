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

    public function test_selecting_a_card_opens_its_detail_in_the_inspector(): void
    {
        $user = $this->actor();
        $icft = Event::where('name', 'ICFT 2026')->firstOrFail();

        // The journey is the landing view; the inspector belongs to the lanes.
        $c = Livewire::actingAs($user)->test(EventsIndex::class)
            ->assertSet('view', 'journey')
            ->assertSee('ICFT 2026')
            ->set('view', 'lanes')
            ->assertDontSee('Event Control Room'); // collapsed: detail is hidden

        $c->call('toggleExpand', $icft->id)
            ->assertSet('expandedId', $icft->id)
            ->assertSee('AI Recommendation')
            ->assertSee('Health breakdown')
            ->assertSee('Delivery phases')
            ->assertSee('Event Control Room');

        // Clicking the same card again closes it.
        $c->call('toggleExpand', $icft->id)
            ->assertSet('expandedId', null)
            ->assertDontSee('Event Control Room');
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

        $component = Livewire::actingAs($user)->test(EventsIndex::class)->set('sort', 'health');

        $events = $component->viewData('events')->items();
        $scores = collect($events)->map(fn ($e) => $pulse->breakdown($e)['score'])->all();

        $this->assertSame($scores, collect($scores)->sortDesc()->values()->all());
    }

    public function test_calendar_view_renders_month_grid(): void
    {
        $user = $this->actor();

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->set('view', 'calendar')
            ->set('calMonth', '2026-10')
            ->assertSee('October 2026')
            ->assertSee('ICFT 2026'); // Oct 19–21 falls in this month

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->set('view', 'calendar')
            ->set('calMonth', '2026-10')
            ->call('nextMonth')
            ->assertSee('November 2026');
    }
}
