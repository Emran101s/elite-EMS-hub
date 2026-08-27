<?php

namespace App\Livewire\VenueStudio;

use App\Models\EventRoom;
use App\Models\Venue;
use App\Models\VenueSpace;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * The venue's own inventory of halls and rooms — the thing that did not
 * exist before Venue Studio. Every space here persists across every event
 * booked at this venue; an EventRoom created for a specific booking can
 * optionally point back at one (see Hub\VenueTab::saveRoom()), but nothing
 * here is per-event.
 */
class SpaceExplorerTab extends Component
{
    public Venue $venue;

    public bool $showForm = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $type = 'breakout';

    public string $capacity = '';

    public string $width_m = '';

    public string $length_m = '';

    public string $floor_zone = '';

    public string $notes = '';

    /** @var array<string,string> setup key => capacity string, keyed by EventRoom::SEATING_ARRANGEMENTS */
    public array $setupCapacity = [];

    public function mount(): void
    {
        $this->resetSetupCapacity();
    }

    private function resetSetupCapacity(): void
    {
        $this->setupCapacity = collect(EventRoom::SEATING_ARRANGEMENTS)->map(fn () => '')->all();
    }

    public function newSpace(): void
    {
        $this->reset(['editingId', 'name', 'capacity', 'width_m', 'length_m', 'floor_zone', 'notes']);
        $this->type = 'breakout';
        $this->resetSetupCapacity();
        $this->showForm = true;
    }

    public function editSpace(int $spaceId): void
    {
        $space = $this->venue->spaces()->findOrFail($spaceId);
        $this->editingId = $space->id;
        $this->name = $space->name;
        $this->type = $space->type;
        $this->capacity = (string) ($space->capacity ?? '');
        $this->width_m = $space->width_m !== null ? (string) $space->width_m : '';
        $this->length_m = $space->length_m !== null ? (string) $space->length_m : '';
        $this->floor_zone = $space->floor_zone ?? '';
        $this->notes = $space->notes ?? '';
        $this->resetSetupCapacity();
        foreach ($space->capacity_by_setup ?? [] as $key => $value) {
            if (array_key_exists($key, $this->setupCapacity)) {
                $this->setupCapacity[$key] = (string) $value;
            }
        }
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');
        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'type' => ['required', 'string', 'max:40'],
            'capacity' => ['nullable', 'integer', 'min:0'],
            'width_m' => ['nullable', 'numeric', 'min:0'],
            'length_m' => ['nullable', 'numeric', 'min:0'],
            'floor_zone' => ['nullable', 'string', 'max:80'],
            'notes' => ['nullable', 'string'],
        ]);

        $data = [
            'name' => $this->name,
            'type' => $this->type,
            'capacity' => $this->capacity !== '' ? (int) $this->capacity : null,
            'capacity_by_setup' => collect($this->setupCapacity)->filter(fn ($v) => $v !== '')->map(fn ($v) => (int) $v)->all() ?: null,
            'width_m' => $this->width_m !== '' ? (float) $this->width_m : null,
            'length_m' => $this->length_m !== '' ? (float) $this->length_m : null,
            'floor_zone' => $this->floor_zone ?: null,
            'notes' => $this->notes ?: null,
        ];

        if ($this->editingId) {
            $this->venue->spaces()->whereKey($this->editingId)->firstOrFail()->update($data);
        } else {
            $data['position'] = $this->venue->spaces()->count();
            $this->venue->spaces()->create($data);
        }

        $this->showForm = false;
        session()->flash('status', $this->editingId ? 'Space updated.' : "Space “{$this->name}” added.");
    }

    public function delete(int $spaceId): void
    {
        Gate::authorize('write');
        // Bookings that pointed here fall back to unlinked (nullOnDelete) —
        // the EventRoom itself, and its layout, is untouched.
        $this->venue->spaces()->whereKey($spaceId)->firstOrFail()->delete();
    }

    public function render()
    {
        return view('livewire.venue-studio.space-explorer-tab', [
            'spaces' => $this->venue->spaces()->withCount('bookings')->get(),
            'arrangements' => EventRoom::SEATING_ARRANGEMENTS,
            'types' => VenueSpace::TYPES,
        ]);
    }
}
