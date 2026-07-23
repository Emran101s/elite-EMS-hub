<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Str;

/**
 * Keeps the budget in step with the operational modules. Each module cost
 * record (accommodation, transport, speaker fee, venue hire) is mirrored as a
 * linked budget line whose amount tracks the source. Manual budget lines are
 * never touched; user progress (actual / paid / status) on a linked line is
 * preserved — only the source-owned fields are refreshed.
 */
class BudgetSync
{
    public function sync(Event $event): int
    {
        // Never mutate an approved / locked baseline.
        if ($event->budgetLocked()) {
            return 0;
        }

        $event->load(['accommodations', 'transport', 'speakers', 'rooms']);
        $event->ensureBudgetCategories();

        // Where each module's cost lands in the (user-editable) category list.
        $guests = 'Attendee & Guest Services';
        $venues = 'Venues';
        $event->budgetCategory($guests);
        $event->budgetCategory($venues);

        $keep = [];
        $touched = 0;

        foreach ($event->accommodations as $a) {
            if ($a->cost_cents <= 0) {
                continue;
            }
            $touched += $this->upsert($event, 'accommodation', $a->id, [
                'category' => $guests,
                'description' => trim('Accommodation · '.$a->hotel.($a->guest ? ' — '.$a->guest : '')),
                'estimated_cents' => $a->cost_cents,
                'vendor' => $a->hotel,
                'quantity' => max(1, $a->rooms),
            ]);
            $keep[] = 'accommodation:'.$a->id;
        }

        foreach ($event->transport as $t) {
            if ($t->cost_cents <= 0) {
                continue;
            }
            $touched += $this->upsert($event, 'transport', $t->id, [
                'category' => $guests,
                'description' => 'Transport · '.$t->route,
                'estimated_cents' => $t->cost_cents,
                'vendor' => $t->provider,
                'quantity' => 1,
            ]);
            $keep[] = 'transport:'.$t->id;
        }

        foreach ($event->speakers as $s) {
            if ($s->fee_cents <= 0) {
                continue;
            }
            $touched += $this->upsert($event, 'speaker', $s->id, [
                'category' => $guests,
                'description' => 'Speaker fee · '.$s->name,
                'estimated_cents' => $s->fee_cents,
                'quantity' => 1,
            ]);
            $keep[] = 'speaker:'.$s->id;
        }

        foreach ($event->rooms as $r) {
            // One linked budget line per venue = its full cost (hire + equipment), named with the venue.
            $total = $r->totalCents();
            if ($total <= 0) {
                continue;
            }
            $touched += $this->upsert($event, 'room', $r->id, [
                'category' => $venues,
                'room_id' => $r->id,
                'description' => $r->name,
                'estimated_cents' => $total,
                'quantity' => 1,
            ]);
            $keep[] = 'room:'.$r->id;
        }

        // Event-wide requirements → one aggregate line under "Event Requirements".
        $eventReqTotal = $event->eventRequirementsTotalCents();
        if ($eventReqTotal > 0) {
            $reqCat = 'Event Requirements';
            $event->budgetCategory($reqCat);
            $n = count($event->event_requirements ?? []);
            $touched += $this->upsert($event, 'event_req', 0, [
                'category' => $reqCat,
                'description' => 'Event requirements ('.$n.' '.Str::plural('item', $n).')',
                'estimated_cents' => $eventReqTotal,
                'quantity' => 1,
            ]);
            $keep[] = 'event_req:0';
        }

        // Remove linked lines whose source is gone or dropped to zero.
        foreach ($event->budgetItems()->whereNotNull('source_type')->get() as $line) {
            if (! in_array($line->source_type.':'.$line->source_id, $keep, true)) {
                $line->delete();
            }
        }

        return $touched;
    }

    /** Create or refresh a linked line, preserving actual/paid/status. */
    private function upsert(Event $event, string $type, int $id, array $fields): int
    {
        $line = $event->budgetItems()->where('source_type', $type)->where('source_id', $id)->first();

        if ($line) {
            $line->update($fields + ['unit_cents' => null]);

            return 0;
        }

        $event->budgetItems()->create($fields + [
            'source_type' => $type,
            'source_id' => $id,
            'unit_cents' => null,
            'actual_cents' => 0,
            'paid_cents' => 0,
            'payment_status' => 'pending',
        ]);

        return 1;
    }
}
