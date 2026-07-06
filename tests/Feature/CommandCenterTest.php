<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CommandCenterService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private function dashboard()
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        return $this->actingAs($user)->get('/');
    }

    public function test_dashboard_renders_all_sections(): void
    {
        $this->dashboard()->assertOk()
            ->assertSee('Operations Hub')
            ->assertSee('AI COMMAND CORE')
            ->assertSee('Live Alerts')
            ->assertSee('Resource Utilization')
            ->assertSee('Budget Overview')
            ->assertSee('Upcoming Deadlines')
            ->assertSee('Tasks Overview')
            ->assertSee('Top Suppliers')
            ->assertSee('Events by Status');
    }

    public function test_islands_show_computed_health_scores(): void
    {
        $response = $this->dashboard();
        $pulse = app(\App\Services\EventHealthService::class);

        foreach (['ICFT 2026', 'Tech Expo 2026', 'NDI Workshop'] as $name) {
            $event = \App\Models\Event::where('name', $name)->firstOrFail();
            $response->assertSee($name)
                ->assertSee($pulse->breakdown($event)['score'].'%');
        }
    }

    public function test_alerts_surface_at_risk_and_behind_events(): void
    {
        $this->dashboard()
            ->assertSee('Tech Expo 2026 behind schedule')
            ->assertSee('NDI Workshop at risk');
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

        $this->assertSame(['completed' => 72, 'in_progress' => 36, 'pending' => 20], $pulse->taskCounts());
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
