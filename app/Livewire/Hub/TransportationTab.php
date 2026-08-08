<?php

namespace App\Livewire\Hub;

use App\Livewire\Concerns\RoutesCostsToBudget;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\Supplier;
use App\Models\TransportDriver;
use App\Models\TransportServiceType;
use App\Models\TransportVehicle;
use App\Models\VehicleType;
use App\Support\Taxonomy;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date;

/**
 * A movement is: a service (Airport → Hotel), in a vehicle (Regular Van, max 7),
 * some number of times, at a time, for N passengers.
 *
 * The vehicle and service lists come from the company-wide catalogue in
 * Settings → Transport, so an operation only sees the handful it actually runs.
 */
class TransportationTab extends Component
{
    use RoutesCostsToBudget, WithFileUploads;

    public Event $event;

    public bool $showForm = false;

    // ── Manifest import (Excel / CSV into one movement) ──
    public $importFile = null;

    public ?int $importMoveId = null;

    public string $importMsg = '';

    // ── Movement import (one sheet → the whole transport plan) ──
    public $planFile = null;

    public bool $showPlanImport = false;

    public string $planMsg = '';

    public ?int $editingId = null;

    /** Day filter — movements cluster hard around arrival and departure days. */
    public string $filterDay = '';

    /**
     * Leg filter: '' for everything, or arrival / departure / other. Drives both
     * the tabs and what the manifest PDF exports.
     */
    public string $filterLeg = '';

    /** Car number typed into the bulk bar's "assign to car" box. */
    public string $assignRef = '';

    /** Which movement's manifest is open. */
    public ?int $expandedId = null;

    /** @var array<int,string> new passenger name, keyed by movement id */
    public array $newPax = [];

    /** The movement's category — the same split the flight list uses. */
    #[Validate('required|in:arrival,departure,other')]
    public string $leg = 'arrival';

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

    #[Validate('nullable|integer|exists:transport_drivers,id')]
    public ?int $driver_id = null;

    #[Validate('nullable|integer|exists:transport_vehicles,id')]
    public ?int $vehicle_id = null;

    #[Validate('nullable|integer|exists:suppliers,id')]
    public ?int $supplier_id = null;

    /** Marks a run as priority even when no VIP is named on it yet. */
    public bool $is_vip = false;

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

    #[Validate('required|in:planned,ordered,confirmed,in_progress,completed,issue,cancelled')]
    public string $status = 'planned';

    #[Validate('nullable|string|max:400')]
    public string $notes = '';

