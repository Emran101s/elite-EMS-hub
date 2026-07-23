<?php

namespace App\Livewire;

use App\Models\Supplier;
use App\Models\TransportDriver;
use App\Models\TransportServiceType;
use App\Models\TransportVehicle;
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
#[Layout('components.layouts.app', ['title' => 'Transport Catalogue'])]
class TransportSettings extends Component
{
    public string $newVehicle = '';

    public int $newVehicleCapacity = 4;

    public string $newService = '';

    // ── new driver ──
    public string $newDriver = '';

    public string $newDriverPhone = '';

    public ?int $newDriverSupplier = null;

    // ── new vehicle in the fleet ──
    public string $newPlate = '';

    public string $newModel = '';

    public ?int $newFleetType = null;

    public ?int $newFleetSupplier = null;

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

    // ── Drivers ─────────────────────────────────────────────────
    // The people you hire. Kept here rather than per-event because the same
    // drivers come back job after job.

    public function addDriver(): void
    {
        Gate::authorize('write');
        $name = trim($this->newDriver);

        if ($name === '') {
            return;
        }

        TransportDriver::create([
            'name' => $name,
            'phone' => trim($this->newDriverPhone) ?: null,
            'supplier_id' => $this->newDriverSupplier ?: null,
            'is_active' => true,
        ]);

        $this->reset(['newDriver', 'newDriverPhone', 'newDriverSupplier']);
    }

    public function updateDriver(int $id, string $field, string $value): void
    {
        Gate::authorize('write');

        if (! in_array($field, ['name', 'phone', 'whatsapp', 'licence_no', 'languages', 'notes'], true)) {
            return;
        }

        if ($field === 'name' && trim($value) === '') {
            return;
        }

        TransportDriver::whereKey($id)->update([$field => trim($value) ?: null]);
    }

    public function setDriverSupplier(int $id, string $supplierId): void
    {
        Gate::authorize('write');
        TransportDriver::whereKey($id)->update(['supplier_id' => $supplierId ?: null]);
    }

    public function toggleDriver(int $id): void
    {
        Gate::authorize('write');
        $d = TransportDriver::findOrFail($id);
        $d->update(['is_active' => ! $d->is_active]);
    }

    public function deleteDriver(int $id): void
    {
        Gate::authorize('write');
        // Trips survive — the FK nulls out. An assignment is a plan, not the trip.
        TransportDriver::whereKey($id)->delete();
    }

    // ── Vehicles (specific cars) ────────────────────────────────

    public function addFleetVehicle(): void
    {
        Gate::authorize('write');
        $plate = trim($this->newPlate);
        $model = trim($this->newModel);

        if ($plate === '' && $model === '') {
            $this->addError('newPlate', 'Give it a plate or a model — something to recognise it by.');

            return;
        }

        TransportVehicle::create([
            'plate_no' => $plate ?: null,
            'model' => $model ?: null,
            'vehicle_type_id' => $this->newFleetType ?: null,
            'supplier_id' => $this->newFleetSupplier ?: null,
            'is_active' => true,
        ]);

        $this->reset(['newPlate', 'newModel', 'newFleetType', 'newFleetSupplier']);
    }

    public function updateFleetVehicle(int $id, string $field, string $value): void
    {
        Gate::authorize('write');

        if (! in_array($field, ['plate_no', 'model', 'colour', 'features', 'notes'], true)) {
            return;
        }

        TransportVehicle::whereKey($id)->update([$field => trim($value) ?: null]);
    }

    public function setFleetVehicleType(int $id, string $typeId): void
    {
        Gate::authorize('write');
        TransportVehicle::whereKey($id)->update(['vehicle_type_id' => $typeId ?: null]);
    }

    public function setFleetSupplier(int $id, string $supplierId): void
    {
        Gate::authorize('write');
        TransportVehicle::whereKey($id)->update(['supplier_id' => $supplierId ?: null]);
    }

    public function toggleFleetVehicle(int $id): void
    {
        Gate::authorize('write');
        $v = TransportVehicle::findOrFail($id);
        $v->update(['is_active' => ! $v->is_active]);
    }

    public function deleteFleetVehicle(int $id): void
    {
        Gate::authorize('write');
        TransportVehicle::whereKey($id)->delete();
    }

    public function render()
    {
        return view('livewire.transport-settings', [
            'vehicles' => VehicleType::orderBy('position')->orderBy('id')->get(),
            'services' => TransportServiceType::orderBy('position')->orderBy('id')->get(),
            'drivers' => TransportDriver::with('supplier')->orderBy('name')->get(),
            'fleet' => TransportVehicle::with(['vehicleType', 'supplier'])->orderBy('plate_no')->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
