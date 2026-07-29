<?php

namespace Tests\Feature;

use App\Livewire\ReportsOverview;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ReportsOverviewTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_the_page_reads_the_whole_book(): void
    {
        $user = $this->actor();

        $this->actingAs($user)->get(route('reports.index'))->assertOk()
            ->assertSee('Delivery')
            ->assertSee('Money')
            ->assertSee('Programme')
            ->assertSee('People')
            ->assertSee('ICFT 2026');
    }

    public function test_every_figure_agrees_with_the_records_it_came_from(): void
    {
        $user = $this->actor();

        $view = Livewire::actingAs($user)->test(ReportsOverview::class);

        $live = Event::whereNull('archived_at')->count();
        $this->assertSame($live, (int) collect($view->viewData('figures'))->firstWhere('label', 'Events')['value']);

        $tasks = Task::whereIn('event_id', Event::whereNull('archived_at')->pluck('id'))
            ->where('status', '!=', 'cancelled');

        $this->assertSame($tasks->count(), $view->viewData('work')['total']);
        $this->assertSame(
            (clone $tasks)->whereIn('status', ['done', 'approved'])->count(),
            $view->viewData('work')['done'],
        );

        // Delivery lists every event in the window, worst first.
        $this->assertCount($live, $view->viewData('delivery'));
    }

    public function test_the_window_narrows_the_book(): void
    {
        $user = $this->actor();

        $whole = Livewire::actingAs($user)->test(ReportsOverview::class);
        $delivered = Livewire::actingAs($user)->test(ReportsOverview::class)->call('setWindow', 'delivered');

        $this->assertLessThanOrEqual(
            $whole->viewData('events')->count(),
            $delivered->viewData('events')->count(),
        );

        // Nothing in the delivered window is still to come.
        foreach ($delivered->viewData('events') as $event) {
            $this->assertTrue(($event->ends_at ?? $event->starts_at)->isPast());
        }
    }

    public function test_an_unknown_window_is_ignored_rather_than_obeyed(): void
    {
        $user = $this->actor();

        Livewire::actingAs($user)->test(ReportsOverview::class)
            ->call('setWindow', 'whenever')
            ->assertSet('window', 'all');
    }
}
