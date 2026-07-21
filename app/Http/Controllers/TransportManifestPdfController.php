<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventTransport;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The transport manifest: every movement, day by day, with the names riding in
 * each vehicle — plus the fleet count to hand the supplier ("3 sedans, 2 vans").
 */
class TransportManifestPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event): Response
    {
        // Strictly chronological — date then time, first to arrive to last.
        // Unscheduled runs sort to the end rather than the top (see chronoKey).
        $movements = $event->transport()
            ->with(['vehicleType', 'serviceType', 'manifest'])
            ->get()
            ->sortBy([fn (EventTransport $a, EventTransport $b) => [$a->chronoKey(), $a->id] <=> [$b->chronoKey(), $b->id]])
            ->values();

        // Same shape the tab's rail shows, computed here so the PDF stands alone.
        $fleet = $movements
            ->filter(fn (EventTransport $m) => $m->vehicleType)
            ->groupBy(fn (EventTransport $m) => $m->vehicleType->name)
            ->map(fn ($g, $name) => [
                'name' => $name,
                'capacity' => $g->first()->vehicleType->capacity,
                'vehicles' => $g->sum(fn (EventTransport $m) => $m->vehicleCount()),
                'runs' => $g->count(),
                'pax' => $g->sum(fn (EventTransport $m) => $m->paxCount()),
            ])
            ->sortByDesc('vehicles');

        $html = view('events.transport-manifest-pdf', [
            'event' => $event,
            'days' => $movements->groupBy(fn (EventTransport $m) => $m->depart_at?->format('Y-m-d') ?? 'unscheduled'),
            'fleet' => $fleet,
            'movements' => $movements,
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($event->name ?: 'event').'-transport-manifest.pdf"',
        ]);
    }
}
