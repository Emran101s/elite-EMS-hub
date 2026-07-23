<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\BulkSelectable;
use App\Models\Event;
use Livewire\Attributes\Validate;
use Livewire\Component;

class ExhibitionTab extends Component
{
    use BulkSelectable;

    public Event $event;

    public function deleteSelected(): void
    {
        $this->event->exhibitors()->whereIn('id', $this->selectedIds())->delete();
        $this->clearSelection();
    }

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:120')]
    public string $company = '';

    #[Validate('nullable|string|max:120')]
    public string $contact_name = '';

    #[Validate('nullable|email|max:160')]
    public string $email = '';

    #[Validate('nullable|string|max:40')]
    public string $phone = '';

    #[Validate('nullable|string|max:20')]
    public string $booth_number = '';

    #[Validate('nullable|string|max:30')]
    public string $booth_size = '';

    #[Validate('required|in:standard,premium,island,custom')]
    public string $package = 'standard';

    #[Validate('nullable|numeric|min:0')]
    public string $fee = '';

    #[Validate('nullable|numeric|min:0')]
    public string $paid = '';

    #[Validate('required|in:reserved,confirmed,paid,cancelled')]
    public string $status = 'reserved';

    #[Validate('nullable|string|max:400')]
    public string $notes = '';

    public string $exhibitionTarget = '';

    public function mount(): void
    {
        $this->exhibitionTarget = $this->event->exhibition_target_cents ? (string) ($this->event->exhibition_target_cents / 100) : '';
    }

    public function updatedExhibitionTarget(): void
    {
        $this->event->update([
            'exhibition_target_cents' => is_numeric($this->exhibitionTarget) ? (int) round((float) $this->exhibitionTarget * 100) : null,
        ]);
    }

    public function newItem(): void
    {
        $this->reset(['editingId', 'company', 'contact_name', 'email', 'phone', 'booth_number', 'booth_size', 'fee', 'paid', 'notes']);
        $this->package = 'standard';
        $this->status = 'reserved';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $x = $this->event->exhibitors()->findOrFail($id);
        $this->editingId = $x->id;
        $this->company = $x->company;
        $this->contact_name = $x->contact_name ?? '';
        $this->email = $x->email ?? '';
        $this->phone = $x->phone ?? '';
        $this->booth_number = $x->booth_number ?? '';
        $this->booth_size = $x->booth_size ?? '';
        $this->package = $x->package;
        $this->fee = $x->fee_cents ? (string) ($x->fee_cents / 100) : '';
        $this->paid = $x->paid_cents ? (string) ($x->paid_cents / 100) : '';
        $this->status = $x->status;
        $this->notes = $x->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'company' => $this->company,
            'contact_name' => $this->contact_name ?: null,
            'email' => $this->email ?: null,
            'phone' => $this->phone ?: null,
            'booth_number' => $this->booth_number ?: null,
            'booth_size' => $this->booth_size ?: null,
            'package' => $this->package,
            'fee_cents' => (int) round((float) ($this->fee ?: 0) * 100),
            'paid_cents' => (int) round((float) ($this->paid ?: 0) * 100),
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ];

        $this->editingId
            ? $this->event->exhibitors()->findOrFail($this->editingId)->update($data)
            : $this->event->exhibitors()->create($data);

        $this->showForm = false;
        session()->flash('status', 'Exhibitor saved.');
    }

    public function delete(int $id): void
    {
        $this->event->exhibitors()->whereKey($id)->delete();
    }

    public function render()
    {
        $exhibitors = $this->event->exhibitors()->orderBy('booth_number')->orderBy('company')->get();

        return view('livewire.hub.exhibition-tab', [
            'exhibitors' => $exhibitors,
            'confirmed' => $exhibitors->whereIn('status', ['confirmed', 'paid'])->count(),
            'revenueTotal' => $exhibitors->where('status', '!=', 'cancelled')->sum('fee_cents'),
            'collectedTotal' => $exhibitors->sum('paid_cents'),
            'target' => $this->event->exhibition_target_cents ?? 0,
        ]);
    }
}
