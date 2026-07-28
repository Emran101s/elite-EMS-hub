<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventContractPayment;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * An event's life, in five phases.
 *
 * The board and the wall both answer "how is it going" with one number. That
 * number cannot say WHERE an event is — a 60% that has not started production
 * and a 60% that is three days from doors are the same figure describing two
 * completely different situations.
 *
 * So: five phases, each with its own completion, each computed from records
 * rather than from the stage field. The stage says what somebody last clicked;
 * these say what is actually done.
 *
 *   Opportunity   is it ours          committed, or still being bid
 *   Planning      is it designed      brief · contract · budget · agenda
 *   Production    is it built         tasks · suppliers · venue · transport
 *   Live          is it happening     the days themselves
 *   Post event    is it closed        approvals cleared · money settled
 *
 * A phase is never marked in-progress before the one before it is done, so the
 * track always reads left to right.
 */
class EventJourney
{
    /** The five phases, in the order they happen. */
    public const PHASES = [
        'opportunity' => ['Opportunity', 'Identify & Evaluate', '#D4AF37'],
        'planning' => ['Planning', 'Design & Plan', '#3B82F6'],
        'production' => ['Production', 'Build & Prepare', '#8B5CF6'],
        'live' => ['Live', 'Execute & Deliver', '#10B981'],
        'post' => ['Post event', 'Review & Close', '#94A3B8'],
    ];

    /** Relations the phases read. Eager-load these or pay per event. */
    public const RELATIONS = [
        'tasks', 'suppliers', 'venue', 'transport', 'budgetItems',
        'agendaDays', 'agendaSessions', 'approvals', 'brief', 'contract',
    ];

    /**
     * The whole track for one event.
     *
     * @return array<int,array{key:string,label:string,note:string,hex:string,pct:int,state:string,word:string}>
     */
    public function for(Event $event): array
    {
        $raw = [
            'opportunity' => $this->opportunity($event),
            'planning' => $this->planning($event),
            'production' => $this->production($event),
            'live' => $this->live($event),
            'post' => $this->post($event),
        ];

        // The first phase that is not finished is the one you are in. Everything
        // after it is upcoming, whatever its own arithmetic says — a production
        // score means nothing on an event that is not yet won.
        $current = null;
        foreach ($raw as $key => $pct) {
            if ($pct < 100) {
                $current = $key;
                break;
            }
        }

        $reached = true;
        $out = [];

        foreach (self::PHASES as $key => [$label, $note, $hex]) {
            $pct = $raw[$key];

            [$state, $word] = match (true) {
                $pct >= 100 => ['completed', 'Completed'],
                $key === $current && $key === 'live' => ['live', 'Live now'],
                $key === $current && $pct > 0 => ['in_progress', 'In Progress'],
                $key === $current => ['in_progress', 'Not started'],
                ! $reached => [$key === 'post' ? 'pending' : 'upcoming', $key === 'post' ? 'Pending' : 'Upcoming'],
                default => ['upcoming', 'Upcoming'],
            };

            if ($key === $current) {
                $reached = false;
            }

            $out[] = [
                'key' => $key, 'label' => $label, 'note' => $note, 'hex' => $hex,
                // A phase you have not reached shows nothing rather than a score
                // it would be misleading to have earned.
                'pct' => in_array($state, ['upcoming', 'pending'], true) ? 0 : $pct,
                'state' => $state,
                'word' => $word,
            ];
        }

        return $out;
    }

    /** Is it ours? Committed beats bidding; a draft is barely a lead. */
    private function opportunity(Event $event): int
    {
        return match ($event->stage) {
            'draft' => 20,
            'proposal' => 60,
            default => 100,
        };
    }

    /** Is it designed? The four documents an event cannot be built without. */
    private function planning(Event $event): int
    {
        $gates = [
            $event->brief ? 100 : 0,
            $event->contract ? ($event->contract->status === 'signed' ? 100 : 50) : 0,
            ($event->budget_cents > 0 || $event->budgetItems->isNotEmpty()) ? 100 : 0,
            $event->agendaSessions->isNotEmpty() ? 100 : ($event->agendaDays->isNotEmpty() ? 50 : 0),
        ];

        return (int) round(array_sum($gates) / count($gates));
    }

