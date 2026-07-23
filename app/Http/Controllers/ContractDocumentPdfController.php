<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventContract;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * The type-aware document PDF: vendor and sponsorship agreements, speaker
 * agreements and letters, rendered through the same Chrome pipeline and house
 * identity as the client contract. The client MSA keeps its richer template.
 */
class ContractDocumentPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, EventContract $contract): Response
    {
        abort_unless($contract->event_id === $event->id, 404);

        $contract->ensureSignatories();

        // The shared paper: the exact partial the live preview renders, with the
        // same compiled CSS — so the export matches the preview one for one.
        $html = view('event-contract.paper-pdf', [
            'event' => $event,
            'contract' => $contract,
            'signatories' => $contract->signatories()->get(),
            'css' => $this->compiledCss(),
        ])->render();

        return response($this->chrome($html)->format('A4')->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.Str::slug($contract->displayTitle()).'.pdf"',
        ]);
    }
}
