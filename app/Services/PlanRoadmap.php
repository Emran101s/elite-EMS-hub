<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventPlanItem;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Roadmap geometry for the plan Gantt — shared by the Planning tab (screen)
 * and the Planning PDF (headless Chrome), so the two can never drift apart.
 */
class PlanRoadmap
{
    /** Date range, month ticks, TODAY line and Event-Day anchor. */
    public function axis(Event $event, Collection $items): array
    {
        $today = Carbon::today();
        $eventDay = $event->starts_at ? $event->starts_at->copy()->startOfDay() : null;

        $marks = collect([$today]);
        if ($eventDay) {
            $marks->push($eventDay);
        }
        foreach ($items as $i) {
            if ($i->starts_on) {
                $marks->push($i->starts_on);
            }
            if ($i->due_on) {
                $marks->push($i->due_on);
            }
        }

        $min = $marks->min()->copy()->startOfMonth();
        $max = $marks->max()->copy()->endOfMonth();
        if ($min->diffInDays($max) < 30) {
            $max = $min->copy()->addMonths(3)->endOfMonth();
        }

        $minTs = $min->timestamp;
        $spanSec = max($max->timestamp - $minTs, 86400 * 30);
        $pos = fn ($d) => round(($d->copy()->startOfDay()->timestamp - $minTs) / $spanSec * 100, 3);

        $ticks = [];
        for ($m = $min->copy(); $m <= $max; $m->addMonth()) {
            $ticks[] = ['label' => $m->format('M'), 'sub' => $m->format('Y'), 'left' => $pos($m)];
        }

        return [
            'ticks' => $ticks,
            'minDate' => $min->format('Y-m-d'),
            'minTs' => $minTs,
            'spanDays' => (int) round($spanSec / 86400),
            'eventLeft' => $eventDay ? $pos($eventDay) : null,
            'eventDay' => $eventDay,
            'todayLeft' => $pos($today),
            'todayIn' => $today->between($min, $max),
        ];
    }

    /** Bar geometry for one item, or null when it has no dates. */
    public function geo(EventPlanItem $item, array $axis): ?array
    {
        if (! $item->starts_on && ! $item->due_on) {
            return null;
        }

        $s = ($item->starts_on ?? $item->due_on)->copy()->startOfDay();
        $e = ($item->due_on ?? $item->starts_on)->copy()->startOfDay();
        $span = max(1, $axis['spanDays']);
        $ms = $s->equalTo($e);
        [, $hex] = EventPlanItem::STATUS_BAR[$item->status] ?? EventPlanItem::STATUS_BAR['todo'];

        return [
            'left' => round(max(0, min(99, ($s->timestamp - $axis['minTs']) / 86400 / $span * 100)), 2),
            'width' => $ms ? 0 : round(max(1.5, ($e->timestamp - $s->timestamp) / 86400 / $span * 100), 2),
            'ms' => $ms,
            'hex' => $hex,
        ];
    }

    /**
     * phases → top-level items → recursively nested children, each with bar geometry,
     * plus per-phase done/total/pct.
     */
    public function tree(Collection $items, Collection $phases, array $axis): Collection
    {
        $childrenBy = $items->whereNotNull('parent_id')->groupBy('parent_id');
        $top = $items->whereNull('parent_id');

        $node = function (EventPlanItem $item) use (&$node, $childrenBy, $axis) {
            $kids = ($childrenBy[$item->id] ?? collect())->sortBy('sort_order')->values();

            return [
                'item' => $item,
                'geo' => $this->geo($item, $axis),
                'children' => $kids->map(fn ($c) => $node($c)),
                'childTotal' => $kids->count(),
                'childDone' => $kids->where('status', 'done')->count(),
            ];
        };

        $flatten = function (array $n) use (&$flatten): array {
            return array_merge([$n['item']], ...array_map($flatten, $n['children']->all()));
        };

        return $phases->map(function ($phase) use ($top, $node, $flatten) {
            $tasks = $top->where('category_id', $phase->id)->sortBy('sort_order')->values()->map(fn ($t) => $node($t));
            $all = collect($tasks->flatMap(fn ($n) => $flatten($n)));
            $total = $all->count();
            $done = $all->where('status', 'done')->count();

            return [
                'phase' => $phase,
                'tasks' => $tasks,
                'total' => $total,
                'done' => $done,
                'pct' => $total ? (int) round($done / $total * 100) : 0,
            ];
        })->values();
    }

    /** Headline numbers for the countdown header. */
    public function stats(Event $event, Collection $items): array
    {
        $today = Carbon::today();
        $total = $items->count();
        $done = $items->where('status', 'done')->count();
        $eventDay = $event->starts_at ? $event->starts_at->copy()->startOfDay() : null;

        return [
            'total' => $total,
            'done' => $done,
            'overdue' => $items->filter(fn ($i) => $i->due_on && $i->status !== 'done' && $i->due_on->copy()->startOfDay()->lt($today))->count(),
            'progress' => $total > 0 ? (int) round($done / $total * 100) : 0,
            'daysToEvent' => $eventDay ? (int) round(($eventDay->timestamp - $today->timestamp) / 86400) : null,
            'eventDay' => $eventDay,
        ];
    }
}
