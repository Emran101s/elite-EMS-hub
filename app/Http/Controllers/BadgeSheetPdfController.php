<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Models\EventAttendee;
use App\Support\Badge;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Badges, laid out to fill A4 with cut lines.
 *
 * Rendered by headless Chrome from the same partial the designer previews, so
 * what was designed on screen is what comes out of the printer — the rule the
 * brief and the contract documents already follow.
 */
class BadgeSheetPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Request $request, Event $event): Response
    {
        $attendees = $event->attendees()
            ->where('status', '!=', 'cancelled')
            // Print a selection, or the lot. A door team prints the lot on
            // Friday and the stragglers one at a time on Saturday.
            ->when($request->filled('ids'), fn ($q) => $q->whereIn('id', explode(',', (string) $request->query('ids'))))
            ->orderBy('name')
            ->get();

        abort_if($attendees->isEmpty(), 404, 'Nobody to print a badge for yet.');

        $template = Badge::template($event);

        // Built once here rather than in the view: a thousand badges is a
        // thousand QR encodes, and the view should not be doing that work.
        $qrs = $template['show_qr']
            ? $attendees->mapWithKeys(fn (EventAttendee $a) => [$a->id => Badge::qr(Badge::checkInUrl($a))])->all()
            : [];

        $html = view('events.badge-sheet', [
            'event' => $event,
            'attendees' => $attendees,
            'template' => $template,
            'qrs' => $qrs,
            // Chrome fetches nothing, so the logo travels with the document.
            'logoSrc' => $this->inlineLogo($event),
        ])->render();

        // A landscape badge on a landscape page fits twice as many.
        [$w, $h] = Badge::dimensions($event);
        $shot = $this->chrome($html)->format('A4');

        if ($w > $h) {
            $shot->landscape();
        }

        $pdf = $shot->pdf();

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.str($event->name)->slug().'-badges.pdf"',
        ]);
    }

    /** The event logo as a data URI, or null when there is not one to find. */
    private function inlineLogo(Event $event): ?string
    {
        if (! $event->logo_path) {
            return null;
        }

        $path = public_path(ltrim($event->logo_path, '/'));

        if (! is_file($path)) {
            return null;
        }

        return 'data:'.(mime_content_type($path) ?: 'image/png').';base64,'.base64_encode(file_get_contents($path));
    }
}
