<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventDocument;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Documents live on the private disk, so every read goes through here rather
 * than a public URL — the event check stops a document id from one event being
 * fetched under another.
 */
class EventDocumentController extends Controller
{
    public function download(Event $event, EventDocument $document): StreamedResponse
    {
        $this->guard($event, $document);

        return Storage::disk($document->disk)->download($document->path, $this->filename($document));
    }

    /** Same file, shown in the browser — only for types a browser can render. */
    public function view(Event $event, EventDocument $document): StreamedResponse
    {
        $this->guard($event, $document);
        abort_unless($document->isViewable(), 404);

        return Storage::disk($document->disk)->response(
            $document->path,
            $this->filename($document),
            ['Content-Type' => $document->mime ?: 'application/octet-stream']
        );
    }

    private function guard(Event $event, EventDocument $document): void
    {
        abort_unless($document->event_id === $event->id, 404);
        abort_unless(Storage::disk($document->disk)->exists($document->path), 404);
    }

    /** Downloads carry the human name, keeping the original extension. */
    private function filename(EventDocument $document): string
    {
        $ext = pathinfo($document->original_name, PATHINFO_EXTENSION);

        return \Illuminate\Support\Str::slug($document->name).($ext ? '.'.$ext : '');
    }
}
