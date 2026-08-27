<?php

namespace App\Livewire;

use App\Services\PortfolioFinance;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
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
    'hideTitleRow' => true,
])]
class FinanceOverview extends Component
{
    /** net · margin · income · cost · charged */
    #[Url(as: 'sort')]
    public string $sort = 'net';

    #[Url(as: 'selected')]
    public ?int $selectedId = null;

    public function select(int $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
    }

    public function sortBy(string $key): void
    {
        if (in_array($key, ['net', 'margin', 'income', 'cost', 'charged'], true)) {
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
            'charged' => $rows->sortByDesc('charged'),
            'income' => $rows->sortByDesc('income'),
            'cost' => $rows->sortByDesc('cost'),
            default => $rows->sortByDesc('net'),
        };

        $rows = $sorted->values();
        $selected = $rows->first(fn (array $r) => ($r['event']->id ?? null) === $this->selectedId) ?? $rows->first();

        return view('livewire.finance-overview', [
            'rows' => $rows,
            'selected' => $selected,
            'totals' => $finance->totals(),
            'receivables' => $finance->receivables(),
            'payables' => $finance->payables(),
            'categories' => $finance->costByCategory(),
            'byType' => $finance->marginByType(),
        ]);
    }
}
