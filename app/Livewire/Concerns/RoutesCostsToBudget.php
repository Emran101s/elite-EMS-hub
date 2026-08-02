<?php

namespace App\Livewire\Concerns;

use App\Services\BudgetSync;
use Illuminate\Support\Facades\Gate;

/**
 * A module says which budget category its costs land in.
 *
 * Every module that spends money mirrors that spend into the budget, and until
 * now the section it landed in was decided in code: everything guest-facing in
 * one category, every hall in another. An event that budgets transport apart
 * from accommodation had no way to say so, and the category list the desk is
 * free to rename and extend had entries nothing could ever be pointed at.
 *
 * The choice is made where the money is — in the module's own Control Center —
 * because that is where somebody is looking when the question occurs to them.
 * Changing it re-files the lines at once rather than at the next sync, so the
 * budget on the other tab is never a version behind.
 */
trait RoutesCostsToBudget
{
    /** The module key this component routes — see Event::MODULE_BUDGET_DEFAULTS. */
    abstract public function budgetModule(): string;

    public function routeCostsTo(string $category): void
    {
        Gate::authorize('write');

        if (! $this->event->routeModuleCosts($this->budgetModule(), $category)) {
            return;
        }

        // Re-file what is already there. Leaving it to the next sync means the
        // Budget tab disagrees with the choice just made on this one.
        app(BudgetSync::class)->sync($this->event->fresh());
    }

    /** What the picker shows: this event's categories, and the one in use. */
    public function budgetRouting(): array
    {
        return [
            'module' => $this->budgetModule(),
            'label' => \App\Models\Event::MODULE_BUDGET_LABELS[$this->budgetModule()] ?? 'This module',
            'current' => $this->event->moduleBudgetCategory($this->budgetModule()),
            'categories' => $this->event->budgetCategories()->pluck('name')->all(),
        ];
    }
}
