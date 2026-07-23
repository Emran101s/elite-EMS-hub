<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use App\Services\AgendaProgram;
use Illuminate\Http\Response;

/**
 * Renders the agenda "programme board" to PDF with headless Chrome — the exact
 * same <x-agenda-program> component the Agenda tab shows on screen, across every
 * day, so the export matches the screen precisely.
 */
class AgendaProgramPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event, AgendaProgram $program): Response
    {
        $event->load([
            'agendaDays' => fn ($q) => $q->orderBy('date'),
            'agendaDays.sessions' => fn ($q) => $q->orderBy('starts_at'),
            'agendaDays.sessions.room',
            'agendaDays.sessions.speakers',
        ]);

        // ?day={id} prints that single day; otherwise the whole programme.
        $agendaDays = $event->agendaDays;
        $single = $agendaDays->firstWhere('id', request()->integer('day'));
        if ($single) {
            $agendaDays = collect([$single]);
        }

        $audience = isset(AgendaProgram::AUDIENCES[request()->string('audience')->toString()])
            ? request()->string('audience')->toString()
            : 'internal';

        $days = $agendaDays->map(fn ($d) => [
            'label' => $d->label,
            'date' => $d->date?->format('D · M j') ?? '',
            'program' => $program->forDay($d->sessions, $audience),
        ])
            // A public programme drops days that are pure build/crew time.
            ->reject(fn ($d) => $audience === 'public' && AgendaProgram::isEmpty($d['program']))
            ->values()->all();

        $html = view('events.agenda-program-pdf', [
            'event' => $event,
            'days' => $days,
            'single' => $single,
            'audience' => $audience,
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->pdf();

        $name = str($event->name)->slug().'-programme'
            .($audience === 'public' ? '-public' : '')
            .($single ? '-'.str($single->date?->format('M-j') ?? $single->label)->slug() : '').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }
}
