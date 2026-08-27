<?php

namespace App\Http\Controllers;

use App\Models\Venue;
use App\Services\VenueCommandHeader;
use Illuminate\View\View;

class VenueStudioController extends Controller
{
    /** @return list<string> */
    public static function tabs(): array
    {
        return array_keys(Venue::STUDIO_TABS);
    }

    public function show(Venue $venue, VenueCommandHeader $headerService): View
    {
        $tab = in_array(request('tab'), self::tabs(), true) ? request('tab') : 'overview';

        $venue->load([
            'spaces.bookings.event',
            'documents',
            'events' => fn ($q) => $q->active()->whereNotNull('starts_at')->orderBy('starts_at'),
        ]);

        return view('venues.studio', [
            'venue' => $venue,
            'tab' => $tab,
            'header' => $headerService->for($venue),
        ]);
    }
}
