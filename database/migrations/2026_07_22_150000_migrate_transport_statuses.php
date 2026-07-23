<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Four planning statuses become seven that cover live operations too.
 *
 * "Booked" was ambiguous — ordered from the supplier, or confirmed by them? It
 * becomes "ordered" (sent, awaiting their word), leaving "confirmed" to mean
 * what everyone assumed it meant.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('event_transport')->where('status', 'booked')->update(['status' => 'ordered']);
    }

    public function down(): void
    {
        DB::table('event_transport')->where('status', 'ordered')->update(['status' => 'booked']);

        // States that had no equivalent fall back to the nearest planning truth.
        DB::table('event_transport')->whereIn('status', ['in_progress', 'issue'])->update(['status' => 'confirmed']);
        DB::table('event_transport')->where('status', 'cancelled')->update(['status' => 'planned']);
    }
};
