<?php

namespace App\Livewire;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Supplier;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The platform-wide vendor directory. Every event's Budget, Catering, Stay and
 * Transport modules pick a supplier from here, so a caterer or AV house is
 * entered once and reused — not retyped as a free-text "vendor" on every line.
 */
#[Layout('components.layouts.app', ['title' => 'Suppliers'])]
class SuppliersManager extends Component
{
    use BulkSelectable;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Url(as: 'q')]
    public string $search = '';

    #[Url(as: 'show')]
    public string $filter = 'all';

    // ── Form ────────────────────────────────────────────────────
    public string $name = '';

    public string $category = '';

    public string $rating = '';

    public string $email = '';

    public string $phone = '';

    public string $city = '';

    public string $country = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'category', 'rating', 'email', 'phone', 'city', 'country']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $s = Supplier::findOrFail($id);
        $this->editingId = $s->id;
        $this->name = $s->name;
        $this->category = $s->category ?? '';
        $this->rating = $s->rating !== null ? rtrim(rtrim(number_format($s->rating, 1), '0'), '.') : '';
        $this->email = $s->email ?? '';
        $this->phone = $s->phone ?? '';
        $this->city = $s->city ?? '';
        $this->country = $s->country ?? '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'category' => ['nullable', 'string', 'in:'.implode(',', Supplier::CATEGORIES)],
            'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
            'email' => ['nullable', 'email', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
        ]);

        $data = [
            'name' => trim($this->name),
            'category' => $this->category ?: null,
            'rating' => $this->rating !== '' ? (float) $this->rating : 0,
            'email' => trim($this->email) ?: null,
            'phone' => trim($this->phone) ?: null,
            'city' => trim($this->city) ?: null,
            'country' => trim($this->country) ?: null,
        ];

        $this->editingId
            ? Supplier::whereKey($this->editingId)->update($data)
            : Supplier::create($data);

        $this->showForm = false;
        session()->flash('status', 'Supplier saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        // Every supplier_id reference (budget, catering, room blocks, transport)
        // is nullOnDelete — those lines keep working, just unlinked from a vendor.
        Supplier::whereKey($id)->delete();
    }

    public function deleteSelected(): void
    {
        Gate::authorize('write');
        Supplier::whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    public function render()
    {
        $suppliers = Supplier::when($this->filter !== 'all', fn ($q) => $q->where('category', $this->filter))
            ->when($this->search !== '', fn ($q) => $q
                ->where(fn ($w) => $w->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('city', 'like', '%'.$this->search.'%')
                    ->orWhere('country', 'like', '%'.$this->search.'%')))
            ->withCount('events')
            ->orderByDesc('rating')->orderBy('name')->get();

        return view('livewire.suppliers-manager', [
            'suppliers' => $suppliers,
            'counts' => collect(['all' => Supplier::count()])
                ->merge(collect(Supplier::CATEGORIES)->mapWithKeys(fn ($c) => [$c => Supplier::where('category', $c)->count()])),
        ]);
    }
}
