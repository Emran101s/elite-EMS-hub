<?php

namespace Tests\Feature;

use App\Livewire\FinanceOverview;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\EventIncomeItem;
use App\Models\User;
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
    private function eventWithCost(int $estimated, int $actual = 0, int $paid = 0, int $income = 0): Event
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
            'description' => 'Lunch', 'estimated_cents' => 90_000, 'actual_cents' => 0, 'paid_cents' => 0,
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
                (int) $event->budgetItems->sum(fn (EventBudgetItem $i) => $i->actual_cents ?: $i->estimated_cents),
                $row['cost']
            );
            $this->assertSame(
                (int) $event->budgetItems->sum(fn (EventBudgetItem $i) => $i->outstandingCents()),
                $row['payable']
            );
        }
    }
}
