<?php

namespace Tests\Feature;

use App\Livewire\Hub\BudgetTab;
use App\Models\Event;
use App\Models\EventAccommodation;
use App\Models\EventRoomBlock;
use App\Models\User;
use App\Services\BudgetSync;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Money committed in a module is money the budget knows about.
 *
 * A block of rooms was booked on an event and the budget stayed at zero: the
 * sync still read the rooming list, which is names, while the money had moved
 * to the block above it. Worse, it only ran when somebody opened the Budget
 * tab — so the hub, the dashboard and the portfolio's margin all quoted a
 * figure that was not merely stale but wrong, and nothing said so.
 */
class BudgetFollowsModulesTest extends TestCase
{
    use RefreshDatabase;

    private function event(): Event
    {
        return Event::factory()->create(['budget_cents' => 0, 'budget_status' => 'draft']);
    }

    private function block(Event $event, array $overrides = []): EventRoomBlock
    {
        return EventRoomBlock::create($overrides + [
            'event_id' => $event->id,
            'hotel' => 'Mövenpick Dead Sea',
            'room_type' => 'Standard',
            'occupancy' => 'single',
            'rooms_count' => 25,
            'rate_cents' => 170_00,
            'check_in' => '2026-09-05',
            'check_out' => '2026-09-11',
            'status' => 'confirmed',
        ]);
    }

    private function linked(Event $event)
    {
        return $event->fresh()->budgetItems()->where('source_type', 'room_block')->get();
    }

    /* ── the case that started this ── */

    public function test_booking_a_block_of_rooms_reaches_the_budget(): void
    {
        $event = $this->event();

        $this->block($event);        // 25 rooms × 6 nights × 170

        $line = $this->linked($event)->firstOrFail();

        $this->assertSame(25_500_00, $line->estimated_cents);
        $this->assertStringContainsString('Mövenpick Dead Sea', $line->description);
        $this->assertStringContainsString('25 rooms × 6 nights', $line->description,
            'the arithmetic is on the line — a figure nobody can take apart is a figure nobody trusts');
    }

    /** And without anybody opening the Budget tab first. */
    public function test_it_arrives_without_opening_the_budget(): void
    {
        $event = $this->event();
        $this->block($event);

        // Read straight off the record, the way the dashboard and the hub do.
        $this->assertSame(25_500_00, (int) $event->fresh()->budgetItems()->sum('estimated_cents'));
    }

    public function test_repricing_the_block_moves_the_budget_with_it(): void
    {
        $event = $this->event();
        $block = $this->block($event);

        $block->update(['rate_cents' => 200_00]);
        $this->assertSame(30_000_00, $this->linked($event)->first()->estimated_cents);

        $block->update(['rooms_count' => 30]);
        $this->assertSame(36_000_00, $this->linked($event)->first()->estimated_cents);
    }

    public function test_cancelling_or_deleting_a_block_takes_its_cost_away(): void
    {
        $event = $this->event();
        $block = $this->block($event);

        $block->update(['status' => 'cancelled']);
        $this->assertCount(0, $this->linked($event), 'a cancelled block is not a cost');

        $block->update(['status' => 'booked']);
        $this->assertCount(1, $this->linked($event));

        $block->delete();
        $this->assertCount(0, $this->linked($event));
    }

    /** Rooms held at a rate nobody has agreed are not free — they are unpriced. */
    public function test_a_block_with_no_rate_is_named_rather_than_counted(): void
    {
        $event = $this->event();
        $this->block($event, ['rate_cents' => 0, 'rooms_count' => 10, 'hotel' => 'Somewhere']);

        $this->assertCount(0, $this->linked($event));

        $pending = app(BudgetSync::class)->pending($event->fresh()->load(['roomBlocks', 'transport', 'rooms']));

        $this->assertCount(1, $pending);
        $this->assertSame('Stay', $pending[0]['module']);
        $this->assertStringContainsString('10 rooms at Somewhere', $pending[0]['what']);
        $this->assertStringContainsString('no rate yet', $pending[0]['what']);
    }

    /* ── the double count that would have been easy to ship ── */

