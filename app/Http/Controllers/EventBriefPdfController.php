<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventBrief;
use Illuminate\Http\Response;

/**
 * Renders the Event Brief to PDF with headless Chrome from the SAME markup +
 * compiled Tailwind CSS as the on-screen dossier, so the export is identical.
 */
class EventBriefPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event): Response
    {
        $brief = EventBrief::forEvent($event);

        // The export IS the live preview: the same shared paper partial, the
        // same compiled CSS — paginated onto A4. One source of truth.
        $html = view('event-brief.paper-pdf', [
            'event' => $event,
            'brief' => $brief,
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$brief->slug().'.pdf"',
        ]);
    }
}
