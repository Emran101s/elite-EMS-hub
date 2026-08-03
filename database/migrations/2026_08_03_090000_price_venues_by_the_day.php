<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A room is hired for days, and its equipment is not the same every day.
 *
 * `cost_cents` was one flat figure per room: somebody worked out five days of
 * hire in their head and typed the answer. So a room booked for five days and
 * the same room booked for one looked identical to the platform, nothing could
 * be recalculated when the programme moved, and "interpretation on three of the
 * five days" could not be said at all.
 *
 * cost_cents becomes the DAY RATE and `days` multiplies it. Every room that
 * exists gets days = 1, so its total is exactly what it was — the number was
 * already a whole hire, and calling it one day's worth keeps it true.
 *
 * `days` null means "count them from the agenda", which is where the answer
 * already is: the sessions in a room know which days it is used.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->unsignedInteger('days')->nullable()->after('cost_cents');
            $table->unsignedInteger('setup_days')->default(0)->after('days');
        });

        // What is already typed is a whole hire, not a rate. Freezing it at one
        // day keeps every existing total unchanged.
        DB::table('event_rooms')->whereNull('days')->update(['days' => 1]);
    }

    public function down(): void
    {
        Schema::table('event_rooms', fn (Blueprint $t) => $t->dropColumn(['days', 'setup_days']));
    }
};
