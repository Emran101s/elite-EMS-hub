<?php

namespace Tests\Feature;

use App\Livewire\Hub\BudgetTab;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Three money rules that were wrong, each in a way no screen showed.
 *
 *   1. "not costed yet" and "cost nothing" shared one number, so a comped
 *      venue went on reporting its estimate as its cost forever.
 *   2. the Budget tab priced the client at a flat percentage of the subtotal
 *      while the Finance page priced line by line, so the two disagreed by
 *      tens of thousands on any event where somebody had quoted a line.
 *   3. the P&L subtracted the CHARGE from income, so an event billed
 *      correctly and paid in full reported a profit of exactly zero.
 */
class BudgetMoneyLogicTest extends TestCase
{
    use RefreshDatabase;

    private function event(float $feePct = 15.0): Event
    {
        $event = Event::factory()->create(['stage' => 'planning', 'management_fee_pct' => $feePct])->fresh();
        $event->ensureBudgetCategories();

        return $event;
    }

    private function line(Event $event, array $attrs = []): EventBudgetItem
    {
        return EventBudgetItem::create($attrs + [
            'event_id' => $event->id,
            'category' => $event->budgetCategories()->pluck('name')[0],
            'description' => 'Stage build',
            'estimated_cents' => 100_000,
            'actual_cents' => null,
            'paid_cents' => 0,
        ]);
    }

    private function screen(Event $event)
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'admin']))
            ->test(BudgetTab::class, ['event' => $event]);
    }

    /* ══ 1 · not costed yet is not the same as cost nothing ══ */

    public function test_an_uncosted_line_falls_back_to_its_estimate(): void
    {
        $line = $this->line($this->event());

        $this->assertFalse($line->hasActual());
        $this->assertSame(100_000, $line->costCents());
        $this->assertNull($line->varianceCents(), 'nothing has been saved or overspent yet');
    }

    public function test_a_line_costed_at_nothing_costs_nothing(): void
    {
        $line = $this->line($this->event(), ['actual_cents' => 0]);

        $this->assertTrue($line->hasActual());
        $this->assertSame(0, $line->costCents(), 'a sponsor comped it; it costs nothing');
        $this->assertSame(100_000, $line->varianceCents(), 'and the whole estimate is a saving');
        $this->assertSame(0, $line->outstandingCents());
        $this->assertSame('paid', $line->derivePaymentStatus(), 'there is nothing left to chase');
    }

    public function test_the_saving_from_a_comped_line_reaches_the_totals(): void
    {
        $event = $this->event();
        $this->line($event, ['actual_cents' => 0]);           // comped in full
        $this->line($event, ['actual_cents' => 90_000]);      // 10k under

        $data = $this->screen($event);

        $this->assertSame(110_000, $data->viewData('savedTotal'),
            'the comped line contributes its whole estimate');
        $this->assertSame(90_000, $data->viewData('forecastTotal'));
        $this->assertTrue($data->viewData('hasActuals'));
    }

    /** Blank means not costed; a typed 0 means costed, at nothing. */
    public function test_the_editor_can_say_both_and_round_trips_them(): void
    {
        $event = $this->event();
        $line = $this->line($event, ['actual_cents' => 50_000]);

        // Typing 0 records a real zero.
        $this->screen($event)->call('editLine', $line->id)->set('actual', '0')->call('save');
        $line->refresh();
        $this->assertSame(0, $line->actual_cents);
        $this->assertTrue($line->hasActual());

        // It comes back into the form as "0", not as blank.
        $this->screen($event)->call('editLine', $line->id)->assertSet('actual', '0');

        // Clearing the field says "not costed after all".
        $this->screen($event)->call('editLine', $line->id)->set('actual', '')->call('save');
        $line->refresh();
        $this->assertNull($line->actual_cents);
        $this->assertSame(100_000, $line->costCents());
    }

    /* ══ 2 · one definition of what the client is charged ══ */

    public function test_the_tab_and_the_portfolio_agree_however_a_line_is_priced(): void
    {
        $event = $this->event(feePct: 15);
        $a = $this->line($event);
        $b = $this->line($event, ['estimated_cents' => 200_000]);
        $c = $this->line($event, ['estimated_cents' => 50_000]);

        $agree = function () use ($event) {
            $event->refresh()->load('budgetItems');
            $tab = $this->screen($event)->viewData('grandForecast');

            $this->assertSame($event->sellSummary()['sell'], $tab,
                'the tab and the Finance page price the same event the same way');
        };

        $agree();                                             // all on the default fee
        $b->update(['billable' => false]);       $agree();    // one absorbed
        $b->update(['billable' => true, 'sell_cents' => 999_00]); $agree();   // one quoted
        $c->update(['markup_pct' => 40]);        $agree();    // one at its own markup
        $a->update(['actual_cents' => 0]);       $agree();    // one comped
    }

    /**
     * The safety rule that makes all of this deployable over live budgets:
     * with nothing priced by hand, the totals are what they always were.
     */
    public function test_an_unpriced_budget_still_totals_subtotal_plus_fee(): void
    {
        $event = $this->event(feePct: 15);
        $this->line($event, ['estimated_cents' => 300_000]);
        $this->line($event, ['estimated_cents' => 450_000]);

        $data = $this->screen($event);
        $subtotal = 750_000;

        $this->assertSame($subtotal, $data->viewData('estimatedTotal'));
        $this->assertSame((int) round($subtotal * 0.15), $data->viewData('feeEst'));
        $this->assertSame($subtotal + (int) round($subtotal * 0.15), $data->viewData('grandEst'));
    }

    public function test_a_non_billable_line_leaves_the_charge_but_not_the_cost(): void
    {
        $event = $this->event(feePct: 15);
        $this->line($event, ['estimated_cents' => 100_000]);
        $this->line($event, ['estimated_cents' => 100_000, 'billable' => false]);

        $data = $this->screen($event);

        $this->assertSame(200_000, $data->viewData('estimatedTotal'), 'both cost');
        $this->assertSame(115_000, $data->viewData('grandEst'), 'only one is charged for');
        $this->assertSame(-85_000, $data->viewData('feeEst'),
            'the absorbed line shows as what it is: money the fee does not cover');
    }

    /* ══ 3 · a P&L subtracts cost from income, not the charge ══ */

    public function test_profit_is_income_less_what_it_costs_to_deliver(): void
    {
        $event = $this->event(feePct: 15);
        $this->line($event, ['estimated_cents' => 100_000, 'actual_cents' => 100_000]);

        // The client is billed 115,000 and pays it.
        $event->incomeItems()->create([
            'source' => 'client', 'description' => 'Fee', 'amount_cents' => 115_000,
        ]);

        $data = $this->screen($event->fresh());

        $this->assertSame(100_000, $data->viewData('costToDeliver'));
        $this->assertSame(15_000, $data->viewData('netResult'),
            'the fee is revenue you keep, not money you spend');
    }
}
