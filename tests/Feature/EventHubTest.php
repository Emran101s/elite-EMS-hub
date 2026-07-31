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

        // The cover carries the name, where and when, the owner of the next
        // critical action, and health. The client, the PM and the stage are
        // deliberately on the Overview beneath it — see event-header.blade.php.
        // This used to assert the client name too, and passed only because the
        // sidebar's portfolio tree happened to print it; the cover never did.
        $this->actingAs($user)->get(route('events.hub', $event))->assertOk()
            ->assertSee('ICFT 2026 — Event Hub')
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

        // The portfolio has three views and no more: the Deck to browse it,
        // the List to work it, the Flight Path to plan it.
        $this->actingAs($user)->get('/events')->assertOk()
            ->assertSee('Projects &amp; Events', false)
            ->assertSee('Deck View')->assertSee('List View')->assertSee('Flight Path')
            ->assertSee('Active mission')
            ->assertSee('ICFT 2026')
            ->assertSee('Next milestone')
            ->assertSee('AI insight');

        $this->actingAs($user)->get('/events?view=list')->assertOk()
            ->assertSee('Operational management')
            ->assertSee('Quick actions')
            ->assertSee('ICFT 2026');

        $this->actingAs($user)->get('/events?view=path')->assertOk()
            ->assertSee('Strategic event timeline')
            ->assertSee('ICFT 2026');

        // Filters narrow the board. Asserted through the component's paginator,
        // since the Event Radar always lists every event in the page HTML.
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

        // Anything else falls back to the Deck rather than erroring.
        $this->actingAs($user)->get('/events?view=kanban')->assertOk()->assertSee('Active mission');
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

    public function test_the_header_reports_scale_readiness_and_what_needs_a_person(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $event->load(\App\Services\EventCommandHeader::RELATIONS);

        $header = app(\App\Services\EventCommandHeader::class)->for($event);

        // Every figure is counted, so it has to agree with the records.
        $this->assertSame($event->speakers->count(), (int) collect($header['scale'])->firstWhere('label', 'Speakers')['value']);
        $this->assertSame($event->sponsors->count(), (int) collect($header['scale'])->firstWhere('label', 'Sponsors')['value']);

        // Readiness is gates met over gates that apply — never over gates the
        // event has no data for.
        $this->assertNotEmpty($header['readiness']['gates']);
        $this->assertSame(
            (int) round($header['readiness']['met'] / $header['readiness']['total'] * 100),
            $header['readiness']['pct'],
        );

        // The header is one card now, so these read shorter than the four
        // blocks they replace — same four things, a third of the height.
        $this->actingAs($user)->get(route('events.hub', $event))->assertOk()
            ->assertSee('Next')
            ->assertSee('Readiness')
            ->assertSee('Health');
    }

    /**
     * The card must not go quiet while work is still open. It used to require a
     * risk above the severe line, a pending approval, or a task with a due date
     * — so an event carrying medium risks and undated tasks reported that
     * nothing was waiting, and its header looked like a different design from
     * the event next to it.
     */
    public function test_the_critical_card_sees_lesser_risks_and_undated_tasks(): void
    {
        $this->actor();
        $service = app(\App\Services\EventCommandHeader::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        // Clear the decks, then leave exactly one undated open task.
        $event->risks()->delete();
        $event->approvals()->delete();
        $event->tasks()->delete();
        $event->tasks()->create([
            'title' => 'Sign off the floor plan',
            'status' => 'todo',
            'priority' => 'high',
            'due_on' => null,
        ]);

        $critical = $service->critical($event->fresh()->load(\App\Services\EventCommandHeader::RELATIONS));
        $this->assertSame('Sign off the floor plan', $critical['title']);
        $this->assertSame('No date set', $critical['due']);

        // A medium open risk outranks the undated task.
        // 3 × 3 = 9, below the severe line of 12 — the case that used to vanish.
        $event->risks()->create([
            'title' => 'Venue contract unsigned',
            'category' => 'contractual',
            'status' => 'open',
            'probability' => 3,
            'impact' => 3,
        ]);

        $critical = $service->critical($event->fresh()->load(\App\Services\EventCommandHeader::RELATIONS));
        $this->assertSame('Venue contract unsigned', $critical['title']);
        $this->assertSame('risks', $critical['tab']);
    }

    /**
     * One header for every event: whether or not anything is waiting, the card
     * renders the same skeleton — headline, source, Due / Owner / Risk level,
     * and a way in. The empty state used to be a different, shorter card, which
     * is what made two events look like two designs.
     */
    public function test_the_header_keeps_its_shape_when_nothing_is_waiting(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $event->risks()->delete();
        $event->approvals()->delete();
        $event->tasks()->delete();

        $this->actingAs($user)->get(route('events.hub', $event))->assertOk()
            ->assertSee('Next')
            ->assertSee('Nothing is waiting on you')
            ->assertSee('Due')
            ->assertSee('Owner')
            ->assertSee('Risk level')
            ->assertSee('Clear');
    }

    /**
     * Twenty tabs of identical weight mean the only way to learn whether Risks
     * has anything in it is to open Risks. Each count must mean exactly one
     * thing — a number on a tab that turns out to mean something else is worse
     * than a bare tab.
     */
    public function test_the_tabs_say_where_the_work_is(): void
    {
        $user = $this->actor();
        $service = app(\App\Services\EventCommandHeader::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $event->tasks()->delete();
        $event->risks()->delete();
        $event->approvals()->delete();

        // A quiet event puts nothing on any tab.
        $this->assertSame([], $service->attention($event->fresh()->load(\App\Services\EventCommandHeader::RELATIONS)));

        // One overdue task, one open one that is not yet due: only the first counts.
        $event->tasks()->create(['title' => 'Late', 'status' => 'todo', 'priority' => 'normal', 'due_on' => now()->subWeek()]);
        $event->tasks()->create(['title' => 'Soon', 'status' => 'todo', 'priority' => 'normal', 'due_on' => now()->addWeek()]);
        $event->approvals()->create(['title' => 'Agenda', 'type' => 'agenda', 'status' => 'pending']);

        $a = $service->attention($event->fresh()->load(\App\Services\EventCommandHeader::RELATIONS));

        $this->assertSame(1, $a['tasks']['count']);
        $this->assertSame('1 overdue', $a['tasks']['why']);
        $this->assertSame('alarm', $a['tasks']['tone'], 'past its date is an alarm, not a workload');

        $this->assertSame(1, $a['approvals']['count']);
        $this->assertSame('wait', $a['approvals']['tone']);

        $this->assertArrayNotHasKey('risks', $a, 'a module with nothing waiting stays bare');
        $this->assertArrayNotHasKey('budget', $a, 'and a module with no defined signal is never invented');

        // And it reaches the tab row.
        $this->actingAs($user)->get(route('events.hub', $event))->assertOk()
            ->assertSee('1 overdue')
            ->assertSee('1 pending');
    }

    /**
     * The way to the next module survives the scroll.
     *
     * There used to be a separate 41px rail pinned above the tabs, because the
     * tabs themselves cost 136px and pinning those would have eaten a seventh
     * of every screenful. The nav is 70px now, so it is the thing that sticks
     * and the rail is gone — one pinned element instead of two, and it is the
     * one you actually use.
     */
    public function test_the_module_nav_survives_the_scroll(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $event->tasks()->delete();
        $event->tasks()->create(['title' => 'Late', 'status' => 'todo', 'priority' => 'normal', 'due_on' => now()->subWeek()]);

        $html = $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'budget']))
            ->assertOk()->getContent();

        $nav = (string) str($html)->after('aria-label="Event modules"')->before('</nav>');
        $box = (string) str($html)->before('aria-label="Event modules"')->afterLast('<div ');

        $this->assertStringContainsString('sticky top-0', $box, 'the nav must survive the scroll');
        $this->assertStringContainsString('Tasks', $nav);

        // Its counts come from the same place as the header's, so the two can
        // never disagree about what is waiting.
        $this->assertStringContainsString('1 overdue', $nav);
    }

    /** A draft is waiting on nobody; a sent document is waiting on a pen. */
    public function test_only_issued_documents_count_against_the_contract_tab(): void
    {
        $this->actor();
        $service = app(\App\Services\EventCommandHeader::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $contract = \App\Models\EventContract::forEvent($event);
        $contract->update(['status' => 'draft']);

        $load = fn () => $event->fresh()->load(\App\Services\EventCommandHeader::RELATIONS);
        $this->assertArrayNotHasKey('contract', $service->attention($load()));

        $contract->update(['status' => 'sent']);
        $this->assertSame('1 awaiting signature', $service->attention($load())['contract']['why']);
    }

    public function test_a_name_with_an_edition_splits_and_a_plain_name_does_not(): void
    {
        $this->actor();
        $service = app(\App\Services\EventCommandHeader::class);

        $split = $service->title(new Event(['name' => 'The First World Public Summit . Arab World']));
        $this->assertSame('The First World Public Summit', $split['lead']);
        $this->assertSame('Arab World', $split['tail']);

        $plain = $service->title(new Event(['name' => 'ICFT 2026']));
        $this->assertSame('ICFT 2026', $plain['lead']);
        $this->assertNull($plain['tail']);
    }
}
