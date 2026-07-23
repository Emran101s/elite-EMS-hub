<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventTransport;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The operations team's document: one day per page, strictly chronological,
 * car number in the leftmost column.
 *
 * Dense is correct here — this is scanned against a clipboard, not read. It
 * honours the same leg and day filters as the tab, so what you export is what
 * you were looking at.
 */
class DailyMovementSchedulePdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, Request $request): Response
    {
        $leg = (string) $request->query('leg', '');
        $day = (string) $request->query('day', '');

        $movements = $event->transport()
            ->with(['vehicleType', 'vehicle', 'serviceType', 'driver', 'supplier', 'manifest'])
            ->get()
            ->sortBy([fn (EventTransport $a, EventTransport $b) => [$a->chronoKey(), $a->id] <=> [$b->chronoKey(), $b->id]])
            ->values();

        $movements = EventTransport::selection($movements, $leg, $day);

        $html = view('events.transport-daily-schedule-pdf', [
            'event' => $event,
            'days' => $movements->groupBy(fn (EventTransport $m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'movements' => $movements,
            'selection' => EventTransport::selectionLabel($leg, $day),
            'control' => $event->controlContact(),
            'css' => $this->compiledCss(),
        ])->render();

        $selection = EventTransport::selectionLabel($leg, $day);

        return response($this->chrome($html)->format('A4')->landscape()->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($event->name ?: 'event')
                .'-daily-schedule'.($selection === 'All movements' ? '' : '-'.Str::slug($selection)).'.pdf"',
        ]);
    }
}
