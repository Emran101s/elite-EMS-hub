<?php

namespace App\Livewire;

use App\Models\Event;
use App\Models\EventTransport;
use App\Models\TransportDriver;
use App\Models\TransportVehicle;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * The dispatch board: a day's movements laid out as lanes against a time axis,
 * one lane per driver (or vehicle). Its reason to exist is conflict detection —
 * two runs overlapping in one lane means a resource is double-booked, and that
 * is drawn in red so you catch it before the supplier does.
 *
 * Desktop-and-tablet by design: a Gantt on a phone is a lie, so the view tells a
 * small screen to open somewhere bigger rather than pretending.
 */
#[Layout('components.layouts.app', ['title' => 'Dispatch Board'])]
class TransportDispatch extends Component
{
    public Event $event;

    public string $day = '';

    /** Lanes are drivers or specific vehicles — whichever you're checking. */
    public string $groupBy = 'driver';

    public string $flash = '';

    public function mount(Event $event): void
    {
        $this->event = $event;
        $this->day = $this->availableDays()->first() ?? now()->toDateString();
    }

    /** @return Collection<int,string> */
    private function availableDays(): Collection
    {
        return $this->event->transport()
            ->whereNotNull('depart_at')
            ->pluck('depart_at')
            ->map(fn ($d) => Carbon::parse($d)->toDateString())
            ->unique()->sort()->values();
    }

    public function setDay(string $day): void
    {
        $this->day = $day;
    }

    public function setGroupBy(string $by): void
    {
        $this->groupBy = in_array($by, ['driver', 'vehicle'], true) ? $by : 'driver';
    }

    /** The day's movements, scheduled ones first. */
    private function dayRuns(): Collection
    {
        return $this->event->transport()
            ->with(['driver', 'vehicle.vehicleType', 'vehicleType', 'manifest'])
            ->whereDate('depart_at', $this->day)
            ->get()
            ->sortBy(fn (EventTransport $m) => $m->effectiveDeparture()?->timestamp ?? PHP_INT_MAX)
            ->values();
    }

    /**
     * Move a run onto another driver or vehicle — the drag target. Lane key
     * 'none' sends it back to the unassigned lane.
     */
    public function reassign(int $movementId, string $laneKey): void
    {
        Gate::authorize('write');

        $m = $this->event->transport()->findOrFail($movementId);
        $field = $this->groupBy === 'vehicle' ? 'vehicle_id' : 'driver_id';
        $id = $laneKey === 'none' ? null : (int) $laneKey;

        // Only accept a real resource of the right kind.
        if ($id !== null) {
            $exists = $this->groupBy === 'vehicle'
                ? TransportVehicle::whereKey($id)->exists()
                : TransportDriver::whereKey($id)->exists();

            if (! $exists) {
                return;
            }
        }

        $m->update([$field => $id]);

        $label = $this->groupBy === 'vehicle'
            ? ($id ? TransportVehicle::find($id)?->label() : 'no vehicle')
            : ($id ? TransportDriver::find($id)?->name : 'no driver');

        $this->flash = 'Car '.$m->refLabel().' → '.$label.'.';
    }

    /**
     * Build the board. Each lane is a resource with the runs on it that day, laid
     * out as bars, plus whether any of them clash.
     *
     * @return array{
     *   start:Carbon, end:Carbon, hours:array<int,string>, spanMinutes:int,
     *   lanes:Collection, unassigned:Collection, conflictIds:array<int,int>,
     *   resources:Collection
     * }
     */
    public function board(): array
    {
        $runs = $this->dayRuns();
        $field = $this->groupBy === 'vehicle' ? 'vehicle_id' : 'driver_id';
        $conflictIds = EventTransport::conflicts($runs, $this->groupBy);

        // Time window: the day's earliest start to latest end, padded to whole
        // hours so the axis reads cleanly.
        $starts = $runs->map(fn (EventTransport $m) => $m->effectiveDeparture())->filter();
        $ends = $runs->map(fn (EventTransport $m) => $m->estimatedEnd())->filter();

        $start = $starts->min()?->copy()->startOfHour() ?? Carbon::parse($this->day.' 06:00');
        $end = $ends->max()?->copy()->ceilHour() ?? Carbon::parse($this->day.' 20:00');
        if ($end->lte($start)) {
            $end = $start->copy()->addHours(4);
        }

        $spanMinutes = max(60, $start->diffInMinutes($end));

        $hours = [];
        for ($h = $start->copy(); $h->lte($end); $h->addHour()) {
            $hours[] = $h->format('H:i');
        }

        // Only resources actually used that day get a lane — the roster can be
        // large; the board should show the operation, not the catalogue.
        $usedIds = $runs->pluck($field)->filter()->unique();

        $resources = $this->groupBy === 'vehicle'
            ? TransportVehicle::with('vehicleType')->whereIn('id', $usedIds)->get()
            : TransportDriver::whereIn('id', $usedIds)->get();

        $lanes = $resources->map(fn ($resource) => [
            'key' => (string) $resource->id,
            'label' => $this->groupBy === 'vehicle' ? $resource->label() : $resource->name,
            'sublabel' => $this->groupBy === 'vehicle'
                ? ($resource->vehicleType?->name ?? '')
                : ($resource->phone ?? ''),
            'runs' => $runs->where($field, $resource->id)->values(),
        ]);

        return [
            'start' => $start,
            'end' => $end,
            'hours' => $hours,
            'spanMinutes' => $spanMinutes,
            'lanes' => $lanes->values(),
            'unassigned' => $runs->whereNull($field)->values(),
            'conflictIds' => $conflictIds,
            // For the drag targets' pickers, every active resource, not just used.
            'resources' => $this->groupBy === 'vehicle'
                ? TransportVehicle::active()->with('vehicleType')->orderBy('plate_no')->get()
                : TransportDriver::active()->orderBy('name')->get(),
        ];
    }

    public function render()
    {
        $board = $this->board();

        return view('livewire.transport-dispatch', [
            'board' => $board,
            'days' => $this->availableDays(),
            'conflictCount' => count($board['conflictIds']),
            'unstaffed' => $board['unassigned']->count(),
        ]);
    }
}
