<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * One equipment list per venue, not two.
 *
 * `event_rooms.equipment` held a tick-list (item => qty/status/notes) and
 * `event_rooms.requirements` held the priced lines. Both were labelled
 * "Equipment" in the interface. When the requirements editor replaced the
 * tick-list editor, nothing wrote to `equipment` any more — so the rooms people
 * had filled in showed an empty prep sheet, and the portfolio's equipment
 * readiness was computed from whatever legacy rows happened to survive.
 *
 * Whatever is still in `equipment` moves across as a requirement at no cost,
 * keeping its quantity, status and notes. The column is left in place — it is
 * read by nothing now, and dropping it is not this migration's job.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('event_rooms')->get() as $room) {
            $kit = json_decode($room->equipment ?? '[]', true) ?: [];
            if (! $kit) {
                continue;
            }

            $reqs = json_decode($room->requirements ?? '[]', true) ?: [];
            // Anything already listed by name stays as it is — the priced line
            // is the better record of the two.
            $have = collect($reqs)->map(fn ($r) => Str::lower(trim($r['name'] ?? '')))->filter()->all();

            foreach ($kit as $name => $line) {
                $name = is_string($name) ? trim($name) : '';
                if ($name === '' || in_array(Str::lower($name), $have, true)) {
                    continue;
                }

                $qty = is_array($line) ? (int) ($line['qty'] ?? 0) : (int) $line;
                if ($qty <= 0) {
                    continue;
                }

                $reqs[] = [
                    'id' => Str::random(8),
                    'name' => $name,
                    'cost_cents' => 0,          // the tick-list never carried money
                    'qty' => $qty,
                    'days' => 1,
                    'status' => is_array($line) && in_array($line['status'] ?? '', ['needed', 'requested', 'confirmed', 'onsite'], true)
                        ? $line['status'] : 'needed',
                    'notes' => is_array($line) ? (string) ($line['notes'] ?? '') : '',
                ];
            }

            DB::table('event_rooms')->where('id', $room->id)
                ->update(['requirements' => json_encode($reqs)]);
        }
    }

    public function down(): void
    {
        // The kit is still in `equipment`; nothing was destroyed to undo.
    }
};
