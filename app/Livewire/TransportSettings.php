<?php

namespace App\Livewire;

use App\Models\TransportServiceType;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The transport catalogue: what you move people in, and what the movement is.
 *
 * Both lists ship with presets, but only the few you actually use start active.
 * Switching one on is what makes it offerable when adding a movement to an event.
 */
#[Layout('components.layouts.app', ['title' => 'Transport Types'])]
class TransportSettings extends Component
{
    public string $newVehicle = '';

    public int $newVehicleCapacity = 4;

    public string $newService = '';

    public function mount(): void
    {
        VehicleType::ensureSeeded();
        TransportServiceType::ensureSeeded();
    }

    // ── Vehicles ────────────────────────────────────────────────

    public function addVehicle(): void
    {
        Gate::authorize('write');
        $name = trim($this->newVehicle);

        if ($name === '') {
            return;
        }

        if (VehicleType::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            $this->addError('newVehicle', 'That vehicle type already exists.');

            return;
        }

        VehicleType::create([
            'name' => $name,
            'capacity' => max(1, $this->newVehicleCapacity),
            'is_active' => true,
            'position' => (int) VehicleType::max('position') + 1,
        ]);

        $this->reset(['newVehicle']);
        $this->newVehicleCapacity = 4;
    }

    public function toggleVehicle(int $id): void
    {
        Gate::authorize('write');
        $v = VehicleType::findOrFail($id);
        $v->update(['is_active' => ! $v->is_active]);
    }

    public function updateVehicle(int $id, string $field, string $value): void
    {
        Gate::authorize('write');

        if ($field === 'name' && trim($value) !== '') {
            VehicleType::whereKey($id)->update(['name' => trim($value)]);
        }

        if ($field === 'capacity') {
            VehicleType::whereKey($id)->update(['capacity' => max(1, (int) $value)]);
        }
    }

    public function deleteVehicle(int $id): void
    {
        Gate::authorize('write');
        // Movements keep working — the FK nulls out rather than cascading.
        VehicleType::whereKey($id)->delete();
    }

    // ── Services ────────────────────────────────────────────────

    public function addService(): void
    {
        Gate::authorize('write');
        $name = trim($this->newService);

        if ($name === '') {
            return;
        }

        if (TransportServiceType::whereRaw('lower(name) = ?', [mb_strtolower($name)])->exists()) {
            $this->addError('newService', 'That service type already exists.');

            return;
        }

        TransportServiceType::create([
            'name' => $name,
            'is_active' => true,
            'position' => (int) TransportServiceType::max('position') + 1,
        ]);

        $this->reset(['newService']);
    }

    public function toggleService(int $id): void
    {
        Gate::authorize('write');
        $s = TransportServiceType::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
    }

    public function updateService(int $id, string $value): void
    {
        Gate::authorize('write');

        if (trim($value) !== '') {
            TransportServiceType::whereKey($id)->update(['name' => trim($value)]);
        }
    }

    public function deleteService(int $id): void
    {
        Gate::authorize('write');
        TransportServiceType::whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.transport-settings', [
            'vehicles' => VehicleType::orderBy('position')->orderBy('id')->get(),
            'services' => TransportServiceType::orderBy('position')->orderBy('id')->get(),
        ]);
    }
}
