<?php

namespace App\Livewire\Hub;

use App\Models\Event;
use App\Models\EventTransport;
use App\Models\TransportServiceType;
use App\Models\VehicleType;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Validate;
use Livewire\Component;

/**
 * A movement is: a service (Airport → Hotel), in a vehicle (Regular Van, max 7),
 * some number of times, at a time, for N passengers.
 *
 * The vehicle and service lists come from the company-wide catalogue in
 * Settings → Transport, so an operation only sees the handful it actually runs.
 */
class TransportationTab extends Component
{
    public Event $event;

    public bool $showForm = false;

    public ?int $editingId = null;

    /** Day filter — movements cluster hard around arrival and departure days. */
    public string $filterDay = '';

    /** Which movement's manifest is open. */
    public ?int $expandedId = null;

    /** @var array<int,string> new passenger name, keyed by movement id */
    public array $newPax = [];

    #[Validate('nullable|integer|exists:transport_service_types,id')]
    public ?int $service_type_id = null;

    #[Validate('nullable|integer|exists:vehicle_types,id')]
    public ?int $vehicle_type_id = null;

    #[Validate('required|integer|min:1|max:99')]
    public int $vehicles = 1;

    #[Validate('nullable|string|max:160')]
    public string $pickup_from = '';

    #[Validate('nullable|string|max:160')]
    public string $drop_to = '';

    #[Validate('nullable|string|max:120')]
    public string $provider = '';

    #[Validate('nullable|string|max:60')]
    public string $driver_contact = '';

    #[Validate('nullable|date')]
    public string $depart_at = '';

    #[Validate('nullable|date')]
    public string $arrive_at = '';

