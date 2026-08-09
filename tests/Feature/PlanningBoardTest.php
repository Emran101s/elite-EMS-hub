<?php

namespace Tests\Feature;

use App\Livewire\PlanningBoard;
use App\Models\Event;
use App\Models\PlanItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Every deliverable across the book.
 *
 * An event's own Plan Studio answers "what does this event's plan look like".
 * This answers the question no single event can: where the planning work is
 * this week, and what is waiting on somebody.
 */
class PlanningBoardTest extends TestCase
{
    use RefreshDatabase;

    private function actor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function item(Event $event, array $attrs = []): PlanItem
    {
        return PlanItem::create($attrs + [
            'event_id' => $event->id,
            'title' => 'Confirm the main hall',
            'status' => 'todo',
            'priority' => 'medium',
        ]);
    }

    private function board()
    {
        return Livewire::actingAs($this->actor())->test(PlanningBoard::class);
    }

    public function test_the_board_renders_and_spans_events(): void
    {
        $a = Event::factory()->create();
        $b = Event::factory()->create();
        $this->item($a);
        $this->item($b, ['title' => 'Sign the AV contract']);

        $this->actingAs($this->actor())->get(route('planning.index'))->assertOk()
            ->assertSee('Planning board');

        $items = $this->board()->viewData('items');

        $this->assertCount(2, $items);
        $this->assertSame(2, $items->pluck('event_id')->unique()->count());
    }

    public function test_the_lanes_are_the_models_own_gates(): void
    {
        $event = Event::factory()->create();
        $this->item($event, ['status' => 'todo']);
        $this->item($event, ['status' => 'needs_approval']);
        $this->item($event, ['status' => 'done']);

        $lanes = $this->board()->viewData('lanes');

        foreach ($lanes as $lane) {
            $this->assertArrayHasKey($lane['key'], PlanItem::STATUSES);
            foreach ($lane['items'] as $item) {
                $this->assertSame($lane['key'], $item->status);
            }
        }

        // Cancelled is drawn only when something is: an empty lane is a column
        // you have to read to dismiss.
        $this->assertNotContains('cancelled', $lanes->pluck('key'));

        $this->item($event, ['status' => 'cancelled']);
        $this->assertContains('cancelled', $this->board()->viewData('lanes')->pluck('key'));
    }

    /** Late first, then by date, then the undated — what is late is what you want. */
    public function test_a_lane_puts_the_late_work_first(): void
    {
        $event = Event::factory()->create();
        $this->item($event, ['title' => 'No date']);
        $this->item($event, ['title' => 'Next month', 'due_on' => now()->addMonth()]);
        $late = $this->item($event, ['title' => 'Late', 'due_on' => now()->subWeek()]);
        $this->item($event, ['title' => 'Next week', 'due_on' => now()->addWeek()]);

        $lane = $this->board()->viewData('lanes')->firstWhere('key', 'todo');

        $this->assertSame($late->id, $lane['items']->first()->id);
        $this->assertSame('No date', $lane['items']->last()->title, 'undated sorts last');
    }

    public function test_the_event_and_owner_filters_narrow_the_board(): void
    {
        $a = Event::factory()->create();
        $b = Event::factory()->create();
        $mine = $this->item($a);
        $this->item($b);

        $user = $this->actor();
        $mine->owners()->attach($user->id);

        $c = Livewire::actingAs($user)->test(PlanningBoard::class);

        $c->call('setEvent', $a->id);
        $this->assertTrue($c->viewData('items')->every(fn ($i) => $i->event_id === $a->id));

        $c->call('setEvent', 0)->call('setOwner', $user->id);
        $this->assertCount(1, $c->viewData('items'));

        // -1 is the unassigned, which is a filter people actually want.
        $c->call('setOwner', -1);
        $this->assertTrue($c->viewData('items')->every(fn ($i) => $i->owners->isEmpty()));
    }

    /** The two ways work goes missing: nobody on it, or nobody looking. */
    public function test_needs_attention_shows_the_late_and_the_unowned(): void
    {
        $event = Event::factory()->create();
        $user = $this->actor();

        $late = $this->item($event, ['title' => 'Late', 'due_on' => now()->subWeek()]);
        $late->owners()->attach($user->id);

        $this->item($event, ['title' => 'Nobody on it']);                       // unowned

        $fine = $this->item($event, ['title' => 'Fine', 'due_on' => now()->addMonth()]);
        $fine->owners()->attach($user->id);

        $c = Livewire::actingAs($user)->test(PlanningBoard::class)->call('toggleAttention');

        $titles = $c->viewData('items')->pluck('title');
        $this->assertContains('Late', $titles);
        $this->assertContains('Nobody on it', $titles);
        $this->assertNotContains('Fine', $titles);
    }

    public function test_moving_a_card_writes_the_gate(): void
    {
        $event = Event::factory()->create();
        $item = $this->item($event);

        $this->board()->call('moveTo', $item->id, 'in_progress');

        $this->assertSame('in_progress', $item->fresh()->status);
    }

    /**
     * Approval carries a signature. The board may set the gate, but it stamps
     * who and when rather than leaving an approved item nobody approved.
     */
    public function test_approving_from_the_board_records_who_and_when(): void
    {
        $event = Event::factory()->create();
        $item = $this->item($event, ['status' => 'needs_approval']);
        $user = $this->actor();

        Livewire::actingAs($user)->test(PlanningBoard::class)->call('moveTo', $item->id, 'approved');

        $item->refresh();
        $this->assertSame('approved', $item->status);
        $this->assertSame($user->id, $item->approved_by);
        $this->assertNotNull($item->approved_at);
    }

    public function test_an_unknown_gate_is_refused(): void
    {
        $event = Event::factory()->create();
        $item = $this->item($event);

        $this->board()->call('moveTo', $item->id, 'nonsense');

        $this->assertSame('todo', $item->fresh()->status);
    }

    public function test_only_writers_may_move_a_card(): void
    {
        $event = Event::factory()->create();
        $item = $this->item($event);
        $viewer = User::create(['name' => 'Vic Viewer', 'email' => 'viewer@ebh.test',
            'password' => bcrypt('x'), 'role' => 'viewer']);

        Livewire::actingAs($viewer)->test(PlanningBoard::class)
            ->call('moveTo', $item->id, 'done')->assertForbidden();

        $this->assertSame('todo', $item->fresh()->status);
    }

    public function test_archived_events_are_left_out(): void
    {
        $event = Event::factory()->create();
        $this->item($event);

        $this->assertCount(1, $this->board()->viewData('items'));

        $event->update(['archived_at' => now()]);
        $this->assertCount(0, $this->board()->viewData('items'));
    }

    /** A filter narrows what you are reading, not what is late. */
    public function test_the_figures_ignore_the_filter(): void
    {
        $a = Event::factory()->create();
        $b = Event::factory()->create();
        $this->item($a, ['due_on' => now()->subWeek()]);
        $this->item($b, ['due_on' => now()->subWeek()]);

        $c = $this->board();
        $before = collect($c->viewData('figures'))->firstWhere('label', 'Overdue')['value'];

        $c->call('setEvent', $a->id);
        $after = collect($c->viewData('figures'))->firstWhere('label', 'Overdue')['value'];

        $this->assertSame('2', $before);
        $this->assertSame($before, $after);
    }

    public function test_the_nav_links_to_it_now_that_it_exists(): void
    {
        $panel = collect(\App\Support\NavPanel::panel())
            ->flatMap(fn ($s) => $s['items'])
            ->firstWhere('label', 'Planning Board');

        $this->assertSame(route('planning.index'), $panel['href']);
    }
}
