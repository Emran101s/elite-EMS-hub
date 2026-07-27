<?php

namespace App\Services;

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

    private function events(): Collection
    {
        return $this->eventsMemo ??= Event::whereNull('archived_at')
            ->with(['budgetItems', 'incomeItems', 'sponsors', 'exhibitors', 'client',
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

            $estimated = (int) $event->budgetItems->sum('estimated_cents');
            $actual = (int) $event->budgetItems->sum('actual_cents');
            // Committed cost: the real figure where one exists, the estimate
            // until then. Spending you have agreed to counts even unpaid.
            $cost = (int) $event->budgetItems->sum(fn (EventBudgetItem $i) => $i->actual_cents ?: $i->estimated_cents);
            $paid = (int) $event->budgetItems->sum('paid_cents');

            $net = $income['total'] - $cost;

            return [
                'event' => $event,
                'income' => $income['total'],
                'collected' => $income['collected'],
                'receivable' => max(0, $income['total'] - $income['collected']),
                'estimated' => $estimated,
                'actual' => $actual,
                'cost' => $cost,
                'paid' => $paid,
                'payable' => max(0, $cost - $paid),
                'net' => $net,
                // Margin against income, not against budget — the question is
                // what share of what you charge you keep.
                'margin' => $income['total'] > 0 ? (int) round($net / $income['total'] * 100) : null,
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

        return [
            'income' => $income,
            'collected' => (int) $rows->sum('collected'),
            'receivable' => (int) $rows->sum('receivable'),
            'cost' => $cost,
            'paid' => (int) $rows->sum('paid'),
            'payable' => (int) $rows->sum('payable'),
            'net' => $income - $cost,
            'margin' => $income > 0 ? (int) round(($income - $cost) / $income * 100) : null,
            'events' => $rows->count(),
        ];
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
            ->where(fn ($q) => $q->whereColumn('paid_cents', '<', 'actual_cents')
                ->orWhere(fn ($w) => $w->where('actual_cents', 0)->whereColumn('paid_cents', '<', 'estimated_cents')))
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
        return $this->events()
            ->flatMap->budgetItems
            ->groupBy('category')
            ->map(fn (Collection $items, string $category) => [
                'category' => $category,
                'label' => $items->first()->categoryLabel(),
                'cost' => (int) $items->sum(fn (EventBudgetItem $i) => $i->actual_cents ?: $i->estimated_cents),
                'lines' => $items->count(),
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
