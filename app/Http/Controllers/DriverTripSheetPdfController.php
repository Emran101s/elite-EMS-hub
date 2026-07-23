<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\TransportDriver;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The driver's own sheet — one page per driver per day, largest type in the suite.
 *
 * Read in a car, often at night, by someone who may not speak the platform's
 * language. Phone numbers are set big on purpose; the emergency contact gets its
 * own bordered box so it can be found without reading anything else.
 */
class DriverTripSheetPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, Request $request): Response
    {
        $day = (string) $request->query('day', '');
        $driverId = (int) $request->query('driver', 0);

        $movements = $event->transport()
            ->with(['vehicleType', 'vehicle', 'serviceType', 'driver', 'manifest'])
            ->whereNotNull('driver_id')
            ->when($driverId, fn ($q) => $q->where('driver_id', $driverId))
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sortBy([fn (EventTransport $a, EventTransport $b) => [$a->chronoKey(), $a->id] <=> [$b->chronoKey(), $b->id]])
            ->values();

        $movements = EventTransport::selection($movements, '', $day);

        // A sheet is a driver AND a day — one driver working three days gets three
        // pages, because that's what they're each handed on the morning.
        $sheets = $movements
            ->groupBy(fn (EventTransport $m) => $m->driver_id.'|'.($m->depart_at?->format('Y-m-d') ?? 'unscheduled'))
            ->map(fn ($runs) => [
                'driver' => $runs->first()->driver,
                'date' => $runs->first()->depart_at,
                'runs' => $runs,
                'duty' => $runs->first()->driver && $runs->first()->depart_at
                    ? $runs->first()->driver->dutyMinutes($runs->first()->depart_at->toDateString())
                    : 0,
            ])
            ->sortBy(fn ($s) => [$s['date']?->timestamp ?? PHP_INT_MAX, $s['driver']?->name])
            ->values();

        $html = view('events.transport-driver-sheet-pdf', [
            'event' => $event,
            'sheets' => $sheets,
            'control' => $event->controlContact(),
            'css' => $this->compiledCss(),
        ])->render();

        $who = $driverId ? TransportDriver::find($driverId)?->name : null;

        return response($this->chrome($html)->format('A4')->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($event->name ?: 'event')
                .'-trip-sheet'.($who ? '-'.Str::slug($who) : '').($day ? '-'.$day : '').'.pdf"',
        ]);
    }
}
