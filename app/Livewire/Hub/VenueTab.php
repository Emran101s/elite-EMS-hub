<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventRoom;
use App\Models\Venue;
use Livewire\Component;

class VenueTab extends Component
{
    public Event $event;

    // Venue assignment
    public ?int $venue_id = null;

    // Room form
    public bool $showRoomForm = false;
    public ?int $editingRoomId = null;
    public string $room_name = '';
    public string $room_type = 'breakout';
    public string $room_capacity = '';

    public function mount(): void
    {
        $this->venue_id = $this->event->venue_id;
    }

    public function updatedVenueId($value): void
    {
        $this->event->update(['venue_id' => $value ?: null]);
        session()->flash('status', 'Venue updated.');
    }

    public function newRoom(): void
    {
        $this->reset(['editingRoomId', 'room_name', 'room_capacity']);
        $this->room_type = 'breakout';
        $this->showRoomForm = true;
    }

    public function editRoom(int $roomId): void
    {
        $room = $this->event->rooms()->findOrFail($roomId);
        $this->editingRoomId = $room->id;
        $this->room_name = $room->name;
        $this->room_type = $room->type;
        $this->room_capacity = (string) ($room->capacity ?? '');
        $this->showRoomForm = true;
    }

    public function saveRoom()
    {
        $this->validate([
            'room_name' => ['required', 'string', 'max:120'],
            'room_type' => ['required', 'in:'.implode(',', EventRoom::TYPES)],
            'room_capacity' => ['nullable', 'integer', 'min:0'],
        ]);

        $data = [
            'name' => $this->room_name,
            'type' => $this->room_type,
            'capacity' => $this->room_capacity !== '' ? (int) $this->room_capacity : null,
        ];

        if ($this->editingRoomId) {
            $this->event->rooms()->whereKey($this->editingRoomId)->firstOrFail()->update($data);
        } else {
            $this->event->rooms()->create($data);
        }

        session()->flash('status', $this->editingRoomId ? 'Room updated.' : "Room “{$this->room_name}” added — it's now available in the Agenda.");

        return $this->redirectTab();
    }

    public function deleteRoom(int $roomId)
    {
        // Sessions in this room fall back to "no room" (nullOnDelete on the FK).
        $this->event->rooms()->whereKey($roomId)->firstOrFail()->delete();

        return $this->redirectTab();
    }

    private function redirectTab()
    {
        return $this->redirectRoute('events.hub', [$this->event, 'tab' => 'venue']);
    }

    public function render()
    {
        return view('livewire.hub.venue-tab', [
            'venues' => Venue::orderBy('name')->get(),
            'rooms' => $this->event->rooms()->withCount('sessions')->orderBy('name')->get(),
        ]);
    }
}
