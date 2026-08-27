<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventTransport;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The Master Transportation Plan — the client-facing document, for approval.
 *
 * The only one in the suite with a cover page and a signature block. It reads as
 * a proposal, not a manifest: summaries, not row-level detail. It shows a cost
 * TOTAL where a cost is recorded, and never any margin or supplier rate — that
 * is ours, not the client's.
 */
class TransportMasterPlanPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event): Response
    {
        $movements = $event->transport()
            ->with(['vehicleType', 'driver', 'supplier', 'serviceType', 'manifest'])
            ->get()
            ->sortBy([fn (EventTransport $a, EventTransport $b) => [$a->chronoKey(), $a->id] <=> [$b->chronoKey(), $b->id]])
            ->values();

        // Fleet: how many of each vehicle type, over the whole event.
        $fleet = $movements
            ->filter(fn (EventTransport $m) => $m->vehicleType)
            ->groupBy(fn (EventTransport $m) => $m->vehicleType->name)
            ->map(fn ($g, $name) => [
                'name' => $name,
                'capacity' => $g->first()->vehicleType->capacity,
                'vehicles' => $g->sum(fn (EventTransport $m) => $m->vehicleCount()),
                'runs' => $g->count(),
            ])
            ->sortByDesc('vehicles')->values();

        // Movements by leg — the shape the client thinks in.
        $byLeg = collect(EventTransport::LEGS)->map(fn ($label, $key) => [
            'label' => $label,
            'runs' => $movements->where('leg', $key)->count(),
            'pax' => $movements->where('leg', $key)->sum(fn (EventTransport $m) => $m->paxCount()),
        ])->filter(fn ($r) => $r['runs'] > 0)->values();

        // Routes actually run, with how often.
        $routes = $movements
            ->groupBy(fn (EventTransport $m) => trim(($m->pickup_from ?: '—').' → '.($m->drop_to ?: '—')))
            ->map(fn ($g, $route) => [
                'route' => $route,
                'runs' => $g->count(),
                'legs' => $g->pluck('leg')->unique()->map(fn ($l) => EventTransport::LEGS[$l] ?? 'Other')->implode(', '),
            ])
            ->sortByDesc('runs')->values();

        $guests = $event->transferGuests()->get();
        $vipCount = $movements->filter(fn (EventTransport $m) => $m->isPriority())->count();

        $costCents = $movements->sum('cost_cents');

        $html = view('events.transport-master-plan-pdf', [
            'event' => $event,
            'company' => CompanyProfile::first(),
            'movements' => $movements,
            'fleet' => $fleet,
            'byLeg' => $byLeg,
            'routes' => $routes,
            'summary' => [
                'movements' => $movements->count(),
                'vehicles' => $fleet->sum('vehicles'),
                'drivers' => $movements->pluck('driver_id')->filter()->unique()->count(),
                'suppliers' => $movements->map(fn (EventTransport $m) => $m->supplierName())->filter()->unique()->count(),
                'guests' => $guests->count(),
                'arrivals' => $guests->where('direction', 'arrival')->count(),
                'departures' => $guests->where('direction', 'departure')->count(),
                'vip' => $vipCount,
                'days' => $movements->map(fn (EventTransport $m) => $m->depart_at?->toDateString())->filter()->unique()->count(),
            ],
            'costCents' => $costCents,
            'control' => $event->controlContact(),
            'css' => $this->compiledCss(),
        ])->render();

        return response($this->chrome($html)->format('A4')->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($event->name ?: 'event').'-transportation-plan.pdf"',
        ]);
    }
}
