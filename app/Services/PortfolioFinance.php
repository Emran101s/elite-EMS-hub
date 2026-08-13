<?php

namespace App\Services;

use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventBudgetItem;
use App\Models\EventContractPayment;
use Illuminate\Support\Collection;

/**
 * Money across the whole book.
 *
 * Every figure here comes from the same helpers an event's own Budget tab uses
 * — Event::incomeSummary(), EventBudgetItem::outstandingCents(),
 * EventContractPayment::status() — so the portfolio can never quietly disagree
 * with the event you click into. This layer aggregates; it does not re-derive.
 */
class PortfolioFinance
{
    /** Loaded once: a P&L touches income, budget lines and contract payments. */
    private ?Collection $eventsMemo = null;

    /** Rates into the base currency, worked out once per run. */
    private array $rates = [];

    private ?string $baseMemo = null;

    public function __construct(private readonly CurrencyService $fx) {}

    /**
     * The currency the whole book is reported in — the company's own.
     *
     * Events are run in whatever currency the client pays in, so a portfolio
     * that added JD to $ was adding numbers that are not the same thing. Every
     * figure below is converted into this one before it meets another.
     */
    public function baseCurrency(): string
    {
        return $this->baseMemo ??= strtoupper(CompanyProfile::current()->default_currency ?: 'USD');
    }

    /** Cents in an event's own currency, as cents in the base currency. */
    private function toBase(int $cents, ?string $from): int
    {
        $from = strtoupper($from ?: $this->baseCurrency());

        if ($from === $this->baseCurrency()) {
            return $cents;
        }

        $rate = $this->rates[$from] ??= $this->fx->rate($from, $this->baseCurrency());

        return (int) round($cents * $rate);
    }

    /** True when more than one currency is in play — worth saying on screen. */
    public function isMixed(): bool
    {
        return $this->events()
            ->map(fn (Event $e) => strtoupper($e->currency ?: $this->baseCurrency()))
            ->unique()->count() > 1;
    }

    private function events(): Collection
    {
        return $this->eventsMemo ??= Event::whereNull('archived_at')
            ->with(['budgetItems', 'incomeItems', 'sponsors', 'exhibitors', 'client', 'invoices',
                'contract.payments', 'contracts.payments'])
            ->orderBy('starts_at')
            ->get();
    }

    /**
     * One row per event: what it should earn, what it has collected, what it
     * costs, and what is left.
     */
    public function statement(): Collection
    {
        return $this->events()->map(function (Event $event) {
            $income = $event->incomeSummary();
            $priced = $event->sellSummary();
            $cur = strtoupper($event->currency ?: $this->baseCurrency());
            $in = fn (int $cents) => $this->toBase($cents, $cur);

            $estimated = $in((int) $event->budgetItems->sum('estimated_cents'));
            $actual = $in((int) $event->budgetItems->sum('actual_cents'));
            // Committed cost: the real figure where one exists, the estimate
            // until then. Spending you have agreed to counts even unpaid.
            $cost = $in($priced['cost']);
            $paid = $in((int) $event->budgetItems->sum('paid_cents'));

            $booked = $in($income['total']);
            $charged = $in($priced['sell']);
            $net = $booked - $cost;

            return [
                'event' => $event,
                'currency' => $cur,
                // What has actually been contracted and sold.
                'income' => $booked,
                'collected' => $in($income['collected']),
                'receivable' => max(0, $booked - $in($income['collected'])),
                // What the work is priced at, line by line. Not the same
                // question: an event can be fully priced and have billed none
                // of it, and the gap between the two is the invoice you owe.
                'charged' => $charged,
                // Only the CLIENT's income is comparable with what the budget
                // is priced at: sponsorship and exhibition money never came
                // from a cost line, so measuring the priced work against total
                // income would say you had over-billed by 300%.
                'clientIncome' => $in($income['client']),
                'unbilled' => max(0, $charged - $in($income['client'])),
                'estimated' => $estimated,
                'actual' => $actual,
                'cost' => $cost,
                'paid' => $paid,
                'payable' => max(0, $cost - $paid),
                'net' => $net,
                // Margin against income, not against budget — the question is
                // what share of what you charge you keep.
                'margin' => $booked > 0 ? (int) round($net / $booked * 100) : null,
                // The same sum against the priced figure: what the work is
                // worth, whether or not it has been billed yet.
                'pricedMargin' => $charged > 0 ? (int) round(($charged - $cost) / $charged * 100) : null,
                'overrun' => $estimated > 0 && $actual > $estimated
                    ? (int) round(($actual - $estimated) / $estimated * 100)
                    : null,
            ];
        });
    }

