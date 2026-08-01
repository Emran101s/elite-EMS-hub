<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\CompanyProfile;
use App\Models\Invoice;
use Illuminate\Http\Response;

/**
 * The invoice as a document — the thing that actually leaves the building.
 *
 * Rendered by headless Chrome from the same partial the editor previews, so
 * the export IS the preview, and so a "5★" or an Arabic item name survives the
 * trip. DomPDF's core fonts are Latin-1 and printed both as "?".
 *
 * It takes the event's theme where there is an event and the house colours
 * where there is not: a deposit raised before the event exists is still an EBH
 * invoice.
 */
class InvoicePdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Invoice $invoice): Response
    {
        $invoice->load(['lines', 'event.client', 'client', 'contract']);

        $company = CompanyProfile::first();

        $html = view('invoices.pdf', [
            'invoice' => $invoice,
            'theme' => $invoice->event?->theme() ?? [
                'primary' => '#0B1F3A', 'secondary' => '#F8FAFC',
                'accent' => '#D4AF37', 'text' => '#0F172A',
            ],
            'company' => [
                'name' => $company->name ?? config('app.name'),
                'address' => $company?->address,
                'email' => $company?->email,
                'phone' => $company?->phone,
            ],
        ])->render();

        return response($this->chrome($html)->format('A4')->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$invoice->number.'.pdf"',
        ]);
    }
}
