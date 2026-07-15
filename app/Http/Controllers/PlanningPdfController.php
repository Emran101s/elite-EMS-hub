<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Services\PlanRoadmap;
use Illuminate\Http\Response;

/**
 * Renders the plan Gantt to PDF with headless Chrome using the same roadmap
 * geometry (PlanRoadmap) and compiled Tailwind CSS as the Planning tab — so the
 * printed plan matches the screen: countdown header, month axis, TODAY / EVENT
 * markers, phase bands and every bar. Unlike the screen it prints fully expanded.
 */
class PlanningPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, PlanRoadmap $roadmap): Response
    {
        $event->ensurePlanCategories();

        $items = $event->planItems()->with('owners')->get();
        $phases = $event->planCategories()->get();
        $axis = $roadmap->axis($event, $items);

        $html = view('events.planning-pdf', [
            'event' => $event,
            'roadmap' => $axis,
            'planTree' => $roadmap->tree($items, $phases, $axis),
            'stats' => $roadmap->stats($event, $items),
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->landscape()->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.str($event->name)->slug().'-plan.pdf"',
        ]);
    }
}
