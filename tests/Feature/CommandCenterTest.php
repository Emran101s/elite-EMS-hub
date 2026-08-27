<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Event;
use App\Models\User;
use App\Services\EventHealthService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * There is one dashboard. It answers "when" — today, this week, the book —
 * which is the job no other screen on the platform has.
 */
class CommandCenterTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_the_dashboard_renders_its_sections(): void
    {
        $user = $this->actor();

        // Phase D: action-first — the KPI strip, Today's Command Queue and
        // Executive Intelligence replaced the old figures row and the
        // "book by stage" card that used to carry these labels.
        $this->actingAs($user)->get('/')->assertOk()
            ->assertSee('Command Queue')
            ->assertSee('The week ahead')
            ->assertSee('Nearest missions')
            ->assertSee('Command Briefing')
            ->assertSee('Active Events');
    }

    public function test_the_operations_room_is_gone_and_its_url_lands_here(): void
    {
        $user = $this->actor();

        // One dashboard: the old URL keeps working rather than 404ing.
        $this->actingAs($user)->get('/operations-room')->assertRedirect('/');
    }

    public function test_the_spotlight_is_what_is_live_or_what_is_next(): void
    {
        $user = $this->actor();

        $view = Livewire::actingAs($user)->test(Dashboard::class);
        $spotlight = $view->viewData('spotlight');
        $today = Carbon::today();

        $this->assertNotNull($spotlight);

        if ($view->viewData('spotlightLive')) {
            $this->assertTrue($spotlight->starts_at->startOfDay()->lte($today));
            $this->assertTrue(($spotlight->ends_at ?? $spotlight->starts_at)->endOfDay()->gte($today));
        } else {
            // Nothing running: it is the soonest event still to come.
            $soonest = Event::whereNull('archived_at')->whereDate('starts_at', '>=', $today)
                ->orderBy('starts_at')->first();
            $this->assertTrue($spotlight->is($soonest));
        }
    }

    public function test_the_week_is_seven_days_starting_today(): void
    {
        $user = $this->actor();

        $week = Livewire::actingAs($user)->test(Dashboard::class)->viewData('week');

        $this->assertCount(7, $week);
        $this->assertTrue($week->first()['date']->isToday());
        $this->assertTrue($week->first()['today']);
        $this->assertTrue($week->last()['date']->isSameDay(Carbon::today()->addDays(6)));

        // The load is the sum of its parts, not a separate number.
        foreach ($week as $day) {
            $this->assertSame($day['sessions'] + $day['movements'] + $day['tasks'], $day['load']);
        }
    }

    public function test_the_figures_mirror_the_health_engine(): void
    {
        $user = $this->actor();

        // Renamed for Phase D's KPI strip — same figures, same computation.
        $kpis = collect(Livewire::actingAs($user)->test(Dashboard::class)->viewData('kpis'));
        $service = app(EventHealthService::class);

        $expected = Event::whereNull('archived_at')->with(EventHealthService::RELATIONS)->get()
            ->filter(fn (Event $e) => in_array($service->breakdown($e)['status'], ['at_risk', 'behind'], true))
            ->count();

        $this->assertSame($expected, $kpis->firstWhere('label', 'Operational Risks')['value']);
        $this->assertSame(
            Event::whereNull('archived_at')->count(),
            $kpis->firstWhere('label', 'Active Events')['value'],
        );
    }
}
