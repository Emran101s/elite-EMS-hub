<?php

namespace Tests\Feature;

use App\Http\Controllers\EventHubController;
use App\Livewire\EventCreate;
use App\Livewire\EventsIndex;
use App\Models\Event;
use App\Models\EventContract;
use App\Models\User;
use App\Services\EventCommandHeader;
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

        foreach (EventHubController::tabs() as $tab) {
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

        // Phase C.1 restructured the portfolio: five views, named in the UI as
        // Mission Board, Radar, Timeline, Table and Calendar. The Deck is
        // retired and "Flight Path" is now called Timeline.
        $this->actingAs($user)->get('/events')->assertOk()
            ->assertSee('Event Portfolio')
            ->assertSee('Mission Board')->assertSee('Radar')
            ->assertSee('Timeline')->assertSee('Table')->assertSee('Calendar')
            ->assertSee('ICFT 2026');

        $this->actingAs($user)->get('/events?view=list')->assertOk()
            ->assertSee('ICFT 2026');

        $this->actingAs($user)->get('/events?view=path')->assertOk()
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

        // Anything else falls back to Mission Board rather than erroring.
        $this->actingAs($user)->get('/events?view=kanban')->assertOk()->assertSee('Mission Board');
    }

    public function test_wizard_builds_an_event_on_one_canvas_with_a_live_preview(): void
    {
        $this->seed(DemoDataSeeder::class);
        $user = User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('originKind', 'commercial')
            ->set('originSource', 'proposal')
            ->set('new_client', 'Royal Office')
            ->set('name', 'Royal Gala 2027')
            ->set('starts_at', '2027-05-20')
            ->set('ends_at', '2027-05-22')
            // The preview reacts as you type — 3 days, and the crest for a gala.
            ->assertViewHas('previewDays', 3)
            ->call('chooseCategory', 'gala')
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
            ->assertHasErrors(['name', 'starts_at', 'category', 'originKind', 'originSource']);
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
        $event->load(EventCommandHeader::RELATIONS);

        $header = app(EventCommandHeader::class)->for($event);

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
        $service = app(EventCommandHeader::class);
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

        $critical = $service->critical($event->fresh()->load(EventCommandHeader::RELATIONS));
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

        $critical = $service->critical($event->fresh()->load(EventCommandHeader::RELATIONS));
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

        // Phase E folded the header's three-card grid into one hub mission
        // card, so "Risk level" and "Nothing is waiting on you" are gone as
        // labels. The contract this test protects is unchanged: with nothing
        // outstanding the header keeps its shape rather than collapsing, and
        // says so plainly instead of inventing an errand.
        $this->actingAs($user)->get(route('events.hub', $event))->assertOk()
            ->assertSee('Next action')
            ->assertSee('Nothing pressing')
            ->assertSee('Owner')
            ->assertSee('Health')
            ->assertSee('Readiness')
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
        $service = app(EventCommandHeader::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $event->tasks()->delete();
        $event->risks()->delete();
        $event->approvals()->delete();

        // Money is a signal too: a room held at a rate nobody has agreed is a
        // commitment the budget cannot count. Quiet means quiet everywhere.
        $event->roomBlocks()->delete();
        $event->transport()->delete();
        $event->rooms()->each(fn ($r) => $r->update(['cost_cents' => 1_000_00]));

        // A quiet event puts nothing on any tab.
        $this->assertSame([], $service->attention($event->fresh()->load(EventCommandHeader::RELATIONS)));

        // One overdue task, one open one that is not yet due: only the first counts.
        $event->tasks()->create(['title' => 'Late', 'status' => 'todo', 'priority' => 'normal', 'due_on' => now()->subWeek()]);
        $event->tasks()->create(['title' => 'Soon', 'status' => 'todo', 'priority' => 'normal', 'due_on' => now()->addWeek()]);
        $event->approvals()->create(['title' => 'Agenda', 'type' => 'agenda', 'status' => 'pending']);

        $a = $service->attention($event->fresh()->load(EventCommandHeader::RELATIONS));

        $this->assertSame(1, $a['tasks']['count']);
        $this->assertSame('1 overdue', $a['tasks']['why']);
        $this->assertSame('alarm', $a['tasks']['tone'], 'past its date is an alarm, not a workload');

        $this->assertSame(1, $a['approvals']['count']);
        $this->assertSame('wait', $a['approvals']['tone']);

        $this->assertArrayNotHasKey('risks', $a, 'a module with nothing waiting stays bare');
        $this->assertArrayNotHasKey('budget', $a, '…and money that is all accounted for is not a signal');

        // Hold rooms without a rate and the Budget tab says so, because the
        // rooms are real and the budget cannot count them.
        $event->roomBlocks()->create([
            'hotel' => 'Unpriced Inn', 'rooms_count' => 12, 'rate_cents' => 0,
            'check_in' => now()->addMonth(), 'check_out' => now()->addMonth()->addDays(3), 'status' => 'held',
        ]);

        $b = $service->attention($event->fresh()->load(EventCommandHeader::RELATIONS));

        $this->assertSame(1, $b['budget']['count']);
        $this->assertSame('1 not costed', $b['budget']['why']);

        // And it reaches the tab row — Phase E made that row contextual to
        // the active Journey stage, so "1 overdue" (Tasks, in Planning) and
        // "1 pending" (Approvals, in Control) each only show up on their own
        // stage now, not from Overview.
        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'tasks']))->assertOk()
            ->assertSee('1 overdue');
        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'approvals']))->assertOk()
            ->assertSee('1 pending');
    }

    /**
     * Satellites are scoped to the active stage.
     *
     * Phase E made the old secondary nav row contextual to the active
     * Journey stage; Phase E.2 replaced that row with satellites attached to
     * the Orbit's active stage, but kept the same contract — "Tasks" (and
     * its own overdue count) only appears while Planning is the active
     * stage, not from every tab. This checks Budget (Commercial) has no
     * reason to carry Planning's detail, and that Tasks' own stage
     * (Planning) does.
     */
    public function test_satellites_are_scoped_to_the_active_stage(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $event->tasks()->delete();
        $event->tasks()->create(['title' => 'Late', 'status' => 'todo', 'priority' => 'normal', 'due_on' => now()->subWeek()]);

        // Note: "1 overdue" alone isn't a safe assertDontSee target — the
        // pre-existing, unmoved Priority Area also reports overdue tasks
        // ("1 overdue task", Event Escalations) on every stage, event-wide.
        // The scoped check is that Planning's satellite section itself does
        // not render while Commercial is active.
        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'budget']))
            ->assertOk()
            ->assertSee('Commercial · satellites')
            ->assertDontSee('Planning · satellites');

        // Tasks lives in the Planning stage — its satellite, and its own
        // overdue count, travels with the active stage.
        $this->actingAs($user)->get(route('events.hub', [$event, 'tab' => 'tasks']))
            ->assertOk()
            ->assertSee('Planning · satellites')
            ->assertSee('1 overdue');
    }

    /** A draft is waiting on nobody; a sent document is waiting on a pen. */
    public function test_only_issued_documents_count_against_the_contract_tab(): void
    {
        $this->actor();
        $service = app(EventCommandHeader::class);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        $contract = EventContract::forEvent($event);
        $contract->update(['status' => 'draft']);

        $load = fn () => $event->fresh()->load(EventCommandHeader::RELATIONS);
        $this->assertArrayNotHasKey('contract', $service->attention($load()));

        $contract->update(['status' => 'sent']);
        $this->assertSame('1 awaiting signature', $service->attention($load())['contract']['why']);
    }

    public function test_a_name_with_an_edition_splits_and_a_plain_name_does_not(): void
    {
        $this->actor();
        $service = app(EventCommandHeader::class);

        $split = $service->title(new Event(['name' => 'The First World Public Summit . Arab World']));
        $this->assertSame('The First World Public Summit', $split['lead']);
        $this->assertSame('Arab World', $split['tail']);

        $plain = $service->title(new Event(['name' => 'ICFT 2026']));
        $this->assertSame('ICFT 2026', $plain['lead']);
        $this->assertNull($plain['tail']);
    }
}
