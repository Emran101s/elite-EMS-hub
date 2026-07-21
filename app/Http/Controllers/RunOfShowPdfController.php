<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\RendersChromePdf;
use App\Models\Event;
use Illuminate\Http\Response;

/**
 * The Run of Show — the cue sheet the team holds on the day. Chronological,
 * every room, with durations, venue-wide turnarounds and the crew calls the
 * delegate programme deliberately hides.
 *
 * ?day={id} prints one day's sheet (the normal case — you hold one day).
 */
class RunOfShowPdfController extends Controller
{
    use RendersChromePdf;

    public function __invoke(Event $event): Response
    {
        $event->load([
            'venue',
            'agendaDays' => fn ($q) => $q->orderBy('sort'),
            'agendaDays.sessions' => fn ($q) => $q->orderBy('starts_at'),
            'agendaDays.sessions.room',
            'agendaDays.sessions.speakers',
        ]);

        $agendaDays = $event->agendaDays;
        $single = $agendaDays->firstWhere('id', request()->integer('day'));
        if ($single) {
            $agendaDays = collect([$single]);
        }

        $days = $agendaDays->map(fn ($d) => [
            'day' => $d,
            'rows' => $this->cues($d->sessions),
        ])->all();

        $html = view('events.run-of-show-pdf', [
            'event' => $event,
            'days' => $days,
            'single' => $single,
            'css' => $this->compiledCss(),
        ])->render();

        $pdf = $this->chrome($html)->format('A4')->landscape()->pdf();

        $name = str($event->name)->slug().'-run-of-show'
            .($single ? '-'.str($single->date?->format('M-j') ?? $single->label)->slug() : '').'.pdf';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    /**
     * Chronological cue rows, with a turnaround row wherever the whole venue
     * goes quiet — the gaps are where show-day problems actually happen.
     */
    private function cues($sessions): array
    {
        $min = fn ($t) => (int) substr((string) $t, 0, 2) * 60 + (int) substr((string) $t, 3, 2);
        $fmt = fn (int $m) => sprintf('%02d:%02d', intdiv($m, 60), $m % 60);
        $len = function (int $m) {
            return $m >= 60 ? intdiv($m, 60).'h'.($m % 60 ? ' '.($m % 60).'m' : '') : $m.'m';
        };

        $ordered = $sessions->sortBy(fn ($s) => $min($s->starts_at))->values();
        $rows = [];
        $venueClear = null;   // latest end seen so far

        foreach ($ordered as $s) {
            $start = $min($s->starts_at);
            $end = $min($s->ends_at);

            // A gap only counts when nothing at all is running.
            if ($venueClear !== null && $start > $venueClear) {
                $rows[] = [
                    'gap' => true,
                    'time' => $fmt($venueClear).'–'.$fmt($start),
                    'length' => $len($start - $venueClear),
                ];
            }

            $rows[] = [
                'gap' => false,
                'session' => $s,
                'time' => $fmt($start).'–'.$fmt($end),
                'length' => $len(max($end - $start, 0)),
                'crew' => in_array($s->track, \App\Services\AgendaProgram::CREW_ONLY, true),
            ];

            $venueClear = max($venueClear ?? $end, $end);
        }

        return $rows;
    }
}
