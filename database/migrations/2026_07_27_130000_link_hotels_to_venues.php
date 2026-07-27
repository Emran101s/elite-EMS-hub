<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hotels become venues.
 *
 * The hotel was a free-text string in three separate tables — room blocks,
 * accommodation rows and transport passengers — so the same hotel was typed
 * three times and matched nowhere. Meanwhile Venues already holds exactly what
 * a hotel needs: name, type, city, address, capacity, a contact. So a hotel is
 * a venue whose type is Hotel, and these three tables link to it.
 *
 * The string column stays, deliberately. It is what the rooming list PDF was
 * printed with and what history should keep reading; picking a venue sets both,
 * and a name typed before this existed still displays.
 *
 * The backfill matches on the exact name, case-insensitively, and nothing else.
 * A fuzzy match would silently attach a block to the wrong hotel, which is
 * worse than leaving it unlinked — an unlinked block still shows its name.
 */
return new class extends Migration
{
    private const TABLES = ['event_room_blocks', 'event_accommodations', 'event_transport_passengers'];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'venue_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) {
                // Deleting a venue leaves the row with its name, not a hole.
                $t->foreignId('venue_id')->nullable()->after('hotel')
                    ->constrained('venues')->nullOnDelete();
            });
        }

        // A venue matched by name is a venue whose type should say Hotel.
        $venues = DB::table('venues')->select('id', 'name', 'type')->get();

        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'hotel')) {
                continue;
            }

            foreach ($venues as $venue) {
                $matched = DB::table($table)
                    ->whereNull('venue_id')
                    ->whereRaw('lower(trim(hotel)) = ?', [mb_strtolower(trim($venue->name))])
                    ->update(['venue_id' => $venue->id]);

                if ($matched > 0 && ! $venue->type) {
                    DB::table('venues')->where('id', $venue->id)->update(['type' => 'Hotel']);
                }
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'venue_id')) {
                Schema::table($table, fn (Blueprint $t) => $t->dropConstrainedForeignId('venue_id'));
            }
        }
    }
};
