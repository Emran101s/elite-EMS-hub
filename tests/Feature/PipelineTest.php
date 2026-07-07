<?php

namespace Tests\Feature;

use App\Livewire\EventsIndex;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\DemoDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class PipelineTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        $this->seed(DemoDataSeeder::class);

        return User::where('email', 'emran.itan@elitebhub.com')->firstOrFail();
    }

    public function test_kanban_view_renders_pipeline_columns(): void
    {
        Livewire::actingAs($this->actor())->test(EventsIndex::class)
            ->set('view', 'kanban')
            ->assertSee('Pipeline')
            ->assertSee('Lead')
            ->assertSee('Proposal')
            ->assertSee('Confirmed')
            ->assertSee('In delivery')
            ->assertSee('Completed')
            ->assertSee('ICFT 2026');
    }

    public function test_move_stage_advances_the_event(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail(); // seeded stage: planning (Confirmed bucket)

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->set('view', 'kanban')
            ->call('moveStage', $event->id, 'delivery');

        $this->assertSame('production', $event->fresh()->stage); // 'delivery' bucket canonical
    }

    public function test_move_stage_ignores_unknown_bucket(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $before = $event->stage;

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('moveStage', $event->id, 'bogus');

        $this->assertSame($before, $event->fresh()->stage);
    }

    public function test_move_stage_wont_touch_archived_events(): void
    {
        $user = $this->actor();
        $event = Event::where('name', 'ICFT 2026')->firstOrFail();
        $event->update(['archived_at' => now(), 'stage' => 'planning']);

        Livewire::actingAs($user)->test(EventsIndex::class)
            ->call('moveStage', $event->id, 'completed');

        $this->assertSame('planning', $event->fresh()->stage);
    }
}
