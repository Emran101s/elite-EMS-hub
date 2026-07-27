<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\EventBudgetItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
        return Event::factory()->create(['stage' => 'planning', 'management_fee_pct' => $feePct])->fresh();
    }

    private function line(Event $event, array $attrs = []): EventBudgetItem
    {
        return EventBudgetItem::create($attrs + [
            'event_id' => $event->id,
            'category' => 'production',
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