    public function newItem(): void
    {
        $this->reset(['editingId', 'pickup_from', 'drop_to', 'provider', 'driver_contact',
            'depart_at', 'arrive_at', 'flight_no', 'passengers', 'cost', 'notes',
            'driver_id', 'vehicle_id', 'supplier_id', 'is_vip']);
        $this->vehicles = 1;
        $this->status = 'planned';
        // Opens on the leg you're already looking at — you're usually building
        // out the tab you filtered to.
        $this->leg = $this->filterLeg ?: 'arrival';
        // Preselect the first active option of each — most movements are the common one.
        $this->service_type_id = TransportServiceType::active()->orderBy('position')->value('id');
        $this->vehicle_type_id = VehicleType::active()->orderBy('position')->value('id');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $t = $this->event->transport()->findOrFail($id);
        $this->editingId = $t->id;
        $this->leg = $t->leg ?: 'other';
        $this->service_type_id = $t->service_type_id;
        $this->vehicle_type_id = $t->vehicle_type_id;
        $this->vehicles = max(1, (int) $t->vehicles);
        $this->pickup_from = $t->pickup_from ?? '';
        $this->drop_to = $t->drop_to ?? '';
        $this->driver_id = $t->driver_id;
        $this->vehicle_id = $t->vehicle_id;
        $this->supplier_id = $t->supplier_id;
        $this->is_vip = (bool) $t->is_vip;
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
            'type' => 'shuttle',  // legacy column; `leg` is the real classifier now
            'leg' => $this->leg,
            'service_type_id' => $this->service_type_id,
            'vehicle_type_id' => $this->vehicle_type_id,
            'vehicles' => max(1, $this->vehicles),
            'route' => $service && ! $this->pickup_from && ! $this->drop_to ? $service->name : $route,
            'pickup_from' => $this->pickup_from ?: null,
            'drop_to' => $this->drop_to ?: null,
            'driver_id' => $this->driver_id,
            'vehicle_id' => $this->vehicle_id,
            'supplier_id' => $this->supplier_id,
            'is_vip' => $this->is_vip,
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
            'event_id' => $this->event->id,
            'direction' => $this->directionOf('', $m->pickup_from ?? '', $m->drop_to ?? ''),
            'name' => $name,
            'flight_no' => $m->flight_no,
            'arrival_on' => $m->arrive_at?->toDateString() ?? $m->depart_at?->toDateString(),
            'arrival_time' => $m->arrive_at?->format('H:i'),
            'pickup_point' => $m->pickup_from,
            'position' => (int) $m->manifest()->max('position') + 1,
        ]);

        $m->assignVehicles();
        $this->newPax[$movementId] = '';
    }

    // ── Import a manifest (Excel / CSV) into one movement ───────

    private function fieldForHeader(string $header): ?string
    {
        $n = preg_replace('/[^a-z0-9]/', '', strtolower($header));

        return match (true) {
            in_array($n, ['name', 'passenger', 'guest', 'fullname', 'delegate', 'pax'], true) => 'name',
            in_array($n, ['airline', 'carrier', 'flightairline'], true) => 'airline',
            in_array($n, ['flightno', 'flight', 'flightnumber', 'flt', 'flightno'], true) => 'flight_no',
            in_array($n, ['arrivaldate', 'arrivalon', 'arrival', 'arrive', 'date', 'checkin'], true) => 'arrival_on',
            in_array($n, ['arrivaltime', 'eta', 'time', 'timein'], true) => 'arrival_time',
            in_array($n, ['phone', 'mobile', 'tel', 'telephone', 'contact', 'phonenumber'], true) => 'phone',
            in_array($n, ['email', 'mail', 'emailaddress'], true) => 'email',
            in_array($n, ['pickup', 'pickuppoint', 'pickupfrom', 'from', 'location', 'meetingpoint'], true) => 'pickup_point',
            in_array($n, ['notes', 'note', 'remarks', 'remark', 'comment', 'comments'], true) => 'notes',
            default => null,
        };
    }

    private function parseSheetDate($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return is_numeric($v)
                ? Date::excelToDateTimeObject((float) $v)->format('Y-m-d')
                : Carbon::parse($v)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseSheetTime($v): ?string
    {
        $v = trim((string) $v);
        if ($v === '') {
            return null;
        }
        try {
            return is_numeric($v)
                ? Date::excelToDateTimeObject((float) $v)->format('H:i')
                : Carbon::parse($v)->format('H:i');
        } catch (\Throwable) {
            return null;
        }
    }

    /* ── Import a whole transport plan: one sheet, every movement ── */

    /**
     * Read whatever the sheet calls someone — "V.I.P.", "Key Note Speaker",
     * "Press" — and land on one of our categories. Anything unrecognised is a
     * delegate, which is the safe default: it never wrongly promotes a trip.
     */
    private function categoryOf(string $raw): string
    {
        $n = preg_replace('/[^a-z]/', '', strtolower(trim($raw)));

        return match (true) {
            $n === '' => 'delegate',
            str_contains($n, 'vip'), str_contains($n, 'guestofhonour'), str_contains($n, 'dignitary') => 'vip',
            str_contains($n, 'speaker'), str_contains($n, 'panel'), str_contains($n, 'keynote'), str_contains($n, 'moderator') => 'speaker',
            str_contains($n, 'sponsor'), str_contains($n, 'partner'), str_contains($n, 'exhibitor') => 'sponsor',
            str_contains($n, 'staff'), str_contains($n, 'crew'), str_contains($n, 'team'), str_contains($n, 'organiser'), str_contains($n, 'organizer') => 'staff',
            str_contains($n, 'media'), str_contains($n, 'press'), str_contains($n, 'journalist'), str_contains($n, 'photographer') => 'media',
            default => 'delegate',
        };
    }

    private function fieldForPlanHeader(string $header): ?string
    {
        $n = preg_replace('/[^a-z0-9]/', '', strtolower($header));

        return match (true) {
            // ── the slim, passenger-centric sheet ──
            in_array($n, ['name', 'passenger', 'passengername', 'guest', 'paxname', 'delegate'], true) => 'passenger_name',
            in_array($n, ['direction', 'leg', 'way', 'inout', 'triptype'], true) => 'direction',
            in_array($n, ['airline', 'carrier'], true) => 'airline',
            in_array($n, ['flightno', 'flight', 'flightnumber', 'flt'], true) => 'flight_no',
            in_array($n, ['date', 'flightdate', 'traveldate', 'departdate', 'departuredate', 'arrivaldate', 'movementdate'], true) => 'date',
            // The flight's own time — landing on arrival, wheels-up on departure.
            in_array($n, ['flighttime', 'arrivaltime', 'departuretime', 'eta', 'etd'], true) => 'flight_time',
            // When the vehicle actually collects — the one that drives the movement.
            in_array($n, ['pickuptime', 'pickup', 'collect', 'collectiontime', 'departtime'], true) => 'pickup_time',
            in_array($n, ['from', 'pickupfrom', 'origin', 'pickuppoint'], true) => 'from',
            in_array($n, ['to', 'dropto', 'drop', 'destination', 'dropoff'], true) => 'to',
            in_array($n, ['phone', 'mobile', 'contact', 'phonenumber'], true) => 'phone',
            in_array($n, ['category', 'type', 'guesttype', 'paxtype', 'role'], true) => 'category',
            in_array($n, ['hotel', 'accommodation', 'stay'], true) => 'hotel',
            in_array($n, ['notes', 'note', 'remarks', 'remark', 'comment'], true) => 'notes',

            // ── optional movement detail, only if you include it ──
            in_array($n, ['route', 'trip', 'movement', 'run'], true) => 'route',
            in_array($n, ['service', 'servicetype'], true) => 'service',
            in_array($n, ['vehicletype', 'vehicle', 'car', 'bus', 'cartype'], true) => 'vehicle_type',
            in_array($n, ['vehicles', 'vehiclecount', 'novehicles', 'units', 'qty', 'quantity'], true) => 'vehicles',
            in_array($n, ['passengers', 'pax', 'headcount', 'seats'], true) => 'passengers',
            in_array($n, ['provider', 'supplier', 'vendor', 'company'], true) => 'provider',
            in_array($n, ['drivercontact', 'driver', 'driverphone'], true) => 'driver_contact',
            in_array($n, ['status'], true) => 'status',
            in_array($n, ['cost', 'price', 'amount', 'total'], true) => 'cost',
            default => null,
        };
    }

    /** "arrival" / "in" / "arr" → arrival; "departure" / "out" / "dep" → departure. */
    private function directionOf(string $raw, string $from, string $to): string
    {
        $n = strtolower(trim($raw));
        if (str_starts_with($n, 'arr') || in_array($n, ['in', 'inbound', 'pickup'], true)) {
            return 'arrival';
        }
        if (str_starts_with($n, 'dep') || in_array($n, ['out', 'outbound', 'dropoff'], true)) {
            return 'departure';
        }

        // No column? Infer from where they're going.
        $airportish = fn ($s) => (bool) preg_match('/airport|qaia|terminal|apt\b/i', $s);

        return $airportish($to) ? 'departure' : 'arrival';
    }

    public function importPlan(): void
    {
        Gate::authorize('write');
        $this->validate(['planFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);

        try {
            $rows = IOFactory::load($this->planFile->getRealPath())
                ->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable) {
            $this->addError('planFile', 'Could not read that file. Save it as .xlsx or .csv and try again.');

            return;
        }

        $headerIndex = null;
        foreach ($rows as $i => $row) {
            if (collect($row)->contains(fn ($c) => trim((string) $c) !== '')) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) {
            $this->addError('planFile', 'The sheet looks empty.');

            return;
        }

        $map = [];
        foreach ($rows[$headerIndex] as $col => $h) {
            if ($field = $this->fieldForPlanHeader((string) $h)) {
                $map[$col] = $field;
            }
        }
        if (! array_intersect(['passenger_name', 'route', 'from', 'to'], $map)) {
            $this->addError('planFile', 'No “Name” column found. Add a Name column (or a Route) and re-upload.');

            return;
        }

        $vehicleTypes = VehicleType::get()->keyBy(fn ($v) => mb_strtolower($v->name));
        $guests = 0;
        $skipped = 0;
        $position = (int) $this->event->transferGuests()->max('position');

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $d = [];
            foreach ($map as $col => $field) {
                $d[$field] = trim((string) ($row[$col] ?? ''));
            }

            $name = $d['passenger_name'] ?? '';
            if ($name === '') {
                continue;   // the sheet is a guest list — a row without a name is noise
            }

            $direction = $this->directionOf($d['direction'] ?? '', $d['from'] ?? '', $d['to'] ?? '');
            $isDeparture = $direction === 'departure';

            // Fill in the obvious ends of the journey when the sheet leaves them out.
            $from = ($d['from'] ?? '') ?: ($isDeparture ? 'Hotel' : 'Airport');
            $to = ($d['to'] ?? '') ?: ($isDeparture ? 'Airport' : 'Hotel');

            $date = $this->parseSheetDate($d['date'] ?? '');
            $flightTime = $this->parseSheetTime($d['flight_time'] ?? '');
            // On arrival the pickup IS the landing; on departure it is set hours before.
            $pickupTime = $this->parseSheetTime($d['pickup_time'] ?? '') ?? $flightTime;

            // Re-importing a corrected sheet should fix the row, not duplicate the guest.
            $existing = $this->event->transferGuests()
                ->whereRaw('lower(name) = ?', [mb_strtolower($name)])
                ->where('direction', $direction)
                ->when($date, fn ($q) => $q->whereDate('arrival_on', $date))
                ->first();

            $attrs = [
                'event_id' => $this->event->id,
                'name' => mb_substr($name, 0, 160),
                'direction' => $direction,
                'airline' => ($d['airline'] ?? '') ?: null,
                'flight_no' => ($d['flight_no'] ?? '') ?: null,
                'arrival_on' => $date,
                'arrival_time' => $flightTime,
                'pickup_time' => $pickupTime,
                'pickup_point' => $from,
                'drop_point' => $to,
                'phone' => ($d['phone'] ?? '') ?: null,
                'category' => $this->categoryOf($d['category'] ?? ''),
                'hotel' => ($d['hotel'] ?? '') ?: null,
                'notes' => ($d['notes'] ?? '') ?: null,
            ];

            if ($existing) {
                $existing->update($attrs);   // keeps whatever vehicle it is already on
                $skipped++;
            } else {
                $this->event->transferGuests()->create($attrs + ['position' => ++$position]);
                $guests++;
            }

            if ($guests >= 2000) {
                break;
            }
        }

        $this->reset(['planFile', 'showPlanImport']);
        $this->planMsg = ($guests + $skipped) === 0
            ? 'No guests were found in that file.'
            : trim($guests.' '.Str::plural('guest', $guests).' imported'
                .($skipped ? ', '.$skipped.' updated' : '')
                .' — assign them to vehicles below.');
    }

    /** "2026-06-14" + "14:30" → a Carbon datetime (either part may be missing). */
    private function combine(string $date, string $time): ?Carbon
    {
        $d = $this->parseSheetDate($date);
        $t = $this->parseSheetTime($time);
        if (! $d) {
            return null;
        }

        try {
            return Carbon::parse($d.' '.($t ?: '00:00'));
        } catch (\Throwable) {
            return null;
        }
    }

    public function openImport(int $movementId): void
    {
        $this->reset(['importFile', 'importMsg']);
        $this->resetErrorBag('importFile');
        $this->importMoveId = $movementId;
        $this->expandedId = $movementId;
    }

    public function closeImport(): void
    {
        $this->reset(['importFile', 'importMoveId', 'importMsg']);
    }

    public function importPassengers(): void
    {
        Gate::authorize('write');
        $this->validate(['importFile' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']]);

        $m = $this->event->transport()->findOrFail($this->importMoveId);

        try {
            $rows = IOFactory::load($this->importFile->getRealPath())
                ->getActiveSheet()->toArray(null, true, true, false);
        } catch (\Throwable) {
            $this->addError('importFile', 'Could not read that file. Save it as .xlsx or .csv and try again.');

            return;
        }

        $headerIndex = null;
        foreach ($rows as $i => $row) {
            if (collect($row)->contains(fn ($c) => trim((string) $c) !== '')) {
                $headerIndex = $i;
                break;
            }
        }
        if ($headerIndex === null) {
            $this->addError('importFile', 'The sheet looks empty.');

            return;
        }

        $map = [];
        foreach ($rows[$headerIndex] as $col => $h) {
            if ($field = $this->fieldForHeader((string) $h)) {
                $map[$col] = $field;
            }
        }
        if (! in_array('name', $map, true)) {
            $this->addError('importFile', 'No “Name” column found. Add a column headed Name (or Passenger) and re-upload.');

            return;
        }

        $imported = 0;
        $position = (int) $m->manifest()->max('position');

        foreach (array_slice($rows, $headerIndex + 1) as $row) {
            $d = [];
            foreach ($map as $col => $field) {
                $d[$field] = trim((string) ($row[$col] ?? ''));
            }
            $name = $d['name'] ?? '';
            if ($name === '') {
                continue;
            }

            // Blank cells fall back to the run this manifest belongs to.
            $m->manifest()->create([
                'event_id' => $this->event->id,
                'direction' => $this->directionOf('', $m->pickup_from ?? '', $m->drop_to ?? ''),
                'name' => mb_substr($name, 0, 160),
                'airline' => ($d['airline'] ?? '') ?: null,
                'flight_no' => ($d['flight_no'] ?? '') ?: $m->flight_no,
                'arrival_on' => $this->parseSheetDate($d['arrival_on'] ?? '') ?? $m->arrive_at?->toDateString() ?? $m->depart_at?->toDateString(),
                'arrival_time' => $this->parseSheetTime($d['arrival_time'] ?? '') ?? $m->arrive_at?->format('H:i'),
                'phone' => ($d['phone'] ?? '') ?: null,
                'email' => ($d['email'] ?? '') ?: null,
                'pickup_point' => ($d['pickup_point'] ?? '') ?: $m->pickup_from,
                'notes' => ($d['notes'] ?? '') ?: null,
                'position' => ++$position,
            ]);

            if (++$imported >= 2000) {
                break;
            }
        }

        $m->assignVehicles();
        $this->reset(['importFile', 'importMoveId']);
        $this->expandedId = $m->id;
        $this->importMsg = $imported === 0
            ? 'No rows with a name were found in that file.'
            : $imported.' '.Str::plural('passenger', $imported).' added to the manifest.';
    }

    /* ── The guest pool: everyone needing a transfer, assigned or not ── */

    /** Which leg the pool is showing. */
    public string $guestLeg = 'arrival';

    public bool $guestsOnlyUnassigned = true;

    /** @var array<int,int> guest ids ticked for a bulk assignment */
    public array $pickedGuests = [];

    public function setGuestLeg(string $leg): void
    {
        $this->guestLeg = $leg === 'departure' ? 'departure' : 'arrival';
        $this->pickedGuests = [];
    }

    public function toggleGuest(int $id): void
    {
        $this->pickedGuests = in_array($id, $this->pickedGuests, true)
            ? array_values(array_diff($this->pickedGuests, [$id]))
            : [...$this->pickedGuests, $id];
    }

    public function toggleGuestPage(array $ids): void
    {
        $ids = array_map('intval', $ids);
        $allOn = $ids !== [] && ! array_diff($ids, $this->pickedGuests);
        $this->pickedGuests = $allOn
            ? array_values(array_diff($this->pickedGuests, $ids))
            : array_values(array_unique([...$this->pickedGuests, ...$ids]));
    }

    /** "Everyone on RJ 100" — the bulk move that makes 200 guests survivable. */
    public function pickFlight(string $flightNo): void
    {
        $this->pickedGuests = array_values(array_unique([
            ...$this->pickedGuests,
            ...$this->guestPool()->where('flight_no', $flightNo)->pluck('id')->all(),
        ]));
    }

    public function clearPicked(): void
    {
        $this->pickedGuests = [];
    }

    /**
     * Assign by the number you'd say out loud — type 3, everyone ticked goes on
     * car 3. Faster than hunting a route in a dropdown once a plan gets long.
     */
    public function assignToNumber(): void
    {
        Gate::authorize('write');

        $ref = (int) ltrim(trim($this->assignRef), '#');
        $this->assignRef = '';

        if ($ref < 1) {
            return;
        }

        $m = $this->event->transport()->where('ref_no', $ref)->first();

        if (! $m) {
            $this->planMsg = 'There is no car #'.$ref.' on this event.';

            return;
        }

        // The leg guard the dropdown enforces has to hold here too.
        if (! in_array($m->leg, [$this->guestLeg, 'other'], true)) {
            $this->planMsg = 'Car #'.$ref.' is a '.strtolower($m->legLabel())
                .' movement — these guests are '.$this->guestLeg.'s.';

            return;
        }

        $this->assignPicked($m->id);
    }

    /** Put the ticked guests on a vehicle, then re-fill the vans in order. */
    public function assignPicked(int $movementId): void
    {
        Gate::authorize('write');
        if ($this->pickedGuests === []) {
            return;
        }

        $m = $this->event->transport()->findOrFail($movementId);
        $position = (int) $m->manifest()->max('position');

        $this->event->transferGuests()->whereIn('id', $this->pickedGuests)->get()
            ->each(function ($g) use ($m, &$position) {
                $g->update(['transport_id' => $m->id, 'position' => ++$position]);
            });

        $m->assignVehicles();
        $n = count($this->pickedGuests);
        $this->pickedGuests = [];
        $this->expandedId = $m->id;
        $this->planMsg = $n.' '.Str::plural('guest', $n).' assigned to car '.$m->refLabel().' — '.$m->route.'.';
    }

    /**
     * Drop target for drag-and-drop. Dragging a guest that is part of the
     * current selection moves the whole selection — dragging a loose one moves
     * just that guest.
     */
    public function dropGuest(int $guestId, int $movementId): void
    {
        Gate::authorize('write');

        if (in_array($guestId, $this->pickedGuests, true)) {
            $this->assignPicked($movementId);

            return;
        }

        $m = $this->event->transport()->findOrFail($movementId);
        $this->event->transferGuests()->whereKey($guestId)->update([
            'transport_id' => $m->id,
            'position' => (int) $m->manifest()->max('position') + 1,
        ]);
        $m->assignVehicles();
    }

    /**
     * Build the obvious runs for whoever is still unplaced: everyone leaving the
     * same place at the same time goes on one vehicle. A starting point you can
     * then adjust — never a substitute for the planner's judgement.
     */
    public function suggestGrouping(): void
    {
        Gate::authorize('write');

        $pool = $this->event->transferGuests()
            ->where('direction', $this->guestLeg)
            ->whereNull('transport_id')
            ->flightOrder()->get();

        if ($pool->isEmpty()) {
            $this->planMsg = 'Nothing left to group on this leg.';

            return;
        }

        $fleet = VehicleType::active()->where('capacity', '>', 0)->orderBy('capacity')->get();
        $service = TransportServiceType::active()->orderBy('position')->value('id');
        $made = 0;

        // The smallest vehicle that takes the whole group in one go; if nothing is
        // big enough, the largest — and the capacity split handles the rest.
        $pickVehicle = fn (int $heads) => $fleet->firstWhere('capacity', '>=', $heads) ?? $fleet->last();

        $pool->groupBy(function ($g) {
            return ($g->pickup_point ?: '—').'|'.($g->drop_point ?: '—').'|'
                .($g->arrival_on?->toDateString() ?? '').' '.($g->pickup_time ?: $g->arrival_time ?: '');
        })->each(function ($riders) use ($pickVehicle, $service, &$made) {
            $first = $riders->first();
            $vehicle = $pickVehicle($riders->count());
            $when = $first->arrival_on && ($first->pickup_time ?: $first->arrival_time)
                ? Carbon::parse($first->arrival_on->toDateString().' '.($first->pickup_time ?: $first->arrival_time))
                : null;

            $m = $this->event->transport()->create([
                'route' => trim(($first->pickup_point ?: '—').' → '.($first->drop_point ?: '—')),
                'type' => 'van',
                // The run inherits the leg of the guests it was built from.
                'leg' => $this->guestLeg,
                'vehicle_type_id' => $vehicle?->id,
                'service_type_id' => $service,
                'vehicles' => 1,
                'pickup_from' => $first->pickup_point,
                'drop_to' => $first->drop_point,
                'flight_no' => $riders->pluck('flight_no')->filter()->unique()->count() === 1 ? $first->flight_no : null,
                'depart_at' => $when,
                'arrive_at' => $first->arrival_on && $first->arrival_time
                    ? Carbon::parse($first->arrival_on->toDateString().' '.$first->arrival_time)
                    : null,
                'capacity' => $vehicle?->capacity,
                'status' => 'planned',
                'passengers' => $riders->count(),
            ]);

            $position = 0;
            $riders->each(fn ($g) => $g->update(['transport_id' => $m->id, 'position' => ++$position]));
            $m->assignVehicles();
            $made++;
        });

        $this->pickedGuests = [];
        $this->planMsg = $made.' '.Str::plural('run', $made)
            .' suggested from '.$pool->count().' '.Str::plural('guest', $pool->count())
            .' — adjust the vehicles and times as needed.';
    }

    /**
     * Move one guest straight from whichever vehicle they're on to another —
     * or back to the pool with an empty value. Both ends get re-filled.
     */
    public function moveGuest(int $id, string $movementId): void
    {
        Gate::authorize('write');

        $guest = $this->event->transferGuests()->findOrFail($id);
        $was = $guest->transport_id;
        $to = $movementId === '' ? null : $this->event->transport()->findOrFail((int) $movementId);

        if ($was === $to?->id) {
            return;
        }

        $guest->update([
            'transport_id' => $to?->id,
            'vehicle_no' => null,
            'position' => $to ? (int) $to->manifest()->max('position') + 1 : $guest->position,
        ]);

        $to?->assignVehicles();
        $was && $this->event->transport()->find($was)?->assignVehicles();

        $this->planMsg = $to
            ? $guest->name.' moved to '.$to->route.'.'
            : $guest->name.' returned to the pool.';
    }

    /** Send them back to the pool, and close the gap they leave in the vans. */
    public function unassignGuest(int $id): void
    {
        Gate::authorize('write');
        $guest = $this->event->transferGuests()->find($id);
        $was = $guest?->transport_id;
        $guest?->update(['transport_id' => null, 'vehicle_no' => null]);
        $was && $this->event->transport()->find($was)?->assignVehicles();
    }

    public function unassignPicked(): void
    {
        Gate::authorize('write');
        $affected = $this->event->transferGuests()->whereIn('id', $this->pickedGuests)
            ->whereNotNull('transport_id')->pluck('transport_id')->unique();

        $this->event->transferGuests()->whereIn('id', $this->pickedGuests)
            ->update(['transport_id' => null, 'vehicle_no' => null]);

        $this->event->transport()->whereIn('id', $affected)->get()
            ->each(fn (EventTransport $m) => $m->assignVehicles());

        $this->pickedGuests = [];
    }

    /** Remove the ticked guests from the event for good. */
    public function deletePicked(): void
    {
        Gate::authorize('write');
        if ($this->pickedGuests === []) {
            return;
        }

        $affected = $this->event->transferGuests()->whereIn('id', $this->pickedGuests)
            ->whereNotNull('transport_id')->pluck('transport_id')->unique();

        $n = $this->event->transferGuests()->whereIn('id', $this->pickedGuests)->delete();

        $this->event->transport()->whereIn('id', $affected)->get()
            ->each(fn (EventTransport $m) => $m->assignVehicles());

        $this->pickedGuests = [];
        $this->planMsg = $n.' '.Str::plural('guest', $n).' deleted.';
    }

    /** Wipe the guest list — every leg, assigned or not. The vehicles stay. */
    /**
     * Pull registered attendees into the transfer pool — no retyping. Each pool
     * guest remembers its attendee (attendee_id), so pulling again only brings
     * the people who are new since last time. Cancelled registrations stay out.
     */
    public function pullAttendees(): void
    {
        Gate::authorize('write');

        $linked = $this->event->transferGuests()->whereNotNull('attendee_id')->pluck('attendee_id');
        $eligible = $this->event->attendees()
            ->where('status', '!=', 'cancelled')
            ->whereNotIn('id', $linked)
            ->orderBy('name')
            ->get();

        foreach ($eligible as $attendee) {
            $this->event->transferGuests()->create([
                'attendee_id' => $attendee->id,
                'name' => $attendee->name,
                'category' => $attendee->vip ? 'vip' : 'delegate',
                'direction' => 'arrival',
                'phone' => $attendee->phone,
                'email' => $attendee->email,
                'notes' => collect([$attendee->organization, $attendee->job_title])->filter()->implode(' — ') ?: null,
            ]);
        }

        $this->planMsg = $eligible->isEmpty()
            ? 'Every attendee is already in the transport list — nothing new to pull.'
            : $eligible->count().' '.Str::plural('attendee', $eligible->count()).' pulled into the pool as arrivals — set flights and place them on vehicles.';
    }

    public function deleteAllGuests(): void
    {
        Gate::authorize('write');

        $n = $this->event->transferGuests()->delete();
        $this->event->transport()->get()->each(fn (EventTransport $m) => $m->assignVehicles());

        $this->pickedGuests = [];
        $this->planMsg = $n === 0
            ? 'There were no guests to delete.'
            : 'All '.$n.' guests deleted — import the sheet again to start over.';
    }

    /**
     * Clear the whole vehicle plan. The guests are the imported source data, so
     * they are never deleted with the vehicles — they go back to the pool.
     */
    public function deleteAllMovements(): void
    {
        Gate::authorize('write');

        $n = $this->event->transport()->count();
        $returned = $this->event->transferGuests()->whereNotNull('transport_id')
            ->update(['transport_id' => null, 'vehicle_no' => null]);
        $this->event->transport()->delete();

        $this->expandedId = null;
        $this->planMsg = $n === 0
            ? 'There were no vehicles to delete.'
            : $n.' '.Str::plural('vehicle', $n).' deleted'
                .($returned ? ' — '.$returned.' '.Str::plural('guest', $returned).' returned to the pool.' : '.');
    }

    /** The pool for the leg on screen, in flight order. */
    private function guestPool()
    {
        return $this->event->transferGuests()
            ->where('direction', $this->guestLeg)
            ->when($this->guestsOnlyUnassigned, fn ($q) => $q->whereNull('transport_id'))
            ->flightOrder()
            ->get();
    }

    /** Re-fill the vehicles in order — 7 in the first van, the rest in the next. */
    public function autoAssign(int $movementId): void
    {
        Gate::authorize('write');
        $this->event->transport()->findOrFail($movementId)->assignVehicles();
    }

    public function updatePassenger(int $id, string $field, string $value): void
    {
        Gate::authorize('write');

        // Email deliberately absent — a driver needs a phone, not an inbox.
        if (! in_array($field, ['name', 'airline', 'phone', 'flight_no', 'arrival_on', 'arrival_time',
            'pickup_point', 'drop_point', 'pickup_time', 'notes', 'vehicle_no',
            'category', 'hotel', 'luggage_note', 'protocol_note'], true)) {
            return;
        }

        // An unknown category would quietly drop someone out of every filter.
        if ($field === 'category' && ! isset(Taxonomy::options('passenger_category')[$value])) {
            return;
        }

        if ($field === 'pickup_time') {
            $value = preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
        }

        // Date and time inputs clear on a malformed value rather than crashing.
        if ($field === 'arrival_on') {
            $value = $value !== '' && Carbon::hasFormat($value, 'Y-m-d') ? $value : '';
        }

        if ($field === 'arrival_time') {
            $value = preg_match('/^\d{2}:\d{2}$/', $value) ? $value : '';
        }

        // Scoped by event, not by vehicle — pool guests aren't on one yet.
        $this->event->transferGuests()->whereKey($id)->update([$field => $value ?: null]);
    }

    public function deletePassenger(int $id): void
    {
        Gate::authorize('write');
        $guest = $this->event->transferGuests()->find($id);
        $guest?->delete();
        $guest?->transport_id && $this->event->transport()->find($guest->transport_id)?->assignVehicles();
    }

    /** Delete one vehicle; its passengers go back to the pool, not the bin. */
    public function delete(int $id): void
    {
        Gate::authorize('write');
        $this->event->transferGuests()->where('transport_id', $id)
            ->update(['transport_id' => null, 'vehicle_no' => null]);
        $this->event->transport()->whereKey($id)->delete();
    }

    /**
     * Advance one planning step without opening the edit form.
     *
     * The desk used to have to open every run to walk Planned → Ordered →
     * Confirmed. Live still owns the show-day steps; this only moves what
     * happens before anybody is at the airport.
     */
    public function advanceStatus(int $id): void
    {
        Gate::authorize('write');

        $movement = $this->event->transport()->whereKey($id)->firstOrFail();
        $next = $movement->nextPlanningStatus();

        if ($next === null) {
            return;
        }

        $movement->update(['status' => $next]);
    }

    public function setDay(string $day): void
    {
        $this->filterDay = $this->filterDay === $day ? '' : $day;
    }

    public function setLeg(string $leg): void
    {
        $this->filterLeg = isset(EventTransport::LEGS[$leg]) ? $leg : '';
        $this->expandedId = null;
    }

    /**
     * The filters currently on screen, in the shape the manifest PDF takes —
     * so "export" always means "export what I'm looking at".
     *
     * @return array<string,string>
     */
    public function exportFilters(): array
    {
        return array_filter([
            'leg' => $this->filterLeg,
            'day' => $this->filterDay,
        ]);
    }

    /**
     * How many of each vehicle the operation has committed — the number you
     * give the transport supplier: "3 sedans, 2 vans, 1 coach".
     *
     * @param  Collection<int,EventTransport>  $movements
     * @return Collection<string,array{name:string,capacity:int,vehicles:int,runs:int,pax:int}>
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

    public function budgetModule(): string
    {
        return 'transport';
    }

    public function render()
    {
        $all = $this->event->transport()
            ->with(['vehicleType', 'serviceType', 'manifest'])
            ->get()
            ->sortBy([fn (EventTransport $a, EventTransport $b) => [$a->chronoKey(), $a->id] <=> [$b->chronoKey(), $b->id]])
            ->values();

        // Leg tabs — arrivals, departures, everything else. Always in that order,
        // and always all three, so the strip doesn't move under you as you plan.
        $byLeg = $all->groupBy(fn (EventTransport $m) => $m->leg ?: 'other');

        $legTabs = collect(EventTransport::LEGS)->map(fn ($label, $key) => [
            'key' => $key,
            'label' => $label,
            'hint' => EventTransport::LEG_HINTS[$key],
            'runs' => ($byLeg[$key] ?? collect())->count(),
            'pax' => ($byLeg[$key] ?? collect())->sum(fn (EventTransport $m) => $m->paxCount()),
        ])->values();

        // Day tabs count within the chosen leg, so the two filters read as one
        // narrowing rather than two contradictory totals.
        $inLeg = EventTransport::selection($all, $this->filterLeg, '');

        $days = $inLeg->filter(fn (EventTransport $m) => $m->depart_at)
            ->groupBy(fn (EventTransport $m) => $m->depart_at->format('Y-m-d'))
            ->map->count();

        if ($this->filterDay !== '' && ! $days->has($this->filterDay)) {
            $this->filterDay = '';
        }

        $movements = EventTransport::selection($inLeg, '', $this->filterDay);

        // Vehicles this leg's guests are already riding, wherever they sit.
        $assignedElsewhere = $this->event->transferGuests()
            ->where('direction', $this->guestLeg)
            ->whereNotNull('transport_id')
            ->distinct()->pluck('transport_id')->all();

        return view('livewire.hub.transportation-tab', [
            'movements' => $movements->groupBy(fn (EventTransport $m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'days' => $days,
            'legTabs' => $legTabs,
            'shown' => $movements->count(),
            'total' => $all->count(),
            'vehicleTypes' => VehicleType::active()->orderBy('position')->get(),
            'serviceTypes' => TransportServiceType::active()->orderBy('position')->get(),
            'drivers' => TransportDriver::active()->orderBy('name')->get(),
            'fleetVehicles' => TransportVehicle::active()->with('vehicleType')->orderBy('plate_no')->get(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'seatsTotal' => $all->sum(fn (EventTransport $m) => $m->seats()),
            'paxTotal' => $all->sum(fn (EventTransport $m) => $m->paxCount()),
            'namedTotal' => $all->sum(fn (EventTransport $m) => $m->manifest->count()),
            'fleet' => $this->fleet($all),
            'overbooked' => $all->filter(fn (EventTransport $m) => $m->isOverbooked())->count(),
            'notReady' => $all->reject(fn (EventTransport $m) => $m->isSettled() || $m->isReady())->count(),
            'costTotal' => $all->sum('cost_cents'),
            // ── the guest pool ──
            'guests' => $this->guestPool(),
            'unassignedCount' => $this->event->transferGuests()->whereNull('transport_id')->count(),
            'attendeePull' => $this->event->attendees()->where('status', '!=', 'cancelled')
                ->whereNotIn('id', $this->event->transferGuests()->whereNotNull('attendee_id')->pluck('attendee_id'))->count(),
            'legCounts' => [
                'arrival' => $this->event->transferGuests()->where('direction', 'arrival')->whereNull('transport_id')->count(),
                'departure' => $this->event->transferGuests()->where('direction', 'departure')->whereNull('transport_id')->count(),
            ],
            // You can only put arriving guests on arriving runs. "Other" stays on
            // offer both ways — a venue shuttle carries whoever needs it — and a
            // guest's current vehicle is always listed, even if it's off-leg, so
            // the row never reads "unassigned" for someone who isn't.
            'assignTargets' => $all
                ->filter(fn (EventTransport $m) => in_array($m->leg, [$this->guestLeg, 'other'], true)
                    || in_array($m->id, $assignedElsewhere, true))
                ->sortBy(fn (EventTransport $m) => $m->chronoKey())
                ->values(),
        ]);
    }
}
