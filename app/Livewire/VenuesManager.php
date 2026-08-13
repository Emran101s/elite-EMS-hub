<?php

namespace App\Livewire;

use App\Models\Venue;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The platform-wide library of locations. Every event's Settings picks a venue
 * from here, so a hotel is entered once and reused. Flat: rooms/halls inside an
 * event are managed per-event in the Venue & Rooms hub tab, not here.
 */
#[Layout('components.layouts.app', ['title' => 'Venues & Locations', 'hideTitleRow' => true])]
class VenuesManager extends Component
{
    public bool $showForm = false;

    public ?int $editingId = null;

    public string $search = '';

    // ── Form ────────────────────────────────────────────────────
    public string $name = '';

    public string $type = '';

    public string $address = '';

    public string $city = '';

    public string $country = '';

    public ?int $capacity = null;

    public string $contact_name = '';

    public string $contact_phone = '';

    public string $contact_email = '';

    public string $notes = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'name', 'type', 'address', 'city', 'country',
            'capacity', 'contact_name', 'contact_phone', 'contact_email', 'notes']);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $v = Venue::findOrFail($id);
        $this->editingId = $v->id;
        $this->name = $v->name;
        $this->type = $v->type ?? '';
        $this->address = $v->address ?? '';
        $this->city = $v->city ?? '';
        $this->country = $v->country ?? '';
        $this->capacity = $v->capacity;
        $this->contact_name = $v->contact_name ?? '';
        $this->contact_phone = $v->contact_phone ?? '';
        $this->contact_email = $v->contact_email ?? '';
        $this->notes = $v->notes ?? '';
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');

        $this->validate([
            'name' => ['required', 'string', 'max:160'],
            'type' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:240'],
            'city' => ['nullable', 'string', 'max:120'],
            'country' => ['nullable', 'string', 'max:120'],
            'capacity' => ['nullable', 'integer', 'min:0', 'max:1000000'],
            'contact_name' => ['nullable', 'string', 'max:120'],
            'contact_phone' => ['nullable', 'string', 'max:40'],
            'contact_email' => ['nullable', 'email', 'max:160'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $data = [
            'name' => trim($this->name),
            'type' => trim($this->type) ?: null,
            'address' => trim($this->address) ?: null,
            'city' => trim($this->city) ?: null,
            'country' => trim($this->country) ?: null,
            'capacity' => $this->capacity ?: 0,
            'contact_name' => trim($this->contact_name) ?: null,
            'contact_phone' => trim($this->contact_phone) ?: null,
            'contact_email' => trim($this->contact_email) ?: null,
            'notes' => trim($this->notes) ?: null,
        ];

        $this->editingId
            ? Venue::whereKey($this->editingId)->update($data)
            : Venue::create($data);

        $this->showForm = false;
        session()->flash('status', 'Venue saved.');
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        // events.venue_id is nullOnDelete → events keep working, just unlinked.
        Venue::whereKey($id)->delete();
    }

    /** all | hotels | other — hotels are the half the accommodation module uses. */
    #[Url(as: 'show')]
    public string $filter = 'all';

    public function render()
    {
        $venues = Venue::when($this->filter === 'hotels', fn ($q) => $q->hotels())
            ->when($this->filter === 'other', fn ($q) => $q->whereNot(fn ($w) => $w->where('type', 'like', '%Hotel%')))
            ->when($this->search !== '', fn ($q) => $q
                ->where(fn ($w) => $w->where('name', 'like', '%'.$this->search.'%')
                    ->orWhere('city', 'like', '%'.$this->search.'%')
                    ->orWhere('country', 'like', '%'.$this->search.'%')
                    ->orWhere('type', 'like', '%'.$this->search.'%')))
            ->withCount('events')
            ->orderBy('name')->get();

        return view('livewire.venues-manager', [
            'venues' => $venues,
            'counts' => [
                'all' => Venue::count(),
                'hotels' => Venue::hotels()->count(),
                'other' => Venue::whereNot(fn ($w) => $w->where('type', 'like', '%Hotel%'))->count(),
            ],
        ]);
    }
}
