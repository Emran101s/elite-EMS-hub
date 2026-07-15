<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventAccommodation;
use Livewire\Attributes\Validate;
use Livewire\Component;

class AccommodationTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    #[Validate('required|string|max:120')]
    public string $hotel = '';

    #[Validate('nullable|string|max:120')]
    public string $guest = '';

    #[Validate('nullable|string|max:80')]
    public string $room_type = '';

    #[Validate('required|integer|min:1')]
    public int $rooms = 1;

    #[Validate('nullable|date')]
    public string $check_in = '';

    #[Validate('nullable|date|after_or_equal:check_in')]
    public string $check_out = '';

    #[Validate('nullable|numeric|min:0')]
    public string $rate = '';

    #[Validate('nullable|numeric|min:0')]
    public string $cost = '';

    #[Validate('required|in:held,booked,confirmed,cancelled')]
    public string $status = 'held';

    #[Validate('nullable|string|max:60')]
    public string $confirmation_number = '';

    #[Validate('nullable|string|max:400')]
    public string $notes = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'hotel', 'guest', 'room_type', 'check_in', 'check_out', 'rate', 'cost', 'confirmation_number', 'notes']);
        $this->rooms = 1;
        $this->status = 'held';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $a = $this->event->accommodations()->findOrFail($id);
        $this->editingId = $a->id;
        $this->hotel = $a->hotel;
        $this->guest = $a->guest ?? '';
        $this->room_type = $a->room_type ?? '';
        $this->rooms = max(1, $a->rooms);
        $this->check_in = $a->check_in?->format('Y-m-d') ?? '';
        $this->check_out = $a->check_out?->format('Y-m-d') ?? '';
        $this->rate = $a->rate_cents ? (string) ($a->rate_cents / 100) : '';
        $this->cost = $a->cost_cents ? (string) ($a->cost_cents / 100) : '';
        $this->status = $a->status;
        $this->confirmation_number = $a->confirmation_number ?? '';
        $this->notes = $a->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate();

        $rateCents = (int) round((float) ($this->rate ?: 0) * 100);
        $rooms = max(1, $this->rooms);
        $nights = $this->check_in && $this->check_out
            ? max(0, \Carbon\Carbon::parse($this->check_in)->diffInDays(\Carbon\Carbon::parse($this->check_out)))
            : 0;

        // Auto-total from rate × rooms × nights when a rate is given and cost is blank.
        $costCents = $this->cost !== ''
            ? (int) round((float) $this->cost * 100)
            : ($rateCents && $nights ? $rateCents * $rooms * $nights : 0);

        $data = [
            'hotel' => $this->hotel,
            'guest' => $this->guest ?: null,
            'room_type' => $this->room_type ?: null,
            'rooms' => $rooms,
            'check_in' => $this->check_in ?: null,
            'check_out' => $this->check_out ?: null,
            'rate_cents' => $rateCents,
            'cost_cents' => $costCents,
            'status' => $this->status,
            'confirmation_number' => $this->confirmation_number ?: null,
            'notes' => $this->notes ?: null,
        ];

        $this->editingId
            ? $this->event->accommodations()->findOrFail($this->editingId)->update($data)
            : $this->event->accommodations()->create($data);

        $this->showForm = false;
        session()->flash('status', 'Accommodation saved.');
    }

    public function delete(int $id): void
    {
        $this->event->accommodations()->whereKey($id)->delete();
    }

    public function render()
    {
        $bookings = $this->event->accommodations()->orderBy('check_in')->orderBy('id')->get();

        return view('livewire.hub.accommodation-tab', [
            'bookings' => $bookings,
            'roomNightsTotal' => $bookings->sum(fn ($b) => $b->roomNights()),
            'costTotal' => $bookings->sum('cost_cents'),
        ]);
    }
}
