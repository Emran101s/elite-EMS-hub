<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\EventRoom;
use App\Models\User;
use App\Services\CommandCenterService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The dashboard's panels run on records, and drawing it must not cost a query
 * per relation per event — scoring the portfolio five times over took 250.
 */
class DashboardPanelsTest extends TestCase
{
    use RefreshDatabase;

    public function test_equipment_readiness_counts_confirmed_and_onsite_lines(): void
    {
        $event = Event::factory()->create(['stage' => 'planning']);
        EventRoom::create([
            'event_id' => $event->id,
            'name' => 'Main Hall',
            'requirements' => [
                ['name' => 'Sound system (PA)', 'qty' => 1, 'status' => 'onsite'],
                ['name' => 'Projector', 'qty' => 1, 'status' => 'confirmed'],
                ['name' => 'Stage lighting', 'qty' => 1, 'status' => 'requested'],
                ['name' => 'Banners', 'qty' => 4, 'status' => 'needed'],
            ],
        ]);

        $equipment = collect(app(CommandCenterService::class)->utilization())
            ->firstWhere('label', 'Equipment');

        $this->assertSame(50, $equipment['pct']);
        $this->assertSame('2 of 4 lines confirmed or on site', $equipment['hint']);
    }

    public function test_equipment_reads_as_unknown_when_no_room_lists_any(): void
    {
        Event::factory()->create(['stage' => 'planning']);

        $equipment = collect(app(CommandCenterService::class)->utilization())
            ->firstWhere('label', 'Equipment');

        $this->assertNull($equipment['pct']);
        $this->assertStringContainsString('no equipment listed', $equipment['hint']);
    }

    public function test_portfolio_spend_comes_from_the_budget_lines(): void
    {
        $event = Event::factory()->create(['stage' => 'planning']);
        EventBudgetItem::create(['event_id' => $event->id, 'category' => 'production',
            'description' => 'Stage', 'estimated_cents' => 100_000, 'actual_cents' => 40_000]);
        EventBudgetItem::create(['event_id' => $event->id, 'category' => 'catering',
            'description' => 'Catering', 'estimated_cents' => 300_000, 'actual_cents' => 60_000]);

        $spend = app(CommandCenterService::class)->portfolioSpend();

        $this->assertSame(400_000, $spend['estimated']);
        $this->assertSame(100_000, $spend['actual']);
        $this->assertSame(2, $spend['lines']);
    }

    public function test_the_dashboard_does_not_scale_its_queries_with_the_event_count(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        $count = function () use ($user) {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $this->actingAs($user)->get('/')->assertOk();
            $n = count(DB::getQueryLog());
            DB::disableQueryLog();

            return $n;
        };

        $before = $count();

        // Ten more events must not cost ten more rounds of relation loading.
        Event::factory()->count(10)->create(['stage' => 'planning']);
        $after = $count();

        $this->assertLessThan(
            $before * 1.5,
            $after,
            "the dashboard went from $before to $after queries after adding 10 events — "
            .'health is being recomputed per event again'
        );
    }
}