    /** The portfolio in one line. */
    public function totals(): array
    {
        $rows = $this->statement();

        $income = (int) $rows->sum('income');
        $cost = (int) $rows->sum('cost');

        $charged = (int) $rows->sum('charged');

        return [
            'currency' => $this->baseCurrency(),
            'mixed' => $this->isMixed(),
            'income' => $income,
            'clientIncome' => (int) $rows->sum('clientIncome'),
            'collected' => (int) $rows->sum('collected'),
            'receivable' => (int) $rows->sum('receivable'),
            'charged' => $charged,
            'unbilled' => (int) $rows->sum('unbilled'),
            'cost' => $cost,
            'paid' => (int) $rows->sum('paid'),
            'payable' => (int) $rows->sum('payable'),
            'net' => $income - $cost,
            'margin' => $income > 0 ? (int) round(($income - $cost) / $income * 100) : null,
            'pricedMargin' => $charged > 0 ? (int) round(($charged - $cost) / $charged * 100) : null,
            'events' => $rows->count(),
            'overdueReceivable' => $this->overdueReceivableCents(),
            'overdueCount' => $this->overdueReceivableCount(),
        ];
    }

    /**
     * Contract instalments money is owed against, past their due date and
     * still short — the same rows receivables() lists, without its display
     * cap. A partly-paid instalment counts here too: some of it landing does
     * not make the rest on time, and EventContractPayment::status() would
     * otherwise call that "partial" and drop it from "overdue" silently.
     */
    private function overdueReceivablesQuery()
    {
        return EventContractPayment::query()
            ->with('event')
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->whereNotNull('due_on')
            ->where('due_on', '<', now()->toDateString())
            ->whereColumn('paid_cents', '<', 'amount_cents');
    }

    /** Overdue money owed to you, converted into the book's base currency. */
    public function overdueReceivableCents(): int
    {
        return (int) $this->overdueReceivablesQuery()->get()
            ->sum(fn (EventContractPayment $p) => $this->toBase($p->outstandingCents(), $p->event?->currency));
    }

    /** How many instalments that is — portfolio-wide, not the "Due now" panel's take(). */
    public function overdueReceivableCount(): int
    {
        return $this->overdueReceivablesQuery()->count();
    }

    /**
     * Money owed TO you: contract instalments not fully paid, oldest first.
     * Overdue ones lead, because those are the ones that need a phone call.
     */
    public function receivables(int $limit = 12): Collection
    {
        return EventContractPayment::with(['event.client'])
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            ->whereColumn('paid_cents', '<', 'amount_cents')
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->take($limit)
            ->get();
    }

    /** Money owed BY you: budget lines with something still outstanding. */
    public function payables(int $limit = 12): Collection
    {
        return EventBudgetItem::with(['event.client', 'supplier'])
            ->whereHas('event', fn ($q) => $q->whereNull('archived_at'))
            // The same rule as outstandingCents(), in SQL: pay against the
            // actual where one is recorded, against the estimate until then.
            // NULL is "not costed", and a comparison with NULL is unknown
            // rather than false, so each branch names which it wants.
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->whereNotNull('actual_cents')->whereColumn('paid_cents', '<', 'actual_cents'))
                ->orWhere(fn ($w) => $w->whereNull('actual_cents')->whereColumn('paid_cents', '<', 'estimated_cents')))
            ->orderByRaw('due_on is null')
            ->orderBy('due_on')
            ->take($limit)
            ->get()
            ->filter(fn (EventBudgetItem $i) => $i->outstandingCents() > 0)
            ->values();
    }

    /**
     * Where the money goes. Cost by budget category across the portfolio —
     * the answer to "what are we actually spending on".
     */
    public function costByCategory(): Collection
    {
        // Walked from the events rather than from the lines, so each line's
        // currency comes from the event already in memory instead of a
        // lazy-loaded belongsTo per line.
        return $this->events()
            ->flatMap(fn (Event $event) => $event->budgetItems->map(fn (EventBudgetItem $i) => [
                'category' => $i->category,
                'label' => $i->categoryLabel(),
                'cost' => $this->toBase($i->costCents(), $event->currency),
            ]))
            ->groupBy('category')
            ->map(fn (Collection $lines, string $category) => [
                'category' => $category,
                'label' => $lines->first()['label'],
                'cost' => (int) $lines->sum('cost'),
                'lines' => $lines->count(),
            ])
            ->sortByDesc('cost')
            ->values();
    }

    /** Which kind of work earns. Net and margin grouped by event type. */
    public function marginByType(): Collection
    {
        return $this->statement()
            ->groupBy(fn (array $row) => $row['event']->type)
            ->map(function (Collection $rows, string $type) {
                $income = (int) $rows->sum('income');
                $cost = (int) $rows->sum('cost');

                return [
                    'type' => str($type)->replace('_', ' ')->title()->toString(),
                    'events' => $rows->count(),
                    'income' => $income,
                    'net' => $income - $cost,
                    'margin' => $income > 0 ? (int) round(($income - $cost) / $income * 100) : null,
                ];
            })
            ->sortByDesc('net')
            ->values();
    }
}
