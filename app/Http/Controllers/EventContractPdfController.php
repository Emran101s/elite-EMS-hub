<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventContract;
use App\Support\ContractClauses;
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
        $data = $contract->data;

        $html = view('event-contract.contract', [
            'event' => $event,
            'contract' => $contract,
            'data' => $data,
            'recitals' => ContractClauses::recitals($data),
            'clauses' => ContractClauses::clauses($data),
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$contract->slug().'.pdf"',
        ]);
    }
}