    public function test_the_rooming_list_inside_a_block_is_not_counted_again(): void
    {
        $event = $this->event();
        $block = $this->block($event);

        // A named guest in the block, carrying a cost of its own from the days
        // before blocks existed.
        EventAccommodation::create([
            'event_id' => $event->id, 'block_id' => $block->id, 'hotel' => 'Mövenpick Dead Sea',
            'guest' => 'Dr Layla', 'rooms' => 1, 'cost_cents' => 1_020_00,
        ]);

        $this->assertSame(25_500_00, (int) $event->fresh()->budgetItems()->sum('estimated_cents'),
            'the block is the commitment; the names inside it are not a second one');
    }

    public function test_a_booking_made_outside_any_block_still_counts(): void
    {
        $event = $this->event();

        EventAccommodation::create([
            'event_id' => $event->id, 'hotel' => 'Four Seasons',
            'guest' => 'A late arrival', 'rooms' => 1, 'cost_cents' => 480_00,
        ]);

        $line = $event->fresh()->budgetItems()->where('source_type', 'accommodation')->firstOrFail();
        $this->assertSame(480_00, $line->estimated_cents);
    }

    /* ── what the desk owns stays the desk's ── */

    public function test_a_resync_keeps_what_the_desk_recorded_against_the_line(): void
    {
        $event = $this->event();
        $block = $this->block($event);

        $line = $this->linked($event)->firstOrFail();
        $line->update(['actual_cents' => 24_000_00, 'paid_cents' => 10_000_00,
            'payment_status' => 'partial', 'notes' => 'Deposit wired 3 Sep.']);

        $block->update(['rate_cents' => 180_00]);

        $line = $this->linked($event)->firstOrFail();

        $this->assertSame(27_000_00, $line->estimated_cents, 'the module owns the estimate');
        $this->assertSame(24_000_00, $line->actual_cents, '…and the desk owns everything else');
        $this->assertSame(10_000_00, $line->paid_cents);
        $this->assertSame('partial', $line->payment_status);
        $this->assertSame('Deposit wired 3 Sep.', $line->notes);
    }

    public function test_an_approved_baseline_is_never_moved_by_a_module(): void
    {
        $event = $this->event();
        $this->block($event);

        $event->update(['budget_status' => 'approved']);

        EventRoomBlock::where('event_id', $event->id)->first()->update(['rate_cents' => 900_00]);

        $this->assertSame(25_500_00, $this->linked($event)->first()->estimated_cents,
            'an approved budget is a promise, not a live figure');
    }

    /* ── and it says so on the screen ── */

    /** And the feed that exists to say what needs you finally mentions money. */
    public function test_going_over_the_cap_reaches_the_alert_feed(): void
    {
        $event = $this->event();
        $event->update(['budget_cents' => 20_000_00]);

        $this->block($event);          // 25,500 against a 20,000 cap

        $html = $this->actingAs(User::factory()->create(['role' => 'admin']))
            ->get(route('events.hub', $event))->assertOk()->getContent();

        $this->assertStringContainsString('over budget', $html);

        $forecast = $event->fresh()->costForecast();

        // Cost is what the rooms cost us; forecast is what the client is
        // charged for them — the cap is their money, so it is measured against
        // the charge. With the house 15% fee: 25,500 → 29,325.
        $this->assertSame(25_500_00, $forecast['cost']);
        $this->assertSame(29_325_00, $forecast['forecast']);
        $this->assertSame(9_325_00, $forecast['over']);
        $this->assertSame(147, $forecast['pct']);
    }

    public function test_the_budget_says_what_came_from_where(): void
    {
        $event = $this->event();
        $this->block($event);
        $this->block($event, ['rate_cents' => 0, 'rooms_count' => 8, 'hotel' => 'Unpriced Inn']);

        $c = Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(BudgetTab::class, ['event' => $event->fresh()]);

        $byModule = $c->viewData('linkedByModule');

        $this->assertSame(1, $byModule['room_block']['n']);
        $this->assertSame(25_500_00, $byModule['room_block']['cents']);
        $this->assertCount(1, $c->viewData('pendingFromModules'));
    }
}
