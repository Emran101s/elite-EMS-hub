<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Support\Workflow;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

#[Fillable(['tenant_id',
    'event_id', 'ref_no', 'type', 'leg', 'is_vip', 'driver_id', 'vehicle_id', 'supplier_id', 'delayed_to', 'issue_note', 'started_at', 'completed_at', 'vehicle_type_id', 'service_type_id', 'vehicles', 'route', 'pickup_from', 'drop_to', 'provider', 'driver_contact', 'depart_at', 'flight_no', 'arrive_at', 'capacity', 'passengers', 'cost_cents', 'status', 'notes'])]
class EventTransport extends Model
{
    use BelongsToTenant;

    protected $table = 'event_transport';

    public const TYPES = ['shuttle', 'coach', 'sedan', 'van', 'vip', 'flight'];

    public const STATUSES = ['planned', 'ordered', 'confirmed', 'in_progress', 'completed', 'issue', 'cancelled'];

    /**
     * One status, seven states, covering planning through the event itself.
     *
     * Readiness (is there a driver? a vehicle? passengers?) is deliberately NOT
     * in here — it's computed from the record, so it can't go stale. Delays and
     * no-shows are flags that can be true alongside any status.
     *
     * @var array<string,array{label:string,tone:string,hint:string}>
     */
    public const STATUS_META = [
        'planned' => ['label' => 'Planned', 'tone' => 'slate', 'hint' => 'The movement exists. Nothing committed yet.'],
        'ordered' => ['label' => 'Ordered', 'tone' => 'amber', 'hint' => 'Sent to the supplier, awaiting their confirmation.'],
        'confirmed' => ['label' => 'Confirmed', 'tone' => 'green', 'hint' => 'Supplier confirmed. Vehicle and driver named.'],
        'in_progress' => ['label' => 'In progress', 'tone' => 'blue', 'hint' => 'Running right now.'],
        'completed' => ['label' => 'Completed', 'tone' => 'grey', 'hint' => 'Delivered.'],
        'issue' => ['label' => 'Issue', 'tone' => 'red', 'hint' => 'Something is wrong — needs a note and attention.'],
        'cancelled' => ['label' => 'Cancelled', 'tone' => 'struck', 'hint' => 'Not running. Kept for the record.'],
    ];

    /** Tailwind classes per tone, in one place so the tab and the PDF agree. */
    public const STATUS_CLASSES = [
        'slate' => 'bg-navy-100 text-navy-600',
        'amber' => 'bg-amber-100 text-amber-700',
        'green' => 'bg-emerald-100 text-emerald-700',
        'blue' => 'bg-sky-100 text-sky-700',
        'grey' => 'bg-navy-50 text-navy-400',
        'red' => 'bg-red-100 text-red-700',
        'struck' => 'bg-navy-50 text-navy-400 line-through',
    ];

    public function statusLabel(): string
    {
        return Workflow::label('transport_status', $this->status);
    }

    public function statusClass(): string
    {
        $tone = self::STATUS_META[$this->status]['tone'] ?? 'slate';

        return self::STATUS_CLASSES[$tone];
    }

    /** Statuses that mean the trip is behind us — they sink on the live board. */
    public function isSettled(): bool
    {
        return in_array($this->status, ['completed', 'cancelled'], true);
    }

    /**
     * The next planning status, or null once Live owns the run.
     *
     * Planned → Ordered → Confirmed is the desk's job before show day.
     * In progress / completed / issue belong to the Live board, where the
     * person holding a phone advances them — the list must not compete.
     */
    public function nextPlanningStatus(): ?string
    {
        return match ($this->status) {
            'planned' => 'ordered',
            'ordered' => 'confirmed',
            default => null,
        };
    }

    /**
     * How the operation actually thinks about a movement — the same split the
     * flight list uses. "Other" is everything that isn't an airport run:
     * hotel↔venue shuttles, city transfers, a car at disposal.
     */
    public const LEGS = [
        'arrival' => 'Arrival',
        'departure' => 'Departure',
        'other' => 'Other',
    ];

    /** Pickups and drop-offs read better than the raw key on a driver's sheet. */
    public const LEG_HINTS = [
        'arrival' => 'Pick-up · people landing',
        'departure' => 'Drop-off · people flying out',
        'other' => 'Shuttles, city runs, at disposal',
    ];

    public function legLabel(): string
    {
        return self::LEGS[$this->leg] ?? 'Other';
    }

