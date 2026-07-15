<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventTransport;
use Livewire\Attributes\Validate;
use Livewire\Component;

class TransportationTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|in:shuttle,coach,sedan,van,vip,flight')]
    public string $type = 'shuttle';

    #[Validate('required|string|max:160')]
    public string $route = '';

    #[Validate('nullable|string|max:120')]
    public string $provider = '';

    #[Validate('nullable|date')]
    public string $depart_at = '';

    #[Validate('nullable|integer|min:0')]
    public ?int $capacity = null;

    #[Validate('nullable|integer|min:0')]
    public ?int $passengers = null;

    #[Validate('nullable|numeric|min:0')]
    public string $cost = '';

    #[Validate('required|in:planned,booked,confirmed,completed')]
    public string $status = 'planned';

    #[Validate('nullable|string|max:400')]
    public string $notes = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'route', 'provider', 'depart_at', 'capacity', 'passengers', 'cost', 'notes']);
        $this->type = 'shuttle';
        $this->status = 'planned';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $t = $this->event->transport()->findOrFail($id);
        $this->editingId = $t->id;
        $this->type = $t->type;
        $this->route = $t->route;
        $this->provider = $t->provider ?? '';
        $this->depart_at = $t->depart_at?->format('Y-m-d\TH:i') ?? '';
        $this->capacity = $t->capacity;
        $this->passengers = $t->passengers;
        $this->cost = $t->cost_cents ? (string) ($t->cost_cents / 100) : '';
        $this->status = $t->status;
        $this->notes = $t->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'type' => $this->type,
            'route' => $this->route,
            'provider' => $this->provider ?: null,
            'depart_at' => $this->depart_at ?: null,
            'capacity' => $this->capacity,
            'passengers' => $this->passengers ?: 0,
            'cost_cents' => (int) round((float) ($this->cost ?: 0) * 100),
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ];

        $this->editingId
            ? $this->event->transport()->findOrFail($this->editingId)->update($data)
            : $this->event->transport()->create($data);

        $this->showForm = false;
        session()->flash('status', 'Transport movement saved.');
    }

    public function delete(int $id): void
    {
        $this->event->transport()->whereKey($id)->delete();
    }

    public function render()
    {
        $movements = $this->event->transport()->orderBy('depart_at')->orderBy('id')->get();

        return view('livewire.hub.transportation-tab', [
            'movements' => $movements,
            'seatsTotal' => $movements->sum('passengers'),
            'costTotal' => $movements->sum('cost_cents'),
        ]);
    }
}
