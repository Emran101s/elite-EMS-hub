<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\CompanyProfile;
use App\Models\Event;
use App\Models\EventTransport;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The Supplier Order — what you hand a transport vendor: here is what I need you
 * to provide. A request, not an invoice, so it carries NO client price. The
 * supplier quotes back against it.
 *
 * Without ?supplier= it covers every movement not yet tied to a supplier plus a
 * page per supplier; with it, just that one vendor's order.
 */
class SupplierOrderPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, Request $request): Response
    {
        $supplierId = (int) $request->query('supplier', 0);

        $movements = $event->transport()
            ->with(['vehicleType', 'serviceType', 'driver', 'supplier'])
            ->when($supplierId, fn ($q) => $q->where('supplier_id', $supplierId))
            ->where('status', '!=', 'cancelled')
            ->get()
            ->sortBy([fn (EventTransport $a, EventTransport $b) => [$a->chronoKey(), $a->id] <=> [$b->chronoKey(), $b->id]])
            ->values();

        // One order per supplier. Movements with no supplier are gathered into a
        // single "to be assigned" order you can hand to whoever you pick.
        $orders = $movements
            ->groupBy(fn (EventTransport $m) => $m->supplier_id ?: 'unassigned')
            ->map(function ($runs, $key) {
                $supplier = $key === 'unassigned' ? null : $runs->first()->supplier;

                return [
                    'supplier' => $supplier,
                    'runs' => $runs,
                    // What to quote: vehicle counts by type and day.
                    'requirements' => $this->requirements($runs),
                    'days' => $runs->map(fn (EventTransport $m) => $m->depart_at?->toDateString())->filter()->unique()->count(),
                ];
            })
            ->sortBy(fn ($o) => $o['supplier']?->name ?? 'zzz')
            ->values();

        $html = view('events.transport-supplier-order-pdf', [
            'event' => $event,
            'company' => CompanyProfile::first(),
            'orders' => $orders,
            'control' => $event->controlContact(),
            'css' => $this->compiledCss(),
        ])->render();

        $who = $supplierId ? Supplier::find($supplierId)?->name : null;

        return response($this->chrome($html)->format('A4')->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($event->name ?: 'event')
                .'-transport-order'.($who ? '-'.Str::slug($who) : '').'.pdf"',
        ]);
    }

    /**
     * Vehicles required, by type and day — the line items a supplier prices.
     *
     * @param  Collection<int,EventTransport>  $runs
     * @return Collection<int,array{type:string,capacity:?int,date:?string,vehicles:int,trips:int}>
     */
    private function requirements($runs)
    {
        return $runs
            ->groupBy(fn (EventTransport $m) => ($m->vehicleType?->name ?? 'Vehicle')
                .'|'.($m->depart_at?->toDateString() ?? 'TBC'))
            ->map(fn ($group) => [
                'type' => $group->first()->vehicleType?->name ?? 'Vehicle (type to confirm)',
                'capacity' => $group->first()->vehicleType?->capacity,
                'date' => $group->first()->depart_at?->toDateString(),
                'vehicles' => $group->sum(fn (EventTransport $m) => $m->vehicleCount()),
                'trips' => $group->count(),
            ])
            ->sortBy(fn ($r) => [$r['date'] ?? 'zzz', $r['type']])
            ->values();
    }
}