    /**
     * Number every movement as it's created, per event. Done here rather than in
     * the form so imports, duplicates and suggested runs are all numbered too.
     */
    protected static function booted(): void
    {
        static::creating(function (self $m) {
            if ($m->ref_no === null && $m->event_id) {
                $m->ref_no = (int) static::where('event_id', $m->event_id)->max('ref_no') + 1;
            }
        });
    }

    /** What you call it out loud: "car 3". */
    public function refLabel(): string
    {
        return $this->ref_no ? '#'.$this->ref_no : '—';
    }

    /**
     * A specific vehicle inside this movement: "#3.2" when a run needs two vans,
     * plain "#3" when it's a single car.
     */
    public function vehicleLabel(int $vehicleNo): string
    {
        return $this->vehicleCount() > 1
            ? $this->refLabel().'.'.$vehicleNo
            : $this->refLabel();
    }

    /**
     * Best guess at a movement's leg, most trustworthy signal first: who is
     * riding it, then which end of it is an airport.
     *
     * @param  array<int,string>  $passengerDirections
     */
    public static function inferLeg(string $from, string $to, string $route = '', array $passengerDirections = []): string
    {
        // The people on board settle it — a van of arrivals is an arrival.
        $voted = array_count_values(array_filter($passengerDirections));
        if ($voted !== []) {
            arsort($voted);

            return array_key_first($voted);
        }

        $airportish = fn (string $s) => (bool) preg_match('/airport|qaia|terminal|apt\b/i', $s);

        if ($airportish($from) && ! $airportish($to)) {
            return 'arrival';
        }

        if ($airportish($to) && ! $airportish($from)) {
            return 'departure';
        }

        // Nothing but a route string to go on: "Airport → Hotel" still tells us.
        if ($route !== '' && str_contains($route, '→')) {
            [$a, $b] = array_pad(explode('→', $route, 2), 2, '');

            if ($airportish($a) !== $airportish($b)) {
                return $airportish($a) ? 'arrival' : 'departure';
            }
        }

        return 'other';
    }

    protected function casts(): array
    {
        return [
            'depart_at' => 'datetime',
            'arrive_at' => 'datetime',
            'delayed_to' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'is_vip' => 'boolean',
            'capacity' => 'integer',
            'passengers' => 'integer',
            // decimal:1, not integer — the underlying column is decimal(15,1)
            // now (tenths of a cent), so a cost of 127.116 keeps its third
            // decimal instead of being rounded away on read.
            'cost_cents' => 'decimal:1',
        ];
    }

