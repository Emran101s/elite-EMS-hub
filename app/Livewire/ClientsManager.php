<?php

namespace App\Livewire;

use App\Models\Client;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app', ['title' => 'Clients', 'hideTitleRow' => true])]
class ClientsManager extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** Soft Command MDA — selected client in the queue. */
    #[Url(as: 'selected')]
    public ?int $selectedId = null;

    public string $name = '';

    public string $organization = '';

    public string $email = '';

    public string $phone = '';

    public string $website = '';

    public string $notes = '';

    /** Uploaded logo (optional). */
    public $logo = null;

    #[Url(as: 'q')]
    public string $search = '';

    public function select(int $id): void
    {
        $this->selectedId = $this->selectedId === $id ? null : $id;
    }

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'organization', 'email', 'phone', 'website', 'notes', 'logo']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $c = Client::findOrFail($id);
        $this->selectedId = $c->id;
        $this->editingId = $c->id;
        $this->name = $c->name;
        $this->organization = $c->organization ?? '';
        $this->email = $c->email ?? '';
        $this->phone = $c->phone ?? '';
        $this->website = $c->website ?? '';
        $this->notes = $c->notes ?? '';
        $this->logo = null;
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');
        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'organization' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:60'],
            'website' => ['nullable', 'string', 'max:190'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'logo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = [
            'name' => trim($this->name),
            'organization' => trim($this->organization) ?: null,
            'email' => trim($this->email) ?: null,
            'phone' => trim($this->phone) ?: null,
            'website' => trim($this->website) ?: null,
            'notes' => trim($this->notes) ?: null,
        ];

        if ($this->logo) {
            $data['logo_path'] = 'storage/'.$this->logo->store('clients', 'public');
        }

        if ($this->editingId) {
            Client::whereKey($this->editingId)->update($data);
        } else {
            Client::create($data);
        }

        $this->showForm = false;
        $this->reset(['editingId', 'logo']);
        session()->flash('status', 'Client saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('manage-events');
        // events.client_id is nullOnDelete → events keep working, just unlinked.
        Client::whereKey($id)->delete();
    }

    public function render()
    {
        $clients = Client::when($this->search !== '', fn ($q) => $q
            ->where(fn ($w) => $w->where('name', 'like', '%'.$this->search.'%')
                ->orWhere('organization', 'like', '%'.$this->search.'%')
                ->orWhereHas('contacts', fn ($c) => $c->where('name', 'like', '%'.$this->search.'%'))))
            ->with('primaryContact')
            ->withCount('events')
            ->orderBy('name')->get();

        return view('livewire.clients-manager', [
            'clients' => $clients,
            'selected' => $clients->firstWhere('id', $this->selectedId) ?? $clients->first(),
        ]);
    }
}
