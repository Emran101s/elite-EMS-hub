<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventContract;
use Illuminate\Http\Response;

/**
 * Renders the bilingual (EN/AR) Event Management Services Agreement to a
 * premium, signable PDF via headless Chrome.
 */
class EventContractPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event): Response
    {
        $contract = EventContract::forEvent($event);
        $contract->ensureSignatories();

        // The export IS the live preview: the same shared paper partial, the same
        // compiled CSS, the same fonts — paginated onto A4. One source of truth.
        $html = view('event-contract.paper-pdf', [
            'event' => $event,
            'contract' => $contract,
            'signatories' => $contract->signatories()->get(),
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$contract->slug().'.pdf"',
        ]);
    }
}
