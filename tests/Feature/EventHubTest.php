<?php

namespace Tests\Feature;

use App\Http\Controllers\EventHubController;
use App\Models\Event;
use App\Models\User;
use App\Services\EventHealthService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EventHubTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_hub_cover_shows_identity_and_health(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $this->actingAs($user)->get(route('events.hub', $event))->assertOk()
            ->assertSee('ICFT 2026 — Event Hub')
            ->assertSee('ICFT Global Committee')
            ->assertSee('Layla Haddad')
            ->assertSee('Health');
    }

    public function test_every_tab_renders(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        foreach (EventHubController::TABS as $tab) {
            $this->actingAs($user)
                ->get(route('events.hub', [$event, 'tab' => $tab]))
                ->assertOk();
        }
    }

    public function test_tabs_render_operational_data(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'agenda']))
            ->assertSee('Opening Ceremony')->assertSee('Keynote: The Future of Financial Technology')->assertSee('Main Hall');

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'budget']))
            ->assertSee('INV-2026-014')->assertSee('Royal Convention Centre — 3 days');

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'sponsors']))
            ->assertSee('Gulf National Bank')->assertSee('Zain Telecom');

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'risks']))
            ->assertSee('Sponsor logo files missing');

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'approvals']))
            ->assertSee('Q3 budget revision')->assertSee('Main stage design concept');

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'venue']))
            ->assertSee('VIP Lounge')->assertSee('Breakout Room 1');
    }

    public function test_health_score_bands_and_critical_risk_override(): void
    {
        $this->seed(DemoDataSeeder::class);
        $service = app(EventHealthService::class);

        $icft = $service->breakdown(Event::where('name', 'ICFT 2026')->firstOrFail());
        $this->assertGreaterThanOrEqual(0, $icft['score']);
        $this->assertLessThanOrEqual(100, $icft['score']);
        foreach ($icft['components'] as $score) {
            if ($score !== null) {
                $this->assertGreaterThanOrEqual(0, $score);
                $this->assertLessThanOrEqual(100, $score);
            }
        }

        // Tech Expo carries an escalated severity-20 risk → capped at ≤ 60.
        $expo = $service->breakdown(Event::where('name', 'Tech Expo 2026')->firstOrFail());
        $this->assertLessThanOrEqual(60, $expo['score']);
        $this->assertSame('Booth production behind schedule', $expo['critical_risk']);
    }

    public function test_ai_summary_names_real_records(): void
    {
        $this->seed(DemoDataSeeder::class);

        $ai = app(EventHealthService::class)->aiSummary(Event::where('name', 'ICFT 2026')->firstOrFail());

        $this->assertStringContainsString('ICFT 2026 is', $ai['headline']);
        $this->assertNotEmpty($ai['attention']);
        $this->assertStringContainsString('Q3 budget revision', implode(' ', $ai['attention']));
    }

    public function test_events_overview_kpis_and_filters(): void
    {
        $user = $this->actor();

        $this->actingAs($user)->get('/events')->assertOk()
            ->assertSee('Total Events')->assertSee('At Risk')
            ->assertSee('ICFT 2026')
            ->assertSee('Event Control Room') // preview panel shortcuts
            ->assertSee('AI Recommendation')
            ->assertSee('Showing 1 to 6 of 6 events');

        // Filters narrow the grid (radar in the Command Spine always lists all
        // events, so assert the filtered paginator count rather than absence).
        $this->actingAs($user)->get('/events?type=conference')->assertOk()
            ->assertSee('ICFT 2026')->assertSee('Showing 1 to 1 of 1 events');

        $this->actingAs($user)->get('/events?stage=live')->assertOk()
            ->assertSee('Private Dinner')->assertSee('Showing 1 to 1 of 1 events');

        $this->actingAs($user)->get('/events?q=Doha')->assertOk()
            ->assertSee('Tech Expo 2026')->assertSee('Showing 1 to 1 of 1 events');
    }

    public function test_wizard_walks_three_steps_and_creates_event(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        \Livewire\Livewire::actingAs($user)->test(\App\Livewire\EventCreate::class)
            ->set('new_client', 'Royal Office')
            ->set('name', 'Royal Gala 2027')
            ->set('starts_at', '2027-05-20')
            ->call('next')->assertSet('step', 2)
            ->call('chooseTemplate', 'gala')
            ->call('next')->assertSet('step', 3)
            ->call('toggleModule', 'agenda') // turn agenda on
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('name', 'Royal Gala 2027')->firstOrFail();

        $this->assertSame('gala_dinner', $event->type);
        $this->assertSame('gala-dinner', $event->avatar->slug);
        $this->assertContains('agenda', $event->enabled_modules);
    }

    public function test_disabled_module_tab_falls_back_to_overview(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        // Enable only budget; agenda is now off.
        $event->update(['enabled_modules' => ['budget']]);

        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'budget']))
            ->assertOk()->assertSee('Budget');

        // Requesting a disabled tab silently shows Overview (no agenda tab link).
        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'agenda']))
            ->assertOk()->assertSee('Event Overview');
    }
}
