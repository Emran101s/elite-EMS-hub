<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\CommandCenterService;
use App\Services\EventHealthService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Draft and proposal events carry no health score.
 *
 * Before this rule an untouched proposal scored 0 and was reported as "behind",
 * which put three portfolio panels — the At Risk KPI, Events by Status and
 * Budget Overview — permanently in the red for events nobody had started.
 */
class UnscoredEventHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_draft_and_proposal_events_are_not_scored(): void
    {
        $service = app(EventHealthService::class);

        foreach (EventHealthService::UNSCORED_STAGES as $stage) {
            $event = Event::factory()->create(['stage' => $stage]);
            $health = $service->breakdown($event->fresh());

            $this->assertNull($health['score'], "a $stage event must have no score");
            $this->assertSame('not_started', $health['status']);
            $this->assertSame('neutral', $health['group']);
            $this->assertFalse(EventHealthService::isScored($event));
        }
    }

    public function test_a_confirmed_event_is_scored_normally(): void
    {
        $event = Event::factory()->create(['stage' => 'confirmed', 'progress' => 40]);
        $health = app(EventHealthService::class)->breakdown($event->fresh());

        $this->assertIsInt($health['score']);
        $this->assertNotSame('not_started', $health['status']);
        $this->assertTrue(EventHealthService::isScored($event));
    }

    public function test_moving_a_proposal_forward_starts_scoring_it(): void
    {
        $service = app(EventHealthService::class);
        $event = Event::factory()->create(['stage' => 'proposal']);

        $this->assertNull($service->breakdown($event->fresh())['score']);

        $event->update(['stage' => 'planning']);

        $this->assertIsInt($service->breakdown($event->fresh())['score']);
    }

    public function test_unscored_events_are_not_counted_as_at_risk(): void
    {
        Event::factory()->count(3)->create(['stage' => 'proposal']);
        $service = app(CommandCenterService::class);

        $this->assertSame(0, $service->stats()['atRisk']);
        $this->assertSame(3, $service->statusBars()['counts']['Not Started']);
        $this->assertSame(0, $service->statusBars()['counts']['At Risk']);
    }

    public function test_uncommitted_budget_is_reported_separately_from_budget_at_risk(): void
    {
        Event::factory()->create(['stage' => 'proposal', 'budget_cents' => 5_000_000]);

        $segments = collect(app(CommandCenterService::class)->budgetByHealth()['segments'])
            ->keyBy('group');

        $this->assertSame(5_000_000, $segments['neutral']['cents']);
        $this->assertSame(0, $segments['risk']['cents']);
    }

    public function test_the_dashboard_renders_the_not_started_state(): void
    {
        $this->seed(DemoDataSeeder::class);
        Event::factory()->create(['stage' => 'proposal', 'name' => 'Untouched Proposal']);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        $this->actingAs($user)->get('/')
            ->assertOk()
            ->assertSee('Not Started')
            ->assertSee('Untouched Proposal');
    }

    public function test_the_ai_summary_does_not_claim_a_percentage_it_does_not_have(): void
    {
        $event = Event::factory()->create(['stage' => 'proposal', 'name' => 'Quiet Bid']);

        $summary = app(EventHealthService::class)->aiSummary($event->fresh());

        $this->assertStringContainsString('not scored yet', $summary['headline']);
        $this->assertStringNotContainsString('%', $summary['headline']);
    }
}
