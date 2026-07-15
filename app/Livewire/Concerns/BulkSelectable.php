<?php

namespace App\Livewire\Concerns;

/**
 * Adds multi-select to a Livewire list component. The component keeps a set of
 * selected row ids; the view renders a checkbox per row and a selection bar.
 * Each component implements its own deleteSelected() (the delete query differs
 * per model — e.g. skip synced/locked rows).
 */
trait BulkSelectable
{
    /** Selected row ids (ints as strings from the DOM). */
    public array $selected = [];

    public function toggleSelect($id): void
    {
        $id = (int) $id;
        if (in_array($id, $this->selectedIds(), true)) {
            $this->selected = array_values(array_diff($this->selectedIds(), [$id]));
        } else {
            $this->selected[] = $id;
        }
    }

    public function selectMany(array $ids): void
    {
        $this->selected = array_values(array_unique([...$this->selectedIds(), ...array_map('intval', $ids)]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function isSelected($id): bool
    {
        return in_array((int) $id, $this->selectedIds(), true);
    }

    public function selectedCount(): int
    {
        return count($this->selected);
    }

    /** Normalised int ids. */
    protected function selectedIds(): array
    {
        return array_map('intval', $this->selected);
    }
}
