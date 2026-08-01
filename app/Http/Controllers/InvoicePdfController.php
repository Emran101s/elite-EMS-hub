<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * The invoice as a document — the thing that actually leaves the building.
 *
 * It takes the event's theme where there is an event, so an invoice looks like
 * the rest of that project's paperwork, and the house colours where there is
 * not: a deposit raised before the event exists is still an EBH invoice.
 */
class InvoicePdfController extends Controller
{
    public function __invoke(Invoice $invoice): Response
    {
        $invoice->load(['lines', 'event.client', 'client', 'contract']);

        $company = CompanyProfile::first();

        $pdf = Pdf::loadView('invoices.pdf', [
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
        ])->setPaper('a4', 'portrait');

        return $pdf->download($invoice->number.'.pdf');
    }
}
