<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\GeneratesXlsxTemplate;
use App\Models\Event;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ready-to-fill Excel template matching the columns the AttendeesTab importer
 * recognises. Re-importing a filled sheet upserts by email (then name).
 */
class AttendeeTemplateController extends Controller
{
    use GeneratesXlsxTemplate;

    public function __invoke(Event $event): StreamedResponse
    {
        return $this->xlsxTemplate(
            headers: ['Name', 'Email', 'Organization', 'Job Title', 'Phone', 'Ticket Type', 'Amount Paid', 'VIP', 'Dietary', 'Notes'],
            example: [
                ['Layla Odeh', 'layla@example.com', 'Ministry of Culture', 'Director', '+962 79 000 0000', 'VIP', '150', 'Yes', 'Vegetarian', 'Keynote guest'],
                ['Omar Nassar', 'omar@example.com', 'Acme Corp', 'Manager', '', 'Delegate', '', 'No', '', ''],
            ],
            sheetTitle: 'Attendees',
            filename: str($event->name)->slug().'-attendees-template.xlsx',
            widths: [22, 26, 24, 18, 18, 14, 13, 8, 18, 28],
        );
    }
}
