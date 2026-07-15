<?php

namespace App\Http\Controllers;

use App\Models\Requirement;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

class EquipmentPdfController extends Controller
{
    public function __invoke(): Response
    {
        $items = Requirement::orderBy('name')->get();

        $pdf = Pdf::loadView('equipment-pdf', [
            'items' => $items,
            'total' => $items->sum('unit_price_cents'),
        ])->setPaper('a4', 'portrait');

        return $pdf->download('equipment-catalog.pdf');
    }
}
