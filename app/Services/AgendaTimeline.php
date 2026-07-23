<?php

namespace App\Services;

use App\Livewire\Hub\AgendaTab;
use Illuminate\Support\Collection;

/**
 * Builds the agenda "timeline" geometry — room lanes with time-positioned
 * blocks — for a set of sessions. Shared by the on-screen Agenda tab and the
 * Timeline PDF export so the printed drawing matches the screen exactly.
 */
class AgendaTimeline
{
    /**
     * @return array{hours: array, lanes: Collection, startMin: int, span: int}|null
     */
    public function forSessions(Collection $sessions): ?array
    {
        if ($sessions->isEmpty()) {
            return null;
        }

        $toMin = fn (string $t) => (int) substr($t, 0, 2) * 60 + (int) substr($t, 3, 2);
        $startMin = (int) (floor($sessions->min(fn ($s) => $toMin($s->starts_at)) / 60) * 60);
        $endMin = (int) (ceil($sessions->max(fn ($s) => $toMin($s->ends_at)) / 60) * 60);
        $startMin = min($startMin, $endMin - 60);
        $span = max($endMin - $startMin, 60);

        $hours = [];
        for ($m = $startMin; $m <= $endMin; $m += 60) {
            $hours[] = ['label' => sprintf('%02d:00', intdiv($m, 60)), 'left' => round(($m - $startMin) / $span * 100, 3)];
        }

        $lanes = $sessions->groupBy(fn ($s) => $s->room?->name ?? 'Unassigned')->map(fn ($group, $room) => [
            'room' => $room,
            'room_id' => $group->first()->room_id,
            'blocks' => $group->map(function ($s) use ($toMin, $startMin, $span) {
                [$legend, $hex] = AgendaTab::PALETTE[$s->type] ?? ['Session', '#3B82F6'];
                $sMin = $toMin($s->starts_at);
                $dMin = $toMin($s->ends_at) - $sMin;

                return [
                    'session' => $s,
                    'left' => round(($sMin - $startMin) / $span * 100, 3),
                    'width' => round(max($dMin, 15) / $span * 100, 3),
                    'startMin' => $sMin,
                    'durMin' => $dMin,
                    'hex' => $hex, 'legend' => $legend,
                ];
            })->values(),
        ])->values();

        return ['hours' => $hours, 'lanes' => $lanes, 'startMin' => $startMin, 'span' => $span];
    }
}
