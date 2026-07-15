<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class SponsorshipController extends Controller
{
    /** Resolve the packages to show — all, or a single one via ?package= . */
    private function data(Event $event, Request $request): array
    {
        $event->ensureSponsorPackages();
        $packages = $event->sponsorPackages()->get();

        $only = $request->query('package');
        $single = $only ? $packages->firstWhere('name', $only) : null;
        $shown = $single ? collect([$single]) : $packages;

        return [
            'event' => $event,
            'theme' => $event->theme(),
            'packages' => $shown,
            'single' => $single,
            'soldByPackage' => $event->sponsors()->selectRaw('package, count(*) as c')->groupBy('package')->pluck('c', 'package'),
        ];
    }

    public function show(Event $event, Request $request)
    {
        return view('events.sponsorship', $this->data($event, $request));
    }

    public function pdf(Event $event, Request $request)
    {
        $data = $this->data($event, $request);

        $suffix = $data['single'] ? '-'.str($data['single']->name)->slug() : '';
        $pdf = Pdf::loadView('events.sponsorship-pdf', $data)->setPaper('a4', 'portrait');

        return $pdf->download(str($event->name)->slug().'-sponsorship'.$suffix.'.pdf');
    }
}
