<?php

namespace Tests\Feature;

use App\Livewire\EventsIndex;
use App\Models\Event;
use App\Models\User;
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
        $islands = app(\App\Services\CommandCenterService::class)->islands();
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
        $this->assertSame($icft->avatar_id, $copy->avatar_id);
        $this->assertSame($icft->primary_color, $copy->primary_color);
    }

    public function test_sort_by_health_orders_highest_first(): void
    {
        $user = $this->actor();
        $pulse = app(\App\Services\EventHealthService::class);

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
