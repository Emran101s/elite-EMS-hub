<?php

namespace Tests\Feature;

use App\Livewire\CommandPalette;
use App\Livewire\TeamRoster;
use App\Models\Event;
use App\Models\User;
use App\Models\Venue;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CommandPaletteTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_palette_is_mounted_in_the_app_shell(): void
    {
        $this->actingAs($this->actor())->get(route('events.index'))
            ->assertOk()
            ->assertSeeLivewire(CommandPalette::class);
    }

    public function test_it_finds_records_across_entity_types(): void
    {
        $user = $this->actor();

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'ICFT')
            ->assertSee('ICFT 2026');

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'Royal')
            ->assertSee('Royal Convention Centre')     // venue
            ->assertSee('Royal Catering Services');    // supplier
    }

    public function test_results_open_the_record_not_just_its_directory(): void
    {
        $user = $this->actor();
        $venue = Venue::where('name', 'Royal Convention Centre')->firstOrFail();

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'Royal')
            ->assertSee(route('venues.show', $venue), false)
            ->assertSee(route('suppliers.index', ['q' => 'Royal Catering Services']), false);

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'Layla')
            ->assertSee(route('team.index', ['q' => 'Layla Haddad']), false);
    }

    public function test_the_team_roster_opens_filtered_from_its_url(): void
    {
        Livewire::withQueryParams(['q' => 'Layla'])
            ->actingAs($this->actor())
            ->test(TeamRoster::class)
            ->assertSet('search', 'Layla');
    }

    public function test_the_empty_palette_suggests_where_to_go(): void
    {
        Livewire::actingAs($this->actor())->test(CommandPalette::class)
            ->assertSee('Suggested')
            ->assertSee(route('tasks.mine'), false)
            ->assertSee(route('approvals.index'), false)
            ->assertSee(route('events.create'), false);
    }

    public function test_an_event_and_a_tab_jump_straight_into_that_tab(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'icft budget')
            ->assertSee('ICFT 2026 › Budget')
            ->assertSee(route('events.hub', [$event, 'tab' => 'budget']), false)
            ->set('q', 'icft hotel')
            ->assertSee(route('events.hub', [$event, 'tab' => 'accommodation']), false);
    }

    public function test_new_task_on_an_event_opens_its_board_with_the_form_started(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();

        Livewire::actingAs($user)->test(CommandPalette::class)
            ->set('q', 'new task on icft')
            ->assertSee(route('events.hub', [$event, 'tab' => 'tasks', 'action' => 'add']));   // escaped: & is &amp; in the href
    }

    public function test_places_are_found_by_what_people_call_them(): void
    {
        Livewire::actingAs($this->actor())->test(CommandPalette::class)
            ->set('q', 'overdue invoices')
            ->assertSee(route('invoices.index', ['state' => 'overdue']), false)
            ->set('q', 'approve')
            ->assertSee(route('approvals.index'), false);
    }

    public function test_it_never_offers_an_event_you_cannot_open(): void
    {
        $this->actor();   // seeds the book
        $outsider = User::factory()->create(['role' => 'coordinator']);
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $this->assertFalse($outsider->can('view', $event));

        Livewire::actingAs($outsider)->test(CommandPalette::class)
            ->set('q', 'ICFT')
            ->assertDontSee(route('events.hub', $event), false)
            ->set('q', 'icft budget')
            ->assertDontSee('ICFT 2026 › Budget');
    }

    public function test_a_viewer_is_not_offered_new_event(): void
    {
        $this->actor();
        $viewer = User::factory()->create(['role' => 'viewer']);

        Livewire::actingAs($viewer)->test(CommandPalette::class)
            ->assertDontSee(route('events.create'), false)
            ->set('q', 'new event')
            ->assertDontSee(route('events.create'), false);
    }

    public function test_short_queries_return_nothing(): void
    {
        // One character would match half the database — stay quiet until two.
        Livewire::actingAs($this->actor())->test(CommandPalette::class)
            ->set('q', 'I')
            ->assertDontSee('ICFT 2026');
    }
}
