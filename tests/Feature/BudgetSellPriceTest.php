<?php

namespace Tests\Feature;

use App\Livewire\Hub\BudgetTab;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Cost and charge.
 *
 * The budget only ever knew what things cost. What the client pays lived
 * somewhere else — a contract value, a flat fee on the subtotal — so the one
 * question an events business asks about a line ("this cost me X, what am I
 * invoicing for it?") had no answer.
 *
 * The rule that makes this safe to add over live budgets is the last test:
 * with nothing priced by hand, the totals are what they always were.
 */
class BudgetSellPriceTest extends TestCase
{
    use RefreshDatabase;

    private function event(float $feePct = 15.0): Event
    {
        $event = Event::factory()->create(['stage' => 'planning', 'management_fee_pct' => $feePct])->fresh();

        // A line has to land in one of the event's own categories, and the
        // screen validates that, so seed them the way opening the tab would.
        $event->ensureBudgetCategories();

        return $event;
    }

    /** The event's first category — where test lines go unless told otherwise. */
    private function cat(Event $event, int $index = 0): string
    {
        return $event->budgetCategories()->pluck('name')[$index];
    }

    private function line(Event $event, array $attrs = []): EventBudgetItem
    {
        return EventBudgetItem::create($attrs + [
            'event_id' => $event->id,
            'category' => $this->cat($event),
            'description' => 'Stage build',
            'estimated_cents' => 100_000,
            'actual_cents' => 0,
            'paid_cents' => 0,
        ]);
    }

    public function test_cost_is_the_actual_once_known_and_the_estimate_until_then(): void
    {
        $event = $this->event();

        $this->assertSame(100_000, $this->line($event)->costCents());
        $this->assertSame(120_000, $this->line($event, ['actual_cents' => 120_000])->costCents());
    }

    public function test_a_line_with_nothing_said_about_it_is_charged_at_the_management_fee(): void
    {
        $event = $this->event(feePct: 15);
        $line = $this->line($event);

        $this->assertSame(115_000, $line->sellCents(15.0));
        $this->assertSame(15_000, $line->marginCents(15.0));
        $this->assertSame(13, $line->marginPct(15.0), '15,000 of a 115,000 charge is 13%');
    }

    public function test_a_markup_beats_the_fee_and_a_typed_price_beats_the_markup(): void
    {
        $event = $this->event(feePct: 15);

        $marked = $this->line($event, ['markup_pct' => 40]);
        $this->assertSame(140_000, $marked->sellCents(15.0), 'cost plus 40%');

        // "We quoted 200,000 for this, whatever it costs us."
        $quoted = $this->line($event, ['markup_pct' => 40, 'sell_cents' => 200_000]);
        $this->assertSame(200_000, $quoted->sellCents(15.0));
        $this->assertSame(100_000, $quoted->marginCents(15.0));
    }

    public function test_a_line_sold_below_cost_reports_a_negative_margin_rather_than_hiding_it(): void
    {
        $event = $this->event();
        $line = $this->line($event, ['sell_cents' => 80_000]);

        $this->assertSame(-20_000, $line->marginCents());
        $this->assertSame(-25, $line->marginPct(), 'losing 20,000 on an 80,000 charge');
    }

    public function test_a_line_you_absorb_charges_nothing_but_still_costs(): void
    {
        $event = $this->event();
        $line = $this->line($event, ['billable' => false, 'markup_pct' => 50]);

        $this->assertSame(100_000, $line->costCents(), 'it still comes out of the business');
        $this->assertSame(0, $line->sellCents(15.0));
        $this->assertSame(-100_000, $line->marginCents(15.0));
        $this->assertNull($line->marginPct(15.0), 'no charge means no margin to speak of, not 0%');
    }

    public function test_the_event_total_is_what_the_work_is_priced_at(): void
    {
        $event = $this->event(feePct: 10);

        $this->line($event, ['estimated_cents' => 200_000]);                        // fee → 220,000
        $this->line($event, ['estimated_cents' => 100_000, 'markup_pct' => 50]);     // → 150,000
        $this->line($event, ['estimated_cents' => 50_000, 'sell_cents' => 90_000]);  // → 90,000
        $this->line($event, ['estimated_cents' => 30_000, 'billable' => false]);     // → 0

        $s = $event->fresh()->sellSummary();

        $this->assertSame(380_000, $s['cost']);
        $this->assertSame(460_000, $s['sell']);
        $this->assertSame(80_000, $s['margin']);
        $this->assertSame(17, $s['marginPct']);
        $this->assertSame(2, $s['priced'], 'two lines were priced deliberately');
        $this->assertSame(30_000, $s['absorbed']);
        $this->assertSame(4, $s['lines']);
    }

    // ══════════════════════════════════════════════════════════════════════
    //  The screen
    // ══════════════════════════════════════════════════════════════════════

    private function screen(Event $event): Testable
    {
        return Livewire::actingAs(User::factory()->create(['role' => 'super_admin']))
            ->test(BudgetTab::class, ['event' => $event]);
    }

    public function test_a_price_typed_on_the_form_reaches_the_line(): void
    {
        $event = $this->event();
        $line = $this->line($event);

        $this->screen($event)
            ->call('editLine', $line->id)
            ->set('sell', '175')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(17_500, $line->fresh()->sell_cents);
    }

    public function test_clearing_the_price_sends_the_line_back_to_the_management_fee(): void
    {
        $event = $this->event(feePct: 15);
        $line = $this->line($event, ['sell_cents' => 500_000]);

        $this->screen($event)
            ->call('editLine', $line->id)
            ->set('sell', '')
            ->call('save');

        $line->refresh();
        $this->assertNull($line->sell_cents, 'blank means "no price of its own"');
        $this->assertSame(115_000, $line->sellCents(15.0));
    }

    /**
     * Pricing a category at once is the common case. A line already quoted by
     * hand is left alone — that price was a decision too, and a bulk action
     * should not quietly overwrite it.
     */
    public function test_pricing_a_category_leaves_lines_that_were_quoted_by_hand_alone(): void
    {
        $event = $this->event(feePct: 15);

        $plain = $this->line($event);
        $quoted = $this->line($event, ['sell_cents' => 300_000]);
        $elsewhere = $this->line($event, ['category' => $this->cat($event, 1)]);

        $this->screen($event)->call('markupCategory', $this->cat($event), 30);

        $this->assertSame(30.0, $plain->fresh()->markup_pct);
        $this->assertNull($quoted->fresh()->markup_pct, 'a quoted line keeps its quote');
        $this->assertSame(300_000, $quoted->fresh()->sellCents(15.0));
        $this->assertNull($elsewhere->fresh()->markup_pct, 'another category is untouched');
    }

    public function test_a_category_can_be_put_back_on_the_management_fee(): void
    {
        $event = $this->event(feePct: 15);
        $marked = $this->line($event, ['markup_pct' => 40]);
        $quoted = $this->line($event, ['sell_cents' => 300_000]);

        $this->screen($event)->call('clearCategoryPricing', $this->cat($event));

        $this->assertNull($marked->fresh()->markup_pct);
        $this->assertNull($quoted->fresh()->sell_cents, 'clearing means clearing');
        $this->assertSame(115_000, $marked->fresh()->sellCents(15.0));
    }

    /**
     * A line can arrive with an estimate and no unit cost — an import, a seed,
     * a module sync. The form shows a blank unit for those, so deriving the
     * estimate from it would zero the number the moment someone edited the
     * vendor and saved.
     */
    public function test_editing_a_line_that_has_no_unit_cost_does_not_wipe_its_estimate(): void
    {
        $event = $this->event();
        $line = $this->line($event, ['estimated_cents' => 250_000, 'unit_cents' => null]);

        $this->screen($event)
            ->call('editLine', $line->id)
            ->set('vendor', 'Prime AV')
            ->call('save')
            ->assertHasNoErrors();

        $line->refresh();
        $this->assertSame(250_000, $line->estimated_cents, 'the estimate survived an unrelated edit');
        $this->assertSame('Prime AV', $line->vendor);
    }

    public function test_a_line_can_be_taken_off_the_invoice_without_taking_it_out_of_the_budget(): void
    {
        $event = $this->event();
        $line = $this->line($event);

        $this->screen($event)->call('toggleBillable', $line->id);

        $line->refresh();
        $this->assertFalse($line->billable);
        $this->assertSame(100_000, $line->costCents(), 'still in the budget');
        $this->assertSame(0, $line->sellCents(15.0), 'not on the invoice');
    }

    public function test_the_price_view_shows_what_the_event_is_charged_at(): void
    {
        $event = $this->event(feePct: 20);
        $this->line($event, ['estimated_cents' => 500_000]);

        $this->screen($event)
            ->set('view', 'price')
            ->assertSee('Cost to us')
            ->assertSee('Charged to client')
            ->assertSee('Gross margin')
            ->assertSee('Margin %');
    }

    /**
     * The one that makes this safe to introduce over budgets people already
     * rely on: unpriced, the new total is the old subtotal-plus-fee.
     *
     * Summing round(cost × 1.15) per line can differ from round(subtotal × 1.15)
     * by up to half a cent a line, so the tolerance is the line count — not a
     * fudge, just the arithmetic of rounding in two places.
     */
    public function test_an_unpriced_budget_still_totals_exactly_what_it_used_to(): void
    {
        $event = $this->event(feePct: 15);

        foreach ([123_457, 88_889, 45_001, 7, 999_999] as $cents) {
            $this->line($event, ['estimated_cents' => $cents]);
        }

        $event = $event->fresh();
        $summary = $event->sellSummary();

        $subtotal = (int) $event->budgetItems->sum('estimated_cents');
        $oldTotal = $subtotal + (int) round($subtotal * 15 / 100);

        $this->assertSame($subtotal, $summary['cost']);
        $this->assertEqualsWithDelta($oldTotal, $summary['sell'], $event->budgetItems->count());
        $this->assertSame(0, $summary['priced'], 'nothing was priced by hand');
    }
}
