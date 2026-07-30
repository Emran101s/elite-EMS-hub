<?php

namespace Tests\Feature;

use App\Livewire\EventCreate;
use App\Models\Client;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * The Studio is a workspace, not a form: it checks each room as you leave it,
 * shows the event taking shape while you define it, and writes nothing until
 * you launch.
 */
class EventStudioTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_it_opens_on_the_first_room(): void
    {
        $user = $this->actor();

        $this->actingAs($user)->get(route('events.create'))->assertOk()
            ->assertSee('Event Studio')
            ->assertSee('Define your event. The platform builds everything around it.')
            ->assertSee('Live preview')
            ->assertSee('Event Identity');
    }

    public function test_a_room_is_checked_when_you_leave_it(): void
    {
        $user = $this->actor();

        // Nothing answered: the first room refuses to let you past.
        Livewire::actingAs($user)->test(EventCreate::class)
            ->call('next')
            ->assertHasErrors(['name', 'template'])
            ->assertSet('step', 1);

        // Answered: it lets you through to When & Where.
        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('name', 'Arab Investment Summit 2027')
            ->set('new_client', 'Arab Investment Group')
            ->call('chooseTemplate', 'summit')
            ->call('next')
            ->assertHasNoErrors()
            ->assertSet('step', 2);
    }

    public function test_the_preview_reports_only_what_has_been_answered(): void
    {
        $user = $this->actor();

        $c = Livewire::actingAs($user)->test(EventCreate::class);
        $this->assertSame(0, $c->instance()->readiness()['pct'], 'an empty studio has defined nothing');

        $c->set('name', 'Arab Investment Summit 2027')
            ->set('new_client', 'Arab Investment Group')
            ->call('chooseTemplate', 'summit')
            ->set('starts_at', now()->addMonths(3)->toDateString())
            ->set('city', 'Amman');

        $after = $c->instance()->readiness();

        $this->assertGreaterThan(0, $after['pct']);
        $this->assertLessThan(100, $after['pct'], 'it does not claim progress that has not been made');
        $this->assertContains('A sentence about it', $after['missing']);
    }

    /**
     * The module tiles are grouped by category for reading, but the key is the
     * identity — a grouping that renumbers them leaves every tile posting the
     * same meaningless key and nothing switches on. Assert the wiring, not just
     * the method: toggleModule() on its own passed while the page was dead.
     */
    public function test_every_module_tile_is_wired_to_its_own_key(): void
    {
        $user = $this->actor();

        $studio = Livewire::actingAs($user)->test(EventCreate::class)
            ->call('chooseTemplate', 'summit')
            ->call('goTo', 3);

        foreach (array_keys(Event::HUB_MODULES) as $key) {
            $studio->assertSeeHtml("toggleModule('{$key}')");
        }

        // And the switch itself works from both directions.
        $on = $studio->get('modules');
        $this->assertContains('budget', $on);

        $studio->call('toggleModule', 'budget')
            ->assertSet('modules', fn (array $m) => ! in_array('budget', $m, true))
            ->call('toggleModule', 'budget')
            ->assertSet('modules', fn (array $m) => in_array('budget', $m, true));
    }

    public function test_nothing_is_written_until_launch(): void
    {
        $user = $this->actor();
        $before = Event::count();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('name', 'Arab Investment Summit 2027')
            ->set('new_client', 'Arab Investment Group')
            ->call('chooseTemplate', 'summit')
            ->set('starts_at', now()->addMonths(3)->toDateString())
            ->call('goTo', 5);

        $this->assertSame($before, Event::count(), 'walking to the last room creates nothing');
    }

    public function test_launching_builds_the_event_the_preview_described(): void
    {
        $user = $this->actor();
        $start = now()->addMonths(3)->startOfDay();

        Livewire::actingAs($user)->test(EventCreate::class)
            ->set('name', 'Arab Investment Summit 2027')
            ->set('new_client', 'Arab Investment Group')
            ->call('chooseTemplate', 'summit')
            ->set('statusPill', 'proposal')
            ->set('priority', 'high')
            ->set('starts_at', $start->toDateString())
            ->set('ends_at', $start->copy()->addDays(2)->toDateString())
            ->set('city', 'Amman')
            ->set('description', 'A premier gathering of investors.')
            ->set('expected_participants', '1200')
            ->set('budget', '480000')
            ->call('save');

        $event = Event::where('name', 'Arab Investment Summit 2027')->firstOrFail();

        $this->assertSame('summit', $event->type);
        $this->assertSame('proposal', $event->stage);
        $this->assertSame('high', $event->priority);
        $this->assertSame(1200, $event->expected_participants);
        $this->assertSame(48_000_000, $event->budget_cents);
        $this->assertSame('Arab Investment Group', $event->client->name);

        // The dates scaffold the agenda, which is what the preview promised.
        $this->assertCount(3, $event->agendaDays);
        $this->assertNotEmpty($event->enabled_modules);
    }
}
