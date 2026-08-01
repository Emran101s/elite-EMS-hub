<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\CompanyProfile;
use App\Models\Proposal;
use Illuminate\Http\Response;

/**
 * The offer as a document — the thing the client actually reads.
 *
 * Rendered by headless Chrome from the same partial the editor previews, so
 * the export IS the preview, and so a "5★" or an Arabic item name survives the
 * trip. DomPDF's core fonts are Latin-1 and printed both as "?".
 *
 * House colours: at proposal stage there is no event yet, and the one thing
 * the document has to look like is the company.
 */
class ProposalPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Proposal $proposal): Response
    {
        $proposal->load(['lines', 'client', 'contact', 'owner', 'deal']);

        $company = CompanyProfile::first();

        $html = view('proposals.pdf', [
            'proposal' => $proposal,
            'theme' => ['primary' => '#0B1F3A', 'accent' => '#D4AF37'],
            'company' => [
                'name' => $company->name ?? config('app.name'),
                'address' => $company?->address,
                'email' => $company?->email,
                'phone' => $company?->phone,
            ],
        ])->render();

        return response($this->chrome($html)->format('A4')->pdf(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$proposal->number.'.pdf"',
        ]);
    }
}
