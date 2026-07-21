<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

/**
 * Renders the Plan Studio plan to PDF with headless Chrome, reusing the app's
 * compiled Tailwind CSS so the export carries the same brand as the screen.
 */
class PlanStudioPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event): Response
    {
        $event->ensurePlanTracks();

        $tracks = $event->planTracks()
            ->with(['items' => fn ($q) => $q->orderBy('position')->orderBy('id'), 'items.subtasks', 'items.owners', 'items.approver'])
            ->get();

        $html = view('events.plan-studio-pdf', [
            'event' => $event,
            'tracks' => $tracks,
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.Str::slug($event->name ?: 'event').'-plan.pdf"',
        ]);
    }
}