    /** Is it built? What has to be true before anybody arrives. */
    private function production(Event $event): int
    {
        $gates = [];

        $tasks = $event->tasks->where('status', '!=', 'cancelled');
        if ($tasks->isNotEmpty()) {
            $gates[] = (int) round($tasks->whereIn('status', ['done', 'approved'])->count() / $tasks->count() * 100);
        }

        if ($event->suppliers->isNotEmpty()) {
            $signed = $event->suppliers->reject(fn ($s) => in_array($s->pivot->status, ['requested', 'quoted', 'issue'], true))->count();
            $gates[] = (int) round($signed / $event->suppliers->count() * 100);
        }

        $gates[] = $event->venue_id ? 100 : 0;

        $movements = $event->transport->reject(fn ($m) => in_array($m->status, ['cancelled'], true));
        if ($movements->isNotEmpty()) {
            $gates[] = (int) round($movements->filter->isReady()->count() / $movements->count() * 100);
        }

        return $gates ? (int) round(array_sum($gates) / count($gates)) : 0;
    }

    /** Is it happening? The only phase measured in days rather than records. */
    private function live(Event $event): int
    {
        if (! $event->starts_at) {
            return 0;
        }

        $today = Carbon::today();
        $start = $event->starts_at->copy()->startOfDay();
        $end = ($event->ends_at ?? $event->starts_at)->copy()->startOfDay();

        if ($today->lt($start)) {
            return 0;
        }

        if ($today->gt($end)) {
            return 100;
        }

        $total = max(1, $start->diffInDays($end) + 1);

        return (int) round(($start->diffInDays($today) + 1) / $total * 100);
    }

    /** Is it closed? Nothing to close until the doors have shut. */
    private function post(Event $event): int
    {
        $end = ($event->ends_at ?? $event->starts_at)?->copy()->endOfDay();

        if (! $end || $end->isFuture()) {
            return 0;
        }

        $gates = [$event->approvals->where('status', 'pending')->isEmpty() ? 100 : 0];

        $payments = EventContractPayment::where('event_id', $event->id)->get();
        if ($payments->isNotEmpty()) {
            $due = $payments->sum('amount_cents');
            $paid = $payments->sum('paid_cents');
            $gates[] = $due > 0 ? min(100, (int) round($paid / $due * 100)) : 100;
        }

        return (int) round(array_sum($gates) / count($gates));
    }

    /**
     * Where the book is in the world.
     *
     * Grouped rather than plotted: five regions is something you can read, and
     * a pin per event on a world map at this size is a smudge.
     *
     * @return Collection<int,array{key:string,label:string,count:int,x:float,y:float}>
     */
    public function regions(Collection $events): Collection
    {
        // x/y are percentages on the panel's own field, laid out west to east.
        $regions = [
            'americas' => ['Americas', 12, 52, ['USA', 'United States', 'Canada', 'Mexico', 'Brazil', 'Argentina']],
            'europe' => ['Europe', 40, 26, ['UK', 'United Kingdom', 'France', 'Germany', 'Spain', 'Italy', 'Netherlands', 'Switzerland', 'Sweden', 'Portugal', 'Greece', 'Turkey']],
            'africa' => ['Africa', 42, 76, ['Egypt', 'Morocco', 'Tunisia', 'Algeria', 'Libya', 'Sudan', 'Kenya', 'Nigeria', 'South Africa', 'Ethiopia']],
            'middle_east' => ['Middle East', 62, 54, ['Jordan', 'Bahrain', 'UAE', 'Qatar', 'KSA', 'Saudi Arabia', 'Kuwait', 'Oman', 'Lebanon', 'Iraq', 'Palestine', 'Syria', 'Yemen']],
            'asia' => ['Asia', 84, 34, ['India', 'China', 'Japan', 'Singapore', 'Malaysia', 'Indonesia', 'Thailand', 'Korea', 'South Korea', 'Pakistan']],
        ];

        return collect($regions)->map(function (array $r, string $key) use ($events) {
            [$label, $x, $y, $countries] = $r;

            return [
                'key' => $key,
                'label' => $label,
                'x' => $x,
                'y' => $y,
                'count' => $events->filter(fn (Event $e) => in_array(trim((string) $e->country), $countries, true))->count(),
            ];
        })->values();
    }
}
