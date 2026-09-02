<?php

namespace App\Support;

use App\Models\Event;
use App\Models\EventScopeItem;
use App\Models\Task;

/**
 * What state a scope deliverable is in — read from whatever already owns the
 * answer, never stored on the deliverable itself.
 *
 * This is the one rule the module is built around. Every recurring defect in
 * this platform has been the same shape: a screen asserting a figure a
 * different screen already owned, the two drifting, and nobody able to say
 * which was right. The Delivery Scope is the page people are held to, so it
 * is the worst possible place to keep a second copy of a truth.
 *
 * So there is no status column. A deliverable names a SOURCE, and the source
 * is asked. A deliverable with no source falls back to a linked task, and one
 * with neither is honestly reported as unmeasured rather than as "not started"
 * — an unknown is not a zero.
 */
final class ScopeStatus
{
    /** @var array<string,string> source key => human label, for the picker */
    public const SOURCES = [
        'suppliers_contracted' => 'Suppliers contracted',
        'programme_confirmed' => 'Programme confirmed',
        'speakers_confirmed' => 'Speakers confirmed',
        'venue_secured' => 'Venue secured',
        'rooms_released' => 'Room blocks resolved',
        'approvals_cleared' => 'Approvals cleared',
        'task' => 'A specific task',
    ];

    public const MET = 'met';

    public const PARTIAL = 'partial';

    public const OPEN = 'open';

    public const UNMEASURED = 'unmeasured';

    /**
     * @return array{state:string, note:string}
     */
    public static function for(EventScopeItem $item): array
    {
        $event = $item->event;

        if (! $event || $item->source_type === null) {
            return self::unmeasured();
        }

        return match ($item->source_type) {
            'suppliers_contracted' => self::suppliers($event),
            'programme_confirmed' => self::programme($event),
            'speakers_confirmed' => self::speakers($event),
            'venue_secured' => self::venue($event),
            'rooms_released' => self::rooms($event),
            'approvals_cleared' => self::approvals($event),
            'task' => self::task($item),
            default => self::unmeasured(),
        };
    }

    public static function isMet(EventScopeItem $item): bool
    {
        return self::for($item)['state'] === self::MET;
    }

    private static function unmeasured(): array
    {
        // Deliberately not "open". A deliverable nobody has wired to a source
        // has not been assessed, and reporting it as outstanding would put a
        // number on the page that means nothing.
        return ['state' => self::UNMEASURED, 'note' => 'No source — track it by hand'];
    }

    private static function counted(int $done, int $total, string $noun, string $verb): array
    {
        if ($total === 0) {
            return ['state' => self::UNMEASURED, 'note' => 'Nothing to '.$verb.' yet'];
        }

        if ($done >= $total) {
            return ['state' => self::MET, 'note' => 'All '.$total.' '.str($noun)->plural($total)->toString()];
        }

        return [
            'state' => $done > 0 ? self::PARTIAL : self::OPEN,
            'note' => $done.' of '.$total.' '.str($noun)->plural($total)->toString(),
        ];
    }

    private static function suppliers(Event $event): array
    {
        $done = ['contracted', 'in_production', 'delivered', 'completed'];
        $all = $event->suppliers;

        return self::counted(
            $all->filter(fn ($s) => in_array($s->pivot->status, $done, true))->count(),
            $all->count(), 'supplier', 'contract'
        );
    }

    private static function programme(Event $event): array
    {
        $sessions = $event->agendaSessions;

        return self::counted($sessions->filter->isSettled()->count(), $sessions->count(), 'session', 'confirm');
    }

    private static function speakers(Event $event): array
    {
        $all = $event->speakers;

        return self::counted($all->where('status', 'confirmed')->count(), $all->count(), 'speaker', 'confirm');
    }

    private static function venue(Event $event): array
    {
        return $event->venue_id
            ? ['state' => self::MET, 'note' => $event->venue?->name ?? 'Venue assigned']
            : ['state' => self::OPEN, 'note' => 'No venue set'];
    }

    private static function rooms(Event $event): array
    {
        $blocks = $event->roomBlocks ?? collect();

        if ($blocks->isEmpty()) {
            return ['state' => self::UNMEASURED, 'note' => 'No room blocks held'];
        }

        // A held block with no release date is the open question, and the one
        // the Stay tab already prompts for.
        $undated = $blocks->filter(fn ($b) => $b->status === 'held' && $b->cutoff_on === null)->count();

        return $undated === 0
            ? ['state' => self::MET, 'note' => $blocks->count().' '.str('block')->plural($blocks->count()).' resolved']
            : ['state' => self::OPEN, 'note' => $undated.' with no release date'];
    }

    private static function approvals(Event $event): array
    {
        $pending = $event->approvals->where('status', 'pending')->count();

        return $pending === 0
            ? ['state' => self::MET, 'note' => 'Nothing pending']
            : ['state' => self::OPEN, 'note' => $pending.' awaiting a decision'];
    }

    private static function task(EventScopeItem $item): array
    {
        $task = $item->source_id ? Task::find($item->source_id) : null;

        if (! $task) {
            return ['state' => self::UNMEASURED, 'note' => 'Linked task is gone'];
        }

        // Task::STAGES' own open flag, so "approved" counts as open here for
        // the same reason it does everywhere else: approved to proceed is not
        // delivered.
        $open = Task::STAGES[$task->status][2] ?? true;

        return $open
            ? ['state' => $task->status === 'todo' ? self::OPEN : self::PARTIAL, 'note' => $task->stageLabel()]
            : ['state' => $task->status === 'done' ? self::MET : self::OPEN, 'note' => $task->stageLabel()];
    }
}
