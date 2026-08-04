<?php

namespace Tests\Unit;

use App\Events\EventStageChanged;
use App\Models\Event;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event as EventFacade;
use Tests\TestCase;

class EventLifecycleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_same_stage_is_always_a_legal_no_op(): void
    {
        $event = Event::factory()->create(['stage' => 'planning']);

        $this->assertTrue($event->canTransitionTo('planning'));
    }

    public function test_a_stage_on_the_main_line_can_move_to_the_next_one(): void
    {
        $event = Event::factory()->create(['stage' => 'draft']);

        $this->assertTrue($event->canTransitionTo('proposal'));
    }

    public function test_a_stage_cannot_skip_ahead(): void
    {
        $event = Event::factory()->create(['stage' => 'draft']);

        $this->assertFalse($event->canTransitionTo('completed'));
        $this->assertFalse($event->canTransitionTo('live'));
    }

    public function test_closed_is_terminal(): void
    {
        $event = Event::factory()->create(['stage' => 'closed']);

        $this->assertSame([], $event->nextStages());
        $this->assertFalse($event->canTransitionTo('draft'));
    }

    public function test_on_hold_can_resume_into_any_active_build_stage(): void
    {
        $event = Event::factory()->create(['stage' => 'on_hold']);

        $this->assertTrue($event->canTransitionTo('planning'));
        $this->assertTrue($event->canTransitionTo('production'));
        $this->assertFalse($event->canTransitionTo('live'));
    }

    public function test_changing_the_stage_dispatches_the_domain_event(): void
    {
        // A blanket fake() would also swallow Eloquent's own internal model
        // events, which is exactly what fires the static::updated hook that
        // dispatches this — fake only the one event under test.
        EventFacade::fake([EventStageChanged::class]);

        $event = Event::factory()->create(['stage' => 'draft']);
        $event->update(['stage' => 'proposal']);

        EventFacade::assertDispatched(EventStageChanged::class, function ($e) use ($event) {
            return $e->event->is($event) && $e->from === 'draft' && $e->to === 'proposal';
        });
    }

    public function test_saving_without_touching_the_stage_dispatches_nothing(): void
    {
        $event = Event::factory()->create(['stage' => 'draft']);

        EventFacade::fake([EventStageChanged::class]);
        $event->update(['name' => 'Renamed']);

        EventFacade::assertNotDispatched(EventStageChanged::class);
    }
}
