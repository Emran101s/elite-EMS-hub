<?php

namespace Tests\Feature;

use App\Livewire\CommandCenter;
use App\Models\Event;
use App\Models\Task;
use App\Models\User;
use App\Services\CommandCenterService;
use App\Services\EventHealthService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard()
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        return $this->actingAs($user)->get('/operations-room');
    }

    public function test_operations_room_renders_its_sections(): void
    {
        $this->dashboard()->assertOk()
            ->assertSee('Operations Room')
            // The portfolio card now carries an explicit per-row Focus button
            // rather than a "click to focus" hint on the heading.
            ->assertSee('Portfolio')
            ->assertSee('Focus')
            ->assertSee('Overdue')
            ->assertSee('Approvals')
            ->assertSee('Money');
    }

    public function test_focusing_an_event_narrows_the_stream_to_that_event(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $icft = Event::where('name', 'ICFT 2026')->firstOrFail();
        $other = Event::where('name', 'Tech Expo 2026')->firstOrFail();

        // Give each event one unmistakable overdue task.
        $icft->tasks()->create(['title' => 'ICFT signal marker', 'status' => 'todo', 'priority' => 'high', 'due_on' => now()->subDays(3)]);
        $other->tasks()->create(['title' => 'Expo signal marker', 'status' => 'todo', 'priority' => 'high', 'due_on' => now()->subDays(3)]);

        $c = Livewire::actingAs($user)->test(CommandCenter::class)
            ->assertSee('ICFT signal marker')
            ->assertSee('Expo signal marker');

        $c->call('focusOn', $icft->id)
            ->assertSet('focusEvent', $icft->id)
            ->assertSee('ICFT signal marker')
            ->assertDontSee('Expo signal marker');

        // Clicking the same event again clears the focus.
        $c->call('focusOn', $icft->id)->assertSet('focusEvent', null)->assertSee('Expo signal marker');
    }

    public function test_lens_filters_the_stream_by_kind(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $icft = Event::where('name', 'ICFT 2026')->firstOrFail();

        $icft->tasks()->create(['title' => 'Overdue marker task', 'status' => 'todo', 'priority' => 'high', 'due_on' => now()->subDays(2)]);
        $icft->tasks()->create(['title' => 'Awaiting signoff task', 'status' => 'review', 'priority' => 'high']);

        Livewire::actingAs($user)->test(CommandCenter::class)
            ->call('setLens', 'overdue')
            ->assertSet('lens', 'overdue')
            ->assertSee('Overdue marker task')
            ->assertDontSee('Awaiting signoff task');
    }

    public function test_islands_show_computed_health_scores(): void
    {
        $response = $this->dashboard();
        $pulse = app(EventHealthService::class);

        foreach (['ICFT 2026', 'Tech Expo 2026', 'NDI Workshop'] as $name) {
            $event = Event::where('name', $name)->firstOrFail();
            $response->assertSee($name)
                ->assertSee($pulse->breakdown($event)['score'].'%');
        }
    }

    public function test_alerts_surface_events_whose_computed_health_is_poor(): void
    {
        $this->seed(DemoDataSeeder::class);

        // Health is derived, never stored: alerts must mirror the health engine.
        $pulse = app(EventHealthService::class);
        $expected = Event::whereNull('archived_at')->get()
            ->filter(fn ($e) => in_array($pulse->breakdown($e)['status'], ['at_risk', 'behind'], true));

        $service = app(CommandCenterService::class);

        // Assert against the uncapped health alerts: `alerts()` is a shortlist
        // that also carries conflicts, money and tasks, and trims to six — a
        // health alert can legitimately be crowded out of the display.
        $health = $service->healthAlerts()->pluck('title')->implode(' | ');

        $this->assertNotEmpty($expected, 'demo data should contain at least one unhealthy event');
        foreach ($expected as $event) {
            $this->assertStringContainsString($event->name, $health, "{$event->name} has poor health but raised no alert");
        }

        // The display list stays bounded no matter how much is wrong.
        $this->assertLessThanOrEqual(6, $service->alerts()->count());
    }

    public function test_service_numbers_are_sane(): void
    {
        $this->seed(DemoDataSeeder::class);
        $pulse = app(CommandCenterService::class);

        $this->assertCount(6, $pulse->islands());
        $this->assertGreaterThan(0, $pulse->alerts()->count());

        foreach ($pulse->utilization() as $resource) {
            if ($resource['pct'] !== null) {
                $this->assertGreaterThanOrEqual(0, $resource['pct']);
                $this->assertLessThanOrEqual(100, $resource['pct']);
            }
        }

        $budget = $pulse->budgetByHealth();
        $this->assertSame(211000000, $budget['total']);
        $this->assertEqualsWithDelta(100, collect($budget['segments'])->sum('pct'), 0.5);

        // Six orbit stages, each with label/hex/count — blocked rides inside In Progress.
        $counts = $pulse->taskCounts();
        $this->assertSame(array_keys(Task::STAGES), array_keys($counts));
        foreach ($counts as $stage) {
            $this->assertGreaterThanOrEqual(0, $stage['count']);
        }
    }

    public function test_islands_have_positions_on_the_orbit(): void
    {
        $this->seed(DemoDataSeeder::class);

        foreach (app(CommandCenterService::class)->islands() as $event) {
            $this->assertGreaterThanOrEqual(0, $event->pos_x);
            $this->assertLessThanOrEqual(100, $event->pos_x);
            $this->assertGreaterThanOrEqual(0, $event->pos_y);
            $this->assertLessThanOrEqual(100, $event->pos_y);
        }
    }
}
