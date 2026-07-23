<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Guests arrive as a flight list long before the vehicles are booked, so a
 * passenger now belongs to the EVENT and only later to a movement. A row with
 * transport_id = null is an unassigned guest waiting in the pool.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->foreignId('event_id')->nullable()->after('id')->constrained('events')->cascadeOnDelete();
            $table->string('direction', 12)->nullable()->after('name');   // arrival | departure
            $table->string('pickup_time', 5)->nullable()->after('arrival_time');
            $table->string('drop_point')->nullable()->after('pickup_point');
        });

        // Existing passengers keep their movement — just learn their event and leg.
        DB::statement('
            UPDATE event_transport_passengers
               SET event_id = (SELECT t.event_id FROM event_transport t WHERE t.id = event_transport_passengers.transport_id)
             WHERE event_id IS NULL
        ');
        DB::statement("
            UPDATE event_transport_passengers
               SET direction = COALESCE((
                     SELECT CASE WHEN LOWER(COALESCE(t.drop_to, '')) LIKE '%airport%' THEN 'departure' ELSE 'arrival' END
                       FROM event_transport t WHERE t.id = event_transport_passengers.transport_id
                   ), 'arrival')
             WHERE direction IS NULL
        ");

        // A guest can now exist before any vehicle does.
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->unsignedBigInteger('transport_id')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('event_id');
            $table->dropColumn(['direction', 'pickup_time', 'drop_point']);
        });
    }
};
