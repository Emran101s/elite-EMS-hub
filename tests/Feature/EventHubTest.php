<?php

namespace Tests\Feature;

use App\Http\Controllers\EventHubController;
use App\Livewire\EventCreate;
use App\Livewire\EventsIndex;
use App\Models\Event;
use App\Models\User;
use App\Services\EventHealthService;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
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

        // The rebuilt page: light KPI strip, portfolio deck, only Card + Calendar views.
        $this->actingAs($user)->get('/events')->assertOk()
            ->assertSee('Total Events')->assertSee('At Risk')
            ->assertSee('ICFT 2026')
            ->assertSee('Portfolio');

        // Filters narrow the card grid. Asserted through the component's paginator,
        // since the Command Spine radar always lists every event in the page HTML.
        $names = function (array $sets) use ($user) {
            $c = Livewire::actingAs($user)->test(EventsIndex::class);
            foreach ($sets as $k => $v) {
                $c->set($k, $v);   // set after mount, which re-reads the request
            }

            return collect($c->viewData('events')->items())->pluck('name');
        };

        $this->assertSame(['ICFT 2026'], $names(['exactType' => 'conference'])->all());
        $this->assertContains('Private Dinner', $names(['stage' => 'live'])->all());
        $this->assertContains('Tech Expo 2026', $names(['q' => 'Doha'])->all());

        // A removed view falls back to Cards rather than erroring.
        $this->actingAs($user)->get('/events?view=kanban')->assertOk()->assertSee('Portfolio');
    }

    public function test_wizard_builds_an_event_on_one_canvas_with_a_live_preview(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('new_client', 'Royal Office')
            ->set('name', 'Royal Gala 2027')
            ->set('starts_at', '2027-05-20')
            ->set('ends_at', '2027-05-22')
            // The preview reacts as you type — 3 days, and the crest for a gala.
            ->assertViewHas('previewDays', 3)
            ->call('chooseTemplate', 'gala')
            ->assertViewHas('previewType', 'gala_dinner')
            ->call('toggleModule', 'agenda') // gala doesn't include agenda by default — turn it on
            ->call('save')
            ->assertHasNoErrors();

        $event = Event::where('name', 'Royal Gala 2027')->firstOrFail();

        $this->assertSame('gala_dinner', $event->type);
        $this->assertContains('agenda', $event->enabled_modules);
        $this->assertSame(3, $event->agendaDays()->count(), 'the date range scaffolds agenda days');
    }

    public function test_wizard_requires_a_client_and_a_title(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->call('save')
            ->assertHasErrors(['name', 'starts_at', 'client_id']);
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
