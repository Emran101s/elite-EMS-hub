<?php

namespace Tests\Feature;

use App\Livewire\TransportLive;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\TransportDriver;
use App\Models\User;
use App\Models\VehicleType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Event-day operations. The tests assert the two things that decide whether this
 * screen survives contact with a real transfer morning: that the board ranks
 * what matters to the top, and that every action is a single call.
 */
class TransportLiveTest extends TestCase
{
    use RefreshDatabase;

    private Event $event;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Fixed "now" so Now / Next / Later is deterministic.
        Carbon::setTestNow('2026-07-27 09:00:00');

        $this->user = User::factory()->create(['role' => 'super_admin']);
        $this->event = Event::create([
            'name' => 'Live Summit', 'type' => 'summit', 'city' => 'Amman', 'country' => 'Jordan',
            'starts_at' => '2026-07-27', 'ends_at' => '2026-07-29',
        ]);

        VehicleType::create(['name' => 'Van', 'capacity' => 7, 'is_active' => true, 'position' => 1]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function movement(string $departAt, string $status = 'confirmed', array $extra = []): EventTransport
    {
        return $this->event->transport()->create([
            'type' => 'van', 'leg' => 'arrival', 'vehicles' => 1,
            'route' => 'QAIA → Hotel', 'pickup_from' => 'Queen Alia Airport', 'drop_to' => 'Fairmont',
            'status' => $status, 'depart_at' => $departAt,
        ] + $extra);
    }

    private function live()
    {
        return Livewire::actingAs($this->user)->test(TransportLive::class, ['event' => $this->event]);
    }

    // ── the board ───────────────────────────────────────────────

    public function test_the_board_ranks_by_what_needs_looking_at_first(): void
    {
        $running = $this->movement('2026-07-27 08:30', 'in_progress');
        $soon = $this->movement('2026-07-27 10:00');            // within 2h
        $later = $this->movement('2026-07-27 18:00');           // beyond the window
        $done = $this->movement('2026-07-27 07:00', 'completed');
        $broken = $this->movement('2026-07-27 20:00', 'issue', ['issue_note' => 'Van broke down']);

        $board = $this->live()->instance()->board();

        $this->assertSame([$running->id], $board['now']->pluck('id')->all());
        $this->assertSame([$soon->id], $board['next']->pluck('id')->all());
        $this->assertSame([$later->id], $board['later']->pluck('id')->all());
        $this->assertSame([$done->id], $board['done']->pluck('id')->all());

        // An issue jumps the queue regardless of when it was due.
        $this->assertSame([$broken->id], $board['issues']->pluck('id')->all());
        $this->assertFalse($board['later']->contains('id', $broken->id), 'an issue appears once, at the top');
    }

    public function test_a_delay_re_ranks_the_run_by_its_new_time(): void
    {
        $run = $this->movement('2026-07-27 10:00');   // "next" at 09:00

        $c = $this->live();
        $this->assertSame([$run->id], $c->instance()->board()['next']->pluck('id')->all());

        // Pushed six hours out — it is no longer imminent.
        $c->call('delay', $run->id, 360);

        $board = $c->instance()->board();
        $this->assertTrue($board['next']->isEmpty());
        $this->assertSame([$run->id], $board['later']->pluck('id')->all());
        $this->assertSame('16:00', $run->fresh()->effectiveDeparture()->format('H:i'));
        $this->assertSame('10:00', $run->fresh()->depart_at->format('H:i'), 'the plan is kept');
    }

    public function test_only_the_chosen_day_is_shown(): void
    {
        $today = $this->movement('2026-07-27 10:00');
        $tomorrow = $this->movement('2026-07-28 10:00');

        $c = $this->live();
        $this->assertSame('2026-07-27', $c->instance()->day, 'opens on today');
        $this->assertSame([$today->id], collect($c->instance()->board())->flatten()->pluck('id')->all());

        $c->call('setDay', '2026-07-28');
        $this->assertSame([$tomorrow->id], collect($c->instance()->board())->flatten()->pluck('id')->all());
    }

    // ── the two taps ────────────────────────────────────────────

    public function test_start_and_arrive_stamp_the_times_they_happened(): void
    {
        $run = $this->movement('2026-07-27 10:00');
        $c = $this->live();

        $c->call('start', $run->id);
        $run->refresh();
        $this->assertSame('in_progress', $run->status);
        $this->assertNotNull($run->started_at);
        $this->assertNull($run->completed_at);

        Carbon::setTestNow('2026-07-27 10:45:00');
        $c->call('arrive', $run->id);
        $run->refresh();
        $this->assertSame('completed', $run->status);
        $this->assertSame('10:45', $run->completed_at->format('H:i'));
        $this->assertTrue($run->isSettled());
    }

    public function test_a_mis_tap_can_be_undone(): void
    {
        $run = $this->movement('2026-07-27 10:00');
        $c = $this->live();

        $c->call('arrive', $run->id);
        $this->assertSame('completed', $run->fresh()->status);

        // Phones invite mis-taps; the way back has to be one tap too.
        $c->call('undo', $run->id);
        $run->refresh();
        $this->assertSame('confirmed', $run->status);
        $this->assertNull($run->started_at);
        $this->assertNull($run->completed_at);
    }

    public function test_an_issue_always_carries_a_note(): void
    {
        $run = $this->movement('2026-07-27 10:00');
        $c = $this->live();

        // "Something is wrong" on its own helps nobody who picks this up.
        $c->call('openIssue', $run->id)
            ->set('issueNote', '   ')
            ->call('flagIssue', $run->id)
            ->assertHasErrors('issueNote');

        $this->assertSame('confirmed', $run->fresh()->status, 'nothing changed');

        $c->set('issueNote', 'Van broke down on the airport road')->call('flagIssue', $run->id);
        $run->refresh();
        $this->assertSame('issue', $run->status);
        $this->assertSame('Van broke down on the airport road', $run->issue_note);

        $c->call('resolveIssue', $run->id);
        $run->refresh();
        $this->assertSame('confirmed', $run->status);
        $this->assertNull($run->issue_note);
    }

    public function test_starting_a_run_clears_a_stale_issue(): void
    {
        $run = $this->movement('2026-07-27 10:00', 'issue', ['issue_note' => 'Driver stuck in traffic']);

        $this->live()->call('start', $run->id);

        $run->refresh();
        $this->assertSame('in_progress', $run->status);
        $this->assertNull($run->issue_note, 'it is moving now — the old note would mislead');
    }

    public function test_a_guest_can_be_marked_a_no_show_and_un_marked(): void
    {
        $run = $this->movement('2026-07-27 10:00');
        $guest = $run->manifest()->create([
            'event_id' => $this->event->id, 'name' => 'Layla Odeh', 'direction' => 'arrival', 'position' => 1,
        ]);

        $c = $this->live();

        $c->call('toggleNoShow', $guest->id);
        $this->assertTrue($guest->fresh()->isNoShow());

        $c->call('toggleNoShow', $guest->id);
        $this->assertFalse($guest->fresh()->isNoShow(), 'they turned up after all');
    }

    // ── the view ────────────────────────────────────────────────

    public function test_the_live_page_renders_with_the_counts_that_need_action(): void
    {
        $this->movement('2026-07-27 10:00');                                       // no driver
        $driver = TransportDriver::create(['name' => 'Khaled Mansour', 'phone' => '+962 79 555 0111']);
        $staffed = $this->movement('2026-07-27 11:00', 'confirmed', ['driver_id' => $driver->id]);
        $staffed->manifest()->create(['event_id' => $this->event->id, 'name' => 'Layla Odeh',
            'direction' => 'arrival', 'no_show_at' => now(), 'position' => 1]);

        $this->actingAs($this->user)
            ->get(route('events.transport.live', $this->event))
            ->assertOk()
            ->assertSee('Transport · Live')
            ->assertSee('1 run with no driver')
            ->assertSee('1 no-show')
            ->assertSee('Khaled Mansour');
    }

    public function test_the_live_page_requires_a_signed_in_user(): void
    {
        $this->get(route('events.transport.live', $this->event))->assertRedirect(route('login'));
    }

    public function test_a_read_only_user_cannot_change_the_board(): void
    {
        $viewer = User::factory()->create(['role' => 'viewer']);
        $run = $this->movement('2026-07-27 10:00');

        Livewire::actingAs($viewer)->test(TransportLive::class, ['event' => $this->event])
            ->call('start', $run->id)
            ->assertForbidden();

        $this->assertSame('confirmed', $run->fresh()->status);
    }
}
