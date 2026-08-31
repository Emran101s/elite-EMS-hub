<?php

namespace Tests\Feature;

use App\Livewire\FinanceOverview;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\EventContract;
use App\Models\EventContractPayment;
use App\Models\EventIncomeItem;
use App\Models\Invoice;
use App\Models\User;
use App\Services\CurrencyService;
use App\Services\PortfolioFinance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Money across the book.
 *
 * The point of these is the last one: the portfolio must never disagree with
 * the event you click into. Everything else here guards a rule that would
 * otherwise be silently wrong on a screen people make decisions from.
 */
class PortfolioFinanceTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Income here is BOOKED income, not a target: incomeSummary()['total'] sums
     * what has actually been contracted and sold. client_target_cents is the
     * ambition and deliberately does not count — a P&L reports what you have,
     * not what you hoped for.
     */
    private function eventWithCost(int $estimated, ?int $actual = null, int $paid = 0, int $income = 0): Event
    {
        $event = Event::factory()->create(['stage' => 'planning']);

        if ($income > 0) {
            EventIncomeItem::create([
                'event_id' => $event->id,
                'source' => 'client',
                'description' => 'Management fee',
                'amount_cents' => $income,
            ]);
        }

        EventBudgetItem::create([
            'event_id' => $event->id,
            'category' => 'production',
            'description' => 'Stage build',
            'estimated_cents' => $estimated,
            'actual_cents' => $actual,
            'paid_cents' => $paid,
        ]);

        return $event->fresh();
    }

    public function test_cost_is_the_actual_figure_where_one_exists_and_the_estimate_until_then(): void
    {
        $estimateOnly = $this->eventWithCost(estimated: 500_000);                  // no actual yet
        $withActual = $this->eventWithCost(estimated: 500_000, actual: 620_000);   // actual known

        // statement() sorts by start date, so find the rows rather than index them.
        $rows = app(PortfolioFinance::class)->statement()->keyBy(fn ($r) => $r['event']->id);

        $this->assertSame(500_000, $rows[$estimateOnly->id]['cost'], 'with no actual, the estimate is the commitment');
        $this->assertSame(620_000, $rows[$withActual->id]['cost'], 'once an actual exists it replaces the estimate');
        $this->assertSame(1_120_000, app(PortfolioFinance::class)->totals()['cost']);
    }

    public function test_an_overrun_is_reported_against_the_estimate(): void
    {
        $this->eventWithCost(estimated: 500_000, actual: 620_000);

        $row = app(PortfolioFinance::class)->statement()->first();

        $this->assertSame(24, $row['overrun'], '620k against a 500k estimate is 24% over');
    }

    public function test_margin_is_measured_against_income_not_budget(): void
    {
        $this->eventWithCost(estimated: 250_000, income: 1_000_000);

        $row = app(PortfolioFinance::class)->statement()->first();

        $this->assertSame(1_000_000, $row['income']);
        $this->assertSame(750_000, $row['net']);
        $this->assertSame(75, $row['margin']);
    }

    public function test_income_with_no_value_leaves_margin_unmeasurable_rather_than_zero(): void
    {
        $this->eventWithCost(estimated: 100_000);   // cost, but nothing sold

        $row = app(PortfolioFinance::class)->statement()->first();

        $this->assertNull($row['margin'], 'a margin on no income is not 0%, it is unknown');
    }

    public function test_archived_events_are_left_out_of_the_book(): void
    {
        $live = $this->eventWithCost(estimated: 300_000);
        $gone = $this->eventWithCost(estimated: 900_000);
        $gone->update(['archived_at' => now()]);

        $totals = app(PortfolioFinance::class)->totals();

        $this->assertSame(1, $totals['events']);
        $this->assertSame(300_000, $totals['cost']);
        $this->assertSame($live->id, app(PortfolioFinance::class)->statement()->first()['event']->id);
    }

    public function test_payables_are_the_lines_with_something_still_outstanding(): void
    {
        $this->eventWithCost(estimated: 400_000, actual: 400_000, paid: 400_000);   // settled
        $this->eventWithCost(estimated: 300_000, actual: 300_000, paid: 100_000);   // 200k left
        $this->eventWithCost(estimated: 150_000);                                   // nothing paid

        $payables = app(PortfolioFinance::class)->payables();

        $this->assertSame(2, $payables->count(), 'a fully paid line is not a payable');
        $this->assertSame(350_000, (int) $payables->sum(fn ($i) => $i->outstandingCents()));
        $this->assertSame(350_000, app(PortfolioFinance::class)->totals()['payable']);
    }

    public function test_cost_is_grouped_by_category(): void
    {
        $event = $this->eventWithCost(estimated: 200_000);
        EventBudgetItem::create([
            'event_id' => $event->id, 'category' => 'catering',
            'description' => 'Lunch', 'estimated_cents' => 90_000, 'actual_cents' => null, 'paid_cents' => 0,
        ]);

        $categories = app(PortfolioFinance::class)->costByCategory();

        $this->assertSame(2, $categories->count());
        $this->assertSame(200_000, $categories->firstWhere('category', 'production')['cost']);
        $this->assertSame(90_000, $categories->firstWhere('category', 'catering')['cost']);
    }

    public function test_the_screen_renders_and_sorts(): void
    {
        $user = User::factory()->create();
        $this->eventWithCost(estimated: 100_000, income: 200_000)->update(['name' => 'Small job']);
        $this->eventWithCost(estimated: 100_000, income: 5_000_000)->update(['name' => 'Big job']);

        $screen = Livewire::actingAs($user)->test(FinanceOverview::class)
            ->assertSee('Profit & loss')
            ->assertSee('Portfolio')
            ->assertSee('Big job');

        $this->assertSame('Big job', $screen->viewData('rows')[0]['event']->name, 'sorted by net');

        $screen->call('sortBy', 'cost');
        $this->assertSame('cost', $screen->get('sort'));
    }

    /**
     * The portfolio used to add JD to $ as though they were the same number.
     * Every figure is now converted into the company's own currency before it
     * meets another one.
     */
    public function test_events_in_another_currency_are_converted_before_they_are_added_up(): void
    {
        CompanyProfile::current()->update(['default_currency' => 'USD']);

        $home = $this->eventWithCost(estimated: 100_000);
        $home->update(['currency' => 'USD']);

        $abroad = $this->eventWithCost(estimated: 100_000);
        $abroad->update(['currency' => 'JOD']);

        $finance = app(PortfolioFinance::class);
        $rate = app(CurrencyService::class)->rate('JOD', 'USD');

        $this->assertSame('USD', $finance->baseCurrency());
        $this->assertTrue($finance->isMixed(), 'two currencies are in play');

        $rows = $finance->statement()->keyBy(fn ($r) => $r['event']->id);

        $this->assertSame(100_000, $rows[$home->id]['cost'], 'already in the base currency');
        $this->assertSame((int) round(100_000 * $rate), $rows[$abroad->id]['cost']);
        $this->assertNotSame(100_000, $rows[$abroad->id]['cost'], 'a dinar is not a dollar');

        $totals = $finance->totals();
        $this->assertSame('USD', $totals['currency']);
        $this->assertTrue($totals['mixed']);
        $this->assertSame(100_000 + (int) round(100_000 * $rate), $totals['cost']);
    }

    public function test_one_currency_everywhere_is_not_reported_as_mixed(): void
    {
        CompanyProfile::current()->update(['default_currency' => 'JOD']);
        $this->eventWithCost(estimated: 100_000)->update(['currency' => 'JOD']);

        $finance = app(PortfolioFinance::class);

        $this->assertFalse($finance->isMixed());
        $this->assertSame(100_000, $finance->totals()['cost'], 'no conversion to do');
    }

    /**
     * Billing and collecting are different questions.
     *
     * "Billed" used to be read off client income, so a book with real sent
     * invoices on it and nothing yet paid announced that it had billed
     * nothing — beside an "Owed to you" figure of 350,000, which cannot both
     * be true. Anyone acting on that would raise the invoices a second time.
     */
    public function test_an_invoice_that_is_sent_counts_as_billed_even_with_nothing_collected(): void
    {
        $event = $this->eventWithCost(estimated: 100_000);
        $event->update(['management_fee_pct' => 0]);

        $invoice = Invoice::create([
            'event_id' => $event->id,
            'number' => 'TEST-001',
            'status' => 'sent',
            'currency' => $event->currency,
            'issued_on' => now(),
            'paid_cents' => 0,
        ]);
        $invoice->lines()->create(['description' => 'Stage build', 'qty' => 1, 'unit_cents' => 60_000]);

        // A draft is the office deciding not to bill yet. It must not count.
        $draft = Invoice::create([
            'event_id' => $event->id,
            'number' => 'TEST-002',
            'status' => 'draft',
            'currency' => $event->currency,
            'issued_on' => now(),
            'paid_cents' => 0,
        ]);
        $draft->lines()->create(['description' => 'Catering', 'qty' => 1, 'unit_cents' => 25_000]);

        $row = app(PortfolioFinance::class)->statement()->first();

        $this->assertSame(60_000, $row['billed'], 'the sent invoice is billed; the draft is not');
        $this->assertSame(0, $row['collected'], 'and none of it has been collected');
        $this->assertSame(40_000, $row['notInvoiced'], '100,000 priced, 60,000 on a document');
    }

    /**
     * Charged and billed are different questions, and the gap between them is
     * an invoice somebody has not sent.
     */
    public function test_the_portfolio_reports_what_is_priced_as_well_as_what_is_billed(): void
    {
        $event = $this->eventWithCost(estimated: 100_000, income: 50_000);
        $event->update(['management_fee_pct' => 20]);

        $row = app(PortfolioFinance::class)->statement()->first();

        $this->assertSame(120_000, $row['charged'], 'cost plus the 20% fee');
        $this->assertSame(50_000, $row['clientIncome'], 'only part of it has reached an invoice');
        $this->assertSame(70_000, $row['unbilled']);

        $this->assertSame(17, $row['pricedMargin'], '20,000 of a 120,000 charge');
        $this->assertSame(-100, $row['margin'], 'against what was actually billed, it is under water');
    }

    /**
     * Sponsorship and exhibition money never came from a priced cost line, so
     * measuring what the budget is charged at against total income would have
     * reported over-billing by 300% on an event that sells sponsorships.
     */
    public function test_sponsorship_income_does_not_count_as_billing_the_priced_work(): void
    {
        $event = $this->eventWithCost(estimated: 100_000, income: 40_000);
        $event->update(['management_fee_pct' => 0]);
        $event->sponsors()->create(['name' => 'Headline Sponsor', 'package' => 'Platinum Partner', 'amount_cents' => 900_000, 'paid_cents' => 0]);

        $row = app(PortfolioFinance::class)->statement()->first();

        $this->assertSame(100_000, $row['charged']);
        $this->assertSame(40_000, $row['clientIncome'], 'the client has been billed 40,000');
        $this->assertSame(60_000, $row['unbilled'], 'the sponsor money is not billing for the work');
        $this->assertSame(940_000, $row['income'], 'but it is still income, and it still counts towards net');
    }

    /**
     * A signed contract you have not been paid on is money at risk — the whole
     * point of the "Revenue at Risk" figure. The client is booked at what has
     * been collected, so the contract's outstanding instalments have to be added
     * explicitly or a fully-unpaid deal reports nothing owed while the ledger
     * holds real instalments — exactly what made the KPI read zero next to a
     * receivables panel listing the money.
     */
    public function test_a_signed_but_unpaid_contract_is_money_at_risk(): void
    {
        $event = Event::factory()->create(['stage' => 'planning']);
        $contract = EventContract::forEvent($event);
        EventContractPayment::create([
            'event_id' => $event->id, 'contract_id' => $contract->id,
            'label' => 'On signature', 'pct' => 100, 'amount_cents' => 350_000_00, 'sort' => 1,
        ]);

        $row = app(PortfolioFinance::class)->statement()->first();
        $this->assertSame(0, $row['collected'], 'nothing paid yet');
        $this->assertSame(350_000_00, $row['receivable'], 'the whole contract is owed, not zero');
        $this->assertSame(350_000_00, app(PortfolioFinance::class)->totals()['receivable']);

        // Pay part of it — only the shortfall is still at risk.
        EventContractPayment::where('event_id', $event->id)->first()->update(['paid_cents' => 150_000_00]);

        $row = app(PortfolioFinance::class)->statement()->first();
        $this->assertSame(150_000_00, $row['collected']);
        $this->assertSame(200_000_00, $row['receivable'], 'only the unpaid remainder is at risk');
    }

    public function test_the_portfolio_never_disagrees_with_the_event_it_links_to(): void
    {
        $this->eventWithCost(estimated: 250_000, actual: 310_000, paid: 90_000);
        $this->eventWithCost(estimated: 600_000);

        foreach (app(PortfolioFinance::class)->statement() as $row) {
            $event = $row['event'];

            // Income comes from the event's own summary, not a second derivation.
            $this->assertSame($event->incomeSummary()['total'], $row['income']);
            $this->assertSame($event->incomeSummary()['collected'], $row['collected']);

            // Cost and outstanding come from the same helpers the Budget tab uses.
            $this->assertSame(
                (int) $event->budgetItems->sum(fn (EventBudgetItem $i) => $i->costCents()),
                $row['cost']
            );
            $this->assertSame(
                (int) $event->budgetItems->sum(fn (EventBudgetItem $i) => $i->outstandingCents()),
                $row['payable']
            );
        }
    }
}