    #[Validate('nullable|string|max:40')]
    public string $flight_no = '';

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
        $this->reset(['editingId', 'pickup_from', 'drop_to', 'provider', 'driver_contact',
            'depart_at', 'arrive_at', 'flight_no', 'passengers', 'cost', 'notes']);
        $this->vehicles = 1;
        $this->status = 'planned';
        // Preselect the first active option of each — most movements are the common one.
        $this->service_type_id = TransportServiceType::active()->orderBy('position')->value('id');
        $this->vehicle_type_id = VehicleType::active()->orderBy('position')->value('id');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $t = $this->event->transport()->findOrFail($id);
        $this->editingId = $t->id;
        $this->service_type_id = $t->service_type_id;
        $this->vehicle_type_id = $t->vehicle_type_id;
        $this->vehicles = max(1, (int) $t->vehicles);
        $this->pickup_from = $t->pickup_from ?? '';
        $this->drop_to = $t->drop_to ?? '';
        $this->provider = $t->provider ?? '';
        $this->driver_contact = $t->driver_contact ?? '';
        $this->depart_at = $t->depart_at?->format('Y-m-d\TH:i') ?? '';
        $this->arrive_at = $t->arrive_at?->format('Y-m-d\TH:i') ?? '';
        $this->flight_no = $t->flight_no ?? '';
        $this->passengers = $t->passengers;
        $this->cost = $t->cost_cents ? (string) ($t->cost_cents / 100) : '';
        $this->status = $t->status;
        $this->notes = $t->notes ?? '';
        $this->showForm = true;
    }

    public function save(): void
    {
        Gate::authorize('write');
        $this->validate();

        $vehicle = $this->vehicle_type_id ? VehicleType::find($this->vehicle_type_id) : null;
        $service = $this->service_type_id ? TransportServiceType::find($this->service_type_id) : null;

        // `route` stays the human-readable one-liner the rest of the app already reads.
        $route = trim(($this->pickup_from ?: '—').' → '.($this->drop_to ?: '—'));

        $data = [
            'type' => 'shuttle',  // legacy column; the catalogue is the real classifier now
            'service_type_id' => $this->service_type_id,
            'vehicle_type_id' => $this->vehicle_type_id,
            'vehicles' => max(1, $this->vehicles),
            'route' => $service && ! $this->pickup_from && ! $this->drop_to ? $service->name : $route,
            'pickup_from' => $this->pickup_from ?: null,
            'drop_to' => $this->drop_to ?: null,
            'provider' => $this->provider ?: null,
            'driver_contact' => $this->driver_contact ?: null,
            'depart_at' => $this->depart_at ?: null,
            'arrive_at' => $this->arrive_at ?: null,
            'flight_no' => $this->flight_no ?: null,
            // Capacity is a snapshot of the vehicle at booking time, so later
            // edits to the catalogue don't silently rewrite past movements.
            'capacity' => $vehicle ? $vehicle->capacity * max(1, $this->vehicles) : null,
            'passengers' => $this->passengers ?: 0,
            'cost_cents' => (int) round((float) ($this->cost ?: 0) * 100),
            'status' => $this->status,
            'notes' => $this->notes ?: null,
        ];

        $this->editingId
            ? $this->event->transport()->findOrFail($this->editingId)->update($data)
            : $this->event->transport()->create($data);

        $this->showForm = false;
        session()->flash('status', 'Movement saved.');
    }

    /** Same run, another day — the most common way movements get created. */
    public function duplicate(int $id): void
    {
        Gate::authorize('write');
        $t = $this->event->transport()->findOrFail($id);
        $copy = $t->replicate(['created_at', 'updated_at']);
        $copy->status = 'planned';
        $copy->depart_at = $t->depart_at?->copy()->addDay();
        $copy->arrive_at = $t->arrive_at?->copy()->addDay();
        $copy->save();   // manifest deliberately not copied — a new run needs its own names
    }

    public function toggleExpand(int $id): void
    {
        $this->expandedId = $this->expandedId === $id ? null : $id;
    }

    // ── Manifest ────────────────────────────────────────────────

    /** Puts a named passenger in the next free seat. */
    public function addPassenger(int $movementId): void
    {
        Gate::authorize('write');
        $m = $this->event->transport()->findOrFail($movementId);
        $name = trim($this->newPax[$movementId] ?? '');

        if ($name === '') {
            return;
        }

        if ($m->seats() > 0 && $m->manifest()->count() >= $m->seats()) {
            $this->addError('newPax.'.$movementId,
                'No seats left — add another vehicle or move this passenger to a second run.');

            return;
        }

        // Seed from the run: most passengers are on the flight it was booked to
        // meet. Any of these can be overwritten per seat afterwards.
        $m->manifest()->create([
            'name' => $name,
            'flight_no' => $m->flight_no,
            'arrival_on' => $m->arrive_at?->toDateString() ?? $m->depart_at?->toDateString(),
            'arrival_time' => $m->arrive_at?->format('H:i'),
            'pickup_point' => $m->pickup_from,
            'position' => (int) $m->manifest()->max('position') + 1,
        ]);

        $this->newPax[$movementId] = '';
    }

    public function updatePassenger(int $id, string $field, string $value): void
    {
        Gate::authorize('write');

        // Email deliberately absent — a driver needs a phone, not an inbox.
        if (! in_array($field, ['name', 'airline', 'phone', 'flight_no', 'arrival_on',
            'arrival_time', 'pickup_point', 'notes'], true)) {
            return;
        }

        // Date and time inputs clear on a malformed value rather than crashing.
        if ($field === 'arrival_on') {
            $value = $value !== '' && \Carbon\Carbon::hasFormat($value, 'Y-m-d') ? $value : '';
        }

        if ($field === 'arrival_time') {
            $value = preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
        }

        \App\Models\EventTransportPassenger::whereKey($id)
            ->whereIn('transport_id', $this->event->transport()->select('id'))
            ->update([$field => $value ?: null]);
    }

    public function deletePassenger(int $id): void
    {
        Gate::authorize('write');
        \App\Models\EventTransportPassenger::whereKey($id)
            ->whereIn('transport_id', $this->event->transport()->select('id'))
            ->delete();
    }

    public function delete(int $id): void
    {
        Gate::authorize('write');
        $this->event->transport()->whereKey($id)->delete();
    }

    public function setDay(string $day): void
    {
        $this->filterDay = $this->filterDay === $day ? '' : $day;
    }

    /**
     * How many of each vehicle the operation has committed — the number you
     * give the transport supplier: "3 sedans, 2 vans, 1 coach".
     *
     * @param  \Illuminate\Support\Collection<int,EventTransport>  $movements
     * @return \Illuminate\Support\Collection<string,array{name:string,capacity:int,vehicles:int,runs:int,pax:int}>
     */
    public function fleet($movements)
    {
        return $movements
            ->filter(fn (EventTransport $m) => $m->vehicleType)
            ->groupBy(fn (EventTransport $m) => $m->vehicleType->name)
            ->map(fn ($group, $name) => [
                'name' => $name,
                'capacity' => $group->first()->vehicleType->capacity,
                'vehicles' => $group->sum(fn (EventTransport $m) => $m->vehicleCount()),
                'runs' => $group->count(),
                'pax' => $group->sum(fn (EventTransport $m) => $m->paxCount()),
            ])
            ->sortByDesc('vehicles');
    }

    public function render()
    {
        $all = $this->event->transport()
            ->with(['vehicleType', 'serviceType', 'manifest'])
            ->get()
            ->sortBy([fn (EventTransport $a, EventTransport $b) => [$a->chronoKey(), $a->id] <=> [$b->chronoKey(), $b->id]])
            ->values();

        // Day tabs, built from the movements themselves.
        $days = $all->filter(fn (EventTransport $m) => $m->depart_at)
            ->groupBy(fn (EventTransport $m) => $m->depart_at->format('Y-m-d'))
            ->map->count();

        $movements = $this->filterDay
            ? $all->filter(fn (EventTransport $m) => $m->depart_at?->format('Y-m-d') === $this->filterDay)
            : $all;

        return view('livewire.hub.transportation-tab', [
            'movements' => $movements->groupBy(fn (EventTransport $m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'days' => $days,
            'total' => $all->count(),
            'vehicleTypes' => VehicleType::active()->orderBy('position')->get(),
            'serviceTypes' => TransportServiceType::active()->orderBy('position')->get(),
            'seatsTotal' => $all->sum(fn (EventTransport $m) => $m->seats()),
            'paxTotal' => $all->sum(fn (EventTransport $m) => $m->paxCount()),
            'namedTotal' => $all->sum(fn (EventTransport $m) => $m->manifest->count()),
            'fleet' => $this->fleet($all),
            'overbooked' => $all->filter(fn (EventTransport $m) => $m->isOverbooked())->count(),
            'costTotal' => $all->sum('cost_cents'),
        ]);
    }
}
