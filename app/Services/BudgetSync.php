<?php

namespace App\Services;

use App\Models\Event;
use Illuminate\Support\Str;

/**
 * Keeps the budget in step with the operational modules.
 *
 * Every module record that carries money — a block of rooms, a transfer, a
 * speaker fee, a hall — is mirrored as a linked budget line whose amount
 * tracks the source. Manual lines are never touched, and on a linked line the
 * desk's own work (actual, paid, status) survives every re-sync: only the
 * fields the module owns are refreshed.
 *
 * The Stay module is the one to read carefully. Money lives on the BLOCK —
 * 25 rooms × 6 nights at 170 — and the rooming list underneath it is names,
 * not money. Mirroring both would count the same rooms twice, so a rooming row
 * that belongs to a block is skipped and only a standalone booking with a cost
 * of its own becomes a line.
 */
class BudgetSync
{
    public function sync(Event $event): int
    {
        // Never mutate an approved / locked baseline.
        if ($event->budgetLocked()) {
            return 0;
        }

        $event->load(['roomBlocks', 'accommodations', 'transport', 'speakers', 'rooms', 'cateringItems']);
        $event->ensureBudgetCategories();

        // Where each module's cost lands — the event's own choice, made in the
        // module itself, falling back to what this always did. See
        // Event::moduleBudgetCategory().
        $stay = $event->moduleBudgetCategory('stay');
        $transport = $event->moduleBudgetCategory('transport');
        $speakers = $event->moduleBudgetCategory('speakers');
        $venues = $event->moduleBudgetCategory('venue');
        $catering = $event->moduleBudgetCategory('catering');

        foreach ([$stay, $transport, $speakers, $venues, $catering] as $name) {
            $event->budgetCategory($name);
        }

        $keep = [];
        $touched = 0;

        // ── Stay: the blocks are the commitment ──
        foreach ($event->roomBlocks as $b) {
            // A cancelled block is not a cost, and a block nobody has priced
            // yet is not one either — 0 would say the rooms are free.
            if ($b->status === 'cancelled' || $b->totalCents() <= 0) {
                continue;
            }

            $touched += $this->upsert($event, 'room_block', $b->id, [
                'category' => $stay,
                'description' => $b->budgetLine(),
                'estimated_cents' => $b->totalCents(),
                'vendor' => $b->hotel,
                'supplier_id' => $b->supplier_id,
                'quantity' => max(1, $b->roomNights()),
                'unit_cents' => $b->rate_cents ?: null,
            ]);
            $keep[] = 'room_block:'.$b->id;
        }

        // …and a booking made outside any block, which carries its own cost.
        foreach ($event->accommodations as $a) {
            if ($a->block_id !== null || $a->cost_cents <= 0) {
                continue;   // inside a block: already counted above
            }
            $touched += $this->upsert($event, 'accommodation', $a->id, [
                'category' => $stay,
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
                'category' => $transport,
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
                'category' => $speakers,
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

        // ── Food & beverage: one line per occasion, so a gala dinner reads as
        // a gala dinner rather than folding into an unlabelled "catering" total ──
        foreach ($event->cateringItems as $c) {
            if ($c->status === 'cancelled' || $c->totalCents() <= 0) {
                continue;
            }
            $touched += $this->upsert($event, 'catering', $c->id, [
                'category' => $catering,
                'supplier_id' => $c->supplier_id,
                'description' => $c->title.' · '.$c->typeLabel()
                    .($c->occasion_date ? ' · '.$c->occasion_date->format('j M') : ''),
                'estimated_cents' => $c->totalCents(),
                'vendor' => $c->supplier?->name,
                'quantity' => $c->per_person ? max(1, (int) ($c->headcount ?? 1)) : 1,
                'unit_cents' => $c->per_person ? $c->cost_cents : null,
            ]);
            $keep[] = 'catering:'.$c->id;
        }

        // Event-wide requirements → one aggregate line under "Event Requirements".
        $eventReqTotal = $event->eventRequirementsTotalCents();
        if ($eventReqTotal > 0) {
            $reqCat = $event->moduleBudgetCategory('requirements');
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

    /**
     * Module records that hold a commitment the budget cannot count yet.
     *
     * A block of rooms with no rate is the reason somebody stands in front of
     * a budget saying "but I booked those" — the rooms are real, the number is
     * not there, and until now nothing said so. Naming them is the difference
     * between a budget that is wrong and one that is honest about what it does
     * not know.
     *
     * @return list<array{module:string,tab:string,what:string}>
     */
    public function pending(Event $event): array
    {
        $out = [];

        foreach ($event->roomBlocks as $b) {
            if ($b->status !== 'cancelled' && $b->totalCents() <= 0) {
                $out[] = ['module' => 'Stay', 'tab' => 'accommodation',
                    'what' => $b->rooms_count.' '.Str::plural('room', $b->rooms_count)
                        .' at '.($b->hotel ?: 'a hotel').' — no rate yet'];
            }
        }

        foreach ($event->transport as $t) {
            if ($t->cost_cents <= 0) {
                $out[] = ['module' => 'Transport', 'tab' => 'transportation',
                    'what' => ($t->route ?: 'A movement').' — not costed'];
            }
        }

        foreach ($event->rooms as $r) {
            if ($r->totalCents() <= 0) {
                $out[] = ['module' => 'Venue', 'tab' => 'venue',
                    'what' => ($r->name ?: 'A space').' — no hire cost'];
            }
        }

        foreach ($event->cateringItems as $c) {
            if ($c->status !== 'cancelled' && $c->totalCents() <= 0) {
                $out[] = ['module' => 'Food & Beverage', 'tab' => 'catering',
                    'what' => ($c->title ?: 'An occasion').' — not costed'];
            }
        }

        return $out;
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
                        // Not costed yet, which is null — 0 would mean it costs nothing.
            'actual_cents' => null,
            'paid_cents' => 0,
            'payment_status' => 'pending',
        ]);

        return 1;
    }
}
