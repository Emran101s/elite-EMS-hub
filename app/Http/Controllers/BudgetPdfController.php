<?php

namespace App\Http\Controllers;

use App\Models\Event;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class BudgetPdfController extends Controller
{
    public function __invoke(Event $event): Response
    {
        $items = $event->budgetItems()->orderBy('category')->orderBy('id')->get();

        $pdf = Pdf::loadView('events.budget-pdf', [
            'event' => $event,
            'theme' => $event->theme(),
            'items' => $items,
        ])->setPaper('a4', 'portrait');

        return $pdf->download(str($event->name)->slug().'-budget.pdf');
    }
}
