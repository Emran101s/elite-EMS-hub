<?php

namespace App\Http\Controllers;

use App\Models\CompanyProfile;
use App\Models\Proposal;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;

/**
 * The offer as a document — the thing the client actually reads.
 *
 * House colours rather than an event's theme: at proposal stage there is no
 * event yet, and the one thing the document has to look like is the company.
 */
class ProposalPdfController extends Controller
{
    public function __invoke(Proposal $proposal): Response
    {
        $proposal->load(['lines', 'client', 'contact', 'owner', 'deal']);

        $company = CompanyProfile::first();

        $pdf = Pdf::loadView('proposals.pdf', [
            'proposal' => $proposal,
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'],
            'company' => [
                'name' => $company->name ?? config('app.name'),
                'address' => $company?->address,
                'email' => $company?->email,
                'phone' => $company?->phone,
            ],
        ])->setPaper('a4', 'portrait');

        return $pdf->download($proposal->number.'.pdf');
    }
}
