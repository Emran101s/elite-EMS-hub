<?php

namespace Tests\Feature;

use App\Livewire\WorkflowSettings;
use App\Models\Deal;
use App\Models\Event;
use App\Models\Task;
use App\Models\TaxonomyTerm;
use App\Models\User;
use App\Support\Taxonomy;
use App\Support\Workflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Workflow states.
 *
 * The distinction this whole class exists to hold: a type is vocabulary you
 * add to, a state is a position the code reasons about. `won` closes a deal
 * and creates an event; `done` takes a task out of the count. So the wording
 * and the colour are yours, and the keys are not — and nothing arriving from
 * a browser can add a state or take one away.
 */
class WorkflowSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function boot(): User
    {
        Workflow::forget();

        return User::factory()->create();
    }

    public function test_every_set_is_seeded_by_migration_and_seeding_again_changes_nothing(): void
    {
        $this->boot();

        $before = TaxonomyTerm::where('taxonomy', 'like', 'state:%')->count();

        foreach (Workflow::SETS as $set => $meta) {
            $this->assertCount(
                count($meta['states']),
                Workflow::rows($set),
                $set.' did not arrive complete'
            );
        }

        $this->assertSame(0, Workflow::seed(), 'seeding twice must add nothing');
        $this->assertSame($before, TaxonomyTerm::where('taxonomy', 'like', 'state:%')->count());
    }

    /** The point of the screen: your wording, reaching the records. */
    public function test_renaming_a_stage_changes_what_every_record_is_called(): void
    {
        $user = $this->boot();
        $task = Task::factory()->for(Event::factory()->create(['stage' => 'planning']))->create(['status' => 'doing']);

        $this->assertSame('In Progress', $task->stageLabel());

        Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'task_stage')
            ->set('labels.doing', 'On the floor')
            ->set('colors.doing', '#112233')
            ->call('save')
            ->assertHasNoErrors();

        Workflow::forget();

        $this->assertSame('On the floor', $task->fresh()->stageLabel());
        $this->assertSame('#112233', $task->fresh()->stageHex());
        $this->assertSame('doing', $task->fresh()->status, 'the key the code reasons about never moves');
    }

    public function test_the_stage_a_deal_is_won_at_keeps_its_key_however_it_is_renamed(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'deal_stage')
            ->set('labels.won', 'Signed & sealed')
            ->call('save');

        Workflow::forget();

        // The label moved; the key the pipeline switches on did not.
        $this->assertSame('Signed & sealed', Workflow::label('deal_stage', 'won'));
        $this->assertArrayHasKey('won', Deal::STAGES);
        $this->assertNotContains('won', Deal::OPEN, 'won is still the closing stage');
    }

    /**
     * The sets are closed. Nothing arriving from a browser may widen them,
     * however the payload is shaped.
     */
    public function test_a_state_the_platform_does_not_declare_cannot_be_added(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'task_stage')
            ->set('labels.invented', 'Something Else')
            ->set('colors.invented', '#ff0000')
            ->call('save');

        $this->assertNull(
            TaxonomyTerm::where('taxonomy', Workflow::taxonomy('task_stage'))->where('key', 'invented')->first(),
            'only the keys the set declares are written'
        );
        $this->assertCount(count(Workflow::SETS['task_stage']['states']), Workflow::rows('task_stage'));
    }

    public function test_a_state_needs_a_name_and_a_real_colour(): void
    {
        $user = $this->boot();

        Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'task_stage')
            ->set('labels.done', '')
            ->call('save')
            ->assertHasErrors('labels.done');

        Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'task_stage')
            ->set('colors.done', 'greenish')
            ->call('save')
            ->assertHasErrors('colors.done');

        Workflow::forget();
        $this->assertSame('Done', Workflow::label('task_stage', 'done'), 'nothing was written');
    }

    public function test_a_set_can_be_put_back_to_what_it_shipped_with(): void
    {
        $user = $this->boot();

        $screen = Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'event_stage')
            ->set('labels.live', 'On Site')
            ->call('save');

        Workflow::forget();
        $this->assertSame('On Site', Workflow::label('event_stage', 'live'));

        $screen->call('restore');
        Workflow::forget();

        $this->assertSame('Live', Workflow::label('event_stage', 'live'));
        $this->assertSame('#22C55E', Workflow::color('event_stage', 'live'));
    }

    public function test_order_is_the_order_the_columns_appear_in(): void
    {
        $user = $this->boot();

        $reversed = array_reverse(array_keys(Workflow::SETS['task_stage']['states']));

        Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'task_stage')
            ->call('reorder', $reversed);

        Workflow::forget();

        $this->assertSame($reversed, Workflow::rows('task_stage')->pluck('key')->all());
    }

    /**
     * Two registries, one table. A state must never show up on the Types &
     * Lists screen, and seed()/adopt() must never touch one.
     */
    public function test_states_and_types_never_leak_into_each_other(): void
    {
        $this->boot();

        foreach (array_keys(Workflow::SETS) as $set) {
            $this->assertArrayNotHasKey($set, Taxonomy::LISTS, $set.' is a state, not a list');
            $this->assertStringStartsWith('state:', Workflow::taxonomy($set));
        }

        $statesBefore = TaxonomyTerm::where('taxonomy', 'like', 'state:%')->count();

        Taxonomy::seed();
        Taxonomy::adopt();

        $this->assertSame($statesBefore, TaxonomyTerm::where('taxonomy', 'like', 'state:%')->count());
    }

    public function test_an_event_stage_colour_reaches_the_crest_it_paints(): void
    {
        $user = $this->boot();
        $event = Event::factory()->create(['stage' => 'production']);

        $this->assertSame('#D4AF37', Event::stageColor($event->stage));

        Livewire::actingAs($user)->test(WorkflowSettings::class)
            ->call('pick', 'event_stage')
            ->set('colors.production', '#8811AA')
            ->call('save');

        Workflow::forget();

        $this->assertSame('#8811AA', Event::stageColor($event->stage));
    }

    public function test_the_screen_renders_and_settings_links_to_it(): void
    {
        $user = $this->boot();

        $this->actingAs($user)->get(route('settings.index'))
            ->assertOk()
            ->assertSee('Statuses &amp; Colours', false)
            ->assertSee(route('workflows.index'));

        $this->actingAs($user)->get(route('workflows.index'))
            ->assertOk()
            ->assertSee('Event stages')
            ->assertSee('Deal stages')
            ->assertSee('Payment statuses');
    }
}