    public function vehicleType(): BelongsTo
    {
        return $this->belongsTo(VehicleType::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(TransportDriver::class, 'driver_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(TransportVehicle::class, 'vehicle_id');
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Three things a movement needs before it can run. Computed, never stored —
     * a stored readiness flag is a lie waiting to happen.
     *
     * @return array{vehicle:bool,driver:bool,passengers:bool,score:int,missing:array<int,string>}
     */
    /**
     * What this movement still needs, in words, as one answer.
     *
     * Readiness was said three times in three languages on every row — a
     * status pill, a line of amber text about the driver, and three dots that
     * needed a tooltip to decode. Somebody scanning forty movements for the
     * one that is not ready cannot read dots. One phrase, one colour.
     *
     * @return array{label:string,ready:bool,detail:string}
     */
    public function readinessChip(): array
    {
        $missing = $this->readiness()['missing'];

        if ($missing === []) {
            return ['label' => 'Ready', 'ready' => true, 'detail' => 'Vehicle, driver and passengers all set.'];
        }

        // Named in the order the desk fixes them: a car with no driver is a
        // phone call, a car with nobody in it is a plan that has not been made.
        $words = [
            'driver' => 'a driver',
            'vehicle' => 'a vehicle',
            'passengers' => 'passengers',
        ];

        $needs = collect(['driver', 'vehicle', 'passengers'])
            ->filter(fn (string $k) => in_array($k, $missing, true))
            ->map(fn (string $k) => $words[$k])
            ->values();

        return [
            'label' => 'Needs '.$needs->join(' and '),
            'ready' => false,
            'detail' => $this->readiness()['score'].' of 3 ready.',
        ];
    }

    public function readiness(): array
    {
        $has = [
            'vehicle' => (bool) ($this->vehicle_id ?: $this->vehicle_type_id),
            'driver' => (bool) $this->driver_id,
            'passengers' => $this->paxCount() > 0,
        ];

        return [
            ...$has,
            'score' => count(array_filter($has)),
            'missing' => array_keys(array_filter($has, fn ($ok) => ! $ok)),
        ];
    }

    public function isReady(): bool
    {
        return $this->readiness()['score'] === 3;
    }

    /**
     * A trip is priority if it was marked so, or — more usefully — because
     * someone riding it is. Nobody should have to remember to tick a box after
     * dropping a VIP onto a van.
     */
    public function isPriority(): bool
    {
        if ($this->is_vip) {
            return true;
        }

        $pax = $this->relationLoaded('manifest') ? $this->manifest : $this->manifest()->get();

        return $pax->contains(fn (EventTransportPassenger $p) => $p->isPriority());
    }

    /** "VIP" or "Speaker" — why this trip is flagged, for the tooltip. */
    public function priorityReason(): ?string
    {
        if (! $this->isPriority()) {
            return null;
        }

        if ($this->is_vip) {
            return 'Marked as a VIP movement';
        }

        $pax = $this->relationLoaded('manifest') ? $this->manifest : $this->manifest()->get();
        $names = $pax->filter(fn (EventTransportPassenger $p) => $p->isPriority())
            ->map(fn (EventTransportPassenger $p) => $p->categoryLabel().' · '.$p->name);

        return $names->take(3)->implode(', ').($names->count() > 3 ? ' +'.($names->count() - 3).' more' : '');
    }

    /** The time this actually leaves, once a delay has been recorded. */
    public function effectiveDeparture(): ?Carbon
    {
        return $this->delayed_to ?? $this->depart_at;
    }

    /** Assume an hour when nothing's set — long enough to be worth reserving. */
    public const DEFAULT_DURATION_MINUTES = 60;

    /**
     * When a run frees its resource again. `arrive_at` if we have it; otherwise a
     * conservative hour after departure, so the dispatch board reserves the car
     * for a sensible window rather than an instant.
     */
    public function estimatedEnd(): ?Carbon
    {
        $start = $this->effectiveDeparture();

        if (! $start) {
            return null;
        }

        return $this->arrive_at && $this->arrive_at->gt($start)
            ? $this->arrive_at
            : $start->copy()->addMinutes(self::DEFAULT_DURATION_MINUTES);
    }

    /** Do two runs overlap in time? The core of dispatch conflict detection. */
    public function overlaps(self $other): bool
    {
        $aStart = $this->effectiveDeparture();
        $bStart = $other->effectiveDeparture();

        if (! $aStart || ! $bStart) {
            return false;   // an unscheduled run can't clash with anything yet
        }

        return $aStart->lt($other->estimatedEnd()) && $bStart->lt($this->estimatedEnd());
    }

    /**
     * The ids of runs that double-book a resource: same driver (or vehicle) on
     * two runs whose times overlap. Cancelled runs never clash.
     *
     * @param  Collection<int,self>  $movements
     * @param  'driver'|'vehicle'  $by
     * @return array<int,int> movement ids in conflict
     */
    public static function conflicts($movements, string $by = 'driver'): array
    {
        $key = $by === 'vehicle' ? 'vehicle_id' : 'driver_id';

        return $movements
            ->reject(fn (self $m) => $m->status === 'cancelled' || ! $m->{$key})
            ->groupBy($key)
            ->flatMap(function ($runs) {
                $clashing = [];

                foreach ($runs as $a) {
                    foreach ($runs as $b) {
                        if ($a->id !== $b->id && $a->overlaps($b)) {
                            $clashing[] = $a->id;
                            break;
                        }
                    }
                }

                return $clashing;
            })
            ->unique()->values()->all();
    }

    /** Who to call, and on what number, when this run goes wrong. */
    public function contactNumber(): ?string
    {
        return $this->driver?->phone ?: $this->driver_contact;
    }

    /** The supplier's name whether it's a linked record or the old free text. */
    public function supplierName(): ?string
    {
        return $this->supplier?->name ?: $this->provider;
    }

    public function serviceType(): BelongsTo
    {
        return $this->belongsTo(TransportServiceType::class);
    }

    /** Seats available across the vehicles booked for this movement. */
    public function seats(): int
    {
        $per = $this->vehicleType?->capacity ?? $this->capacity ?? 0;

        return $per * max(1, (int) $this->vehicles);
    }

    /** Seats in ONE vehicle of this run — what the split is calculated from. */
    public function seatsPerVehicle(): int
    {
        return (int) ($this->vehicleType?->capacity ?? $this->capacity ?? 0);
    }

    /**
     * Fill the vehicles in order and record who rides in which: with a 7-seat van
     * and twelve passengers, the first seven get van 1 and the rest van 2. Grows
     * the fleet when the manifest outgrows the vehicles already booked.
     */
    public function assignVehicles(): void
    {
        $pax = $this->manifest()->get();
        if ($pax->isEmpty()) {
            return;
        }

        $per = $this->seatsPerVehicle();

        // Capacity unknown — one vehicle, everyone in it.
        if ($per < 1) {
            $pax->each(fn ($p) => $p->vehicle_no === 1 || $p->update(['vehicle_no' => 1]));

            return;
        }

        $needed = (int) ceil($pax->count() / $per);
        if ($needed > (int) $this->vehicles) {
            $this->update(['vehicles' => $needed]);
        }

        foreach ($pax->values() as $i => $p) {
            $no = intdiv($i, $per) + 1;
            if ((int) $p->vehicle_no !== $no) {
                $p->update(['vehicle_no' => $no]);
            }
        }
    }

    /** The manifest split per vehicle: [1 => passengers…, 2 => passengers…]. */
    public function manifestByVehicle(): Collection
    {
        $pax = $this->relationLoaded('manifest') ? $this->manifest : $this->manifest()->get();

        return $pax->groupBy(fn ($p) => max(1, (int) ($p->vehicle_no ?: 1)))->sortKeys();
    }

    /**
     * Sort key for schedules: date then time, ascending. Movements with no time
     * set sort last rather than first — an unscheduled run is not the earliest
     * one, and SQL's NULLs-first ordering would otherwise put it at the top.
     */
    public function chronoKey(): string
    {
        return $this->depart_at?->format('Y-m-d H:i') ?? '9999-12-31 23:59';
    }

    /**
     * The one place a "which movements?" selection is resolved, so the tab on
     * screen and the manifest PDF can never disagree about what's in scope.
     *
     * @param  Collection<int,self>  $movements
     * @param  string|null  $leg  'arrival', 'departure', 'other', or '' for all
     * @param  string|null  $day  a Y-m-d date, or '' for every day
     * @return Collection<int,self>
     */
    public static function selection($movements, ?string $leg = '', ?string $day = '')
    {
        return $movements
            ->when(isset(self::LEGS[(string) $leg]),
                fn ($c) => $c->filter(fn (self $m) => $m->leg === $leg))
            ->when((string) $day !== '',
                fn ($c) => $c->filter(fn (self $m) => $m->depart_at?->format('Y-m-d') === $day))
            ->values();
    }

    /** How that selection reads on a PDF cover: "Arrivals · Mon 27 Jul". */
    public static function selectionLabel(?string $leg, ?string $day): string
    {
        $parts = [];

        if (isset(self::LEGS[(string) $leg])) {
            $parts[] = match ($leg) {
                'arrival' => 'Arrivals — pick-ups',
                'departure' => 'Departures — drop-offs',
                default => 'Other movements',
            };
        }

        if ((string) $day !== '') {
            $parts[] = Carbon::parse($day)->format('D j M Y');
        }

        return $parts === [] ? 'All movements' : implode('  ·  ', $parts);
    }

    /** The manifest — who is actually riding. */
    public function manifest(): HasMany
    {
        return $this->hasMany(EventTransportPassenger::class, 'transport_id')
            ->orderBy('position')->orderBy('id');
    }

    /**
     * Headcount. Named passengers win once anyone is on the manifest; the plain
     * `passengers` number is the estimate you start with before names exist.
     */
    public function paxCount(): int
    {
        $named = $this->relationLoaded('manifest') ? $this->manifest->count() : $this->manifest()->count();

        return $named ?: (int) $this->passengers;
    }

    public function seatsFree(): int
    {
        return max(0, $this->seats() - $this->paxCount());
    }

    /** Passengers beyond what the booked vehicles can carry. */
    public function isOverbooked(): bool
    {
        return $this->seats() > 0 && $this->paxCount() > $this->seats();
    }

    /** How many vehicles of this movement's type are committed. */
    public function vehicleCount(): int
    {
        return max(1, (int) $this->vehicles);
    }

    /** "Airport → Hotel · Regular Van ×2" */
    public function movementLabel(): string
    {
        $bits = array_filter([
            $this->serviceType?->name,
            $this->vehicleType ? $this->vehicleType->name.($this->vehicles > 1 ? ' ×'.$this->vehicles : '') : null,
        ]);

        return $bits ? implode(' · ', $bits) : ($this->route ?: 'Movement');
    }

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }
}
