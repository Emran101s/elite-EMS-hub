<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventRoom;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app', ['title' => 'Layout Builder'])]
class RoomLayoutBuilder extends Component
{
    public Event $event;

    public EventRoom $room;

    /** Placed elements: [id, type, x, y, rot, seats]. */
    public array $elements = [];

    public function mount(Event $event, EventRoom $room): void
    {
        abort_unless($room->event_id === $event->id, 404);
        $this->event = $event;
        $this->room = $room;
        $this->elements = $room->layout ?? [];
    }

    public function addElement(string $type): void
    {
        if (! array_key_exists($type, EventRoom::LAYOUT_PRESETS)) {
            return;
        }
        [, $seats, $w, $h] = EventRoom::LAYOUT_PRESETS[$type];

        $this->elements[] = [
            'id' => Str::random(8),
            'type' => $type,
            'x' => 480, 'y' => 280, // canvas center (960×560)
            'rot' => 0,
            'seats' => $seats,
            'w' => $w, 'h' => $h,
        ];
        $this->persist();
    }

    public function moveElement(string $id, float $x, float $y): void
    {
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $x, $y) {
            if ($el['id'] === $id) {
                $el['x'] = max(0, min(960, round($x)));
                $el['y'] = max(0, min(560, round($y)));
            }

            return $el;
        })->all();
        $this->persist();
    }

    public function rotate(string $id): void
    {
        $this->elements = collect($this->elements)->map(function ($el) use ($id) {
            if ($el['id'] === $id) {
                $el['rot'] = (($el['rot'] ?? 0) + 45) % 360;
            }

            return $el;
        })->all();
        $this->persist();
    }

    public function changeSeats(string $id, int $delta): void
    {
        $this->elements = collect($this->elements)->map(function ($el) use ($id, $delta) {
            if ($el['id'] === $id) {
                $el['seats'] = max(0, ($el['seats'] ?? 0) + $delta);
            }

            return $el;
        })->all();
        $this->persist();
    }

    public function removeElement(string $id): void
    {
        $this->elements = collect($this->elements)->reject(fn ($el) => $el['id'] === $id)->values()->all();
        $this->persist();
    }

    public function clearAll(): void
    {
        $this->elements = [];
        $this->persist();
    }

    private function persist(): void
    {
        $this->room->update(['layout' => $this->elements]);
    }

    public function render()
    {
        return view('livewire.room-layout-builder', [
            'presets' => EventRoom::LAYOUT_PRESETS,
            'seatTotal' => collect($this->elements)->sum(fn ($el) => (int) ($el['seats'] ?? 0)),
        ]);
    }
}
