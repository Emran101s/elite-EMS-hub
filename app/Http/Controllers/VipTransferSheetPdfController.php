<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventTransportPassenger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The protocol team's document — one page per person, never batched.
 *
 * A VIP sheet shared with three other names is useless at an arrivals barrier:
 * the greeter is holding it while looking for one face. So each guest gets their
 * own page, showing every movement they make across the whole event.
 */
class VipTransferSheetPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, Request $request): Response
    {
        // Defaults to everyone who is priority; ?category= narrows to one group.
        $category = (string) $request->query('category', '');

        $guests = $event->transferGuests()
            ->with(['transport.driver', 'transport.vehicle', 'transport.vehicleType'])
            ->when(
                isset(EventTransportPassenger::CATEGORIES[$category]),
                fn ($q) => $q->where('category', $category),
                fn ($q) => $q->priority(),
            )
            ->get();

        // One page per person — the same guest arriving and departing is one
        // sheet with two movements, not two sheets.
        $people = $guests
            ->groupBy(fn (EventTransportPassenger $g) => mb_strtolower(trim($g->name)))
            ->map(fn ($rows) => [
                'guest' => $rows->sortByDesc(fn ($g) => $g->isPriority())->first(),
                'legs' => $rows->sortBy(fn ($g) => [$g->arrival_on?->timestamp ?? PHP_INT_MAX, $g->pickup_time ?? '']),
            ])
            ->sortBy(fn ($p) => $p['guest']->name)
            ->values();

        $html = view('events.transport-vip-sheet-pdf', [
            'event' => $event,
            'people' => $people,
            'control' => $event->controlContact(),
            'scope' => isset(EventTransportPassenger::CATEGORIES[$category])
                ? EventTransportPassenger::CATEGORIES[$category]
                : 'VIP & Speakers',
            'css' => $this->compiledCss(),
        ])->render();

        return response($this->chrome($html)->format('A4')->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($event->name ?: 'event')
                .'-vip-transfer-sheet'.($category ? '-'.Str::slug($category) : '').'.pdf"',
        ]);
    }
}
