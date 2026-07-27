<?php

namespace App\Livewire;

use App\Services\PortfolioFinance;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * Money across the book.
 *
 * Every event already tracks its own budget, income and contract payments.
 * This is the layer above: what the portfolio earns, what it costs, what is
 * owed in each direction, and which kind of work is actually worth doing.
 * It aggregates the event-level helpers rather than re-deriving anything, so
 * a figure here and the same figure inside an event cannot drift apart.
 */
#[Layout('components.layouts.app', [
    'title' => 'Finance',
    'subtitle' => 'The whole book — what it earns, what it costs, and who owes whom.',
])]
class FinanceOverview extends Component
{
    /** net · margin · income · cost */
    public string $sort = 'net';

    public function sortBy(string $key): void
    {
        if (in_array($key, ['net', 'margin', 'income', 'cost'], true)) {
            $this->sort = $key;
        }
    }

    public function render()
    {
        $finance = app(PortfolioFinance::class);
        $rows = $finance->statement();

        $sorted = match ($this->sort) {
            // Unmeasurable margin sorts last rather than as zero.
            'margin' => $rows->sortByDesc(fn (array $r) => $r['margin'] ?? -1),
            'income' => $rows->sortByDesc('income'),
            'cost' => $rows->sortByDesc('cost'),
            default => $rows->sortByDesc('net'),
        };

        return view('livewire.finance-overview', [
            'rows' => $sorted->values(),
            'totals' => $finance->totals(),
            'receivables' => $finance->receivables(),
            'payables' => $finance->payables(),
            'categories' => $finance->costByCategory(),
            'byType' => $finance->marginByType(),
        ]);
    }
}
