<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which vehicle of the run a passenger rides in. A movement can book several
 * vehicles; this says who is in van 1 and who is in van 2.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->unsignedSmallInteger('vehicle_no')->nullable()->after('position');
        });
    }

    public function down(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->dropColumn('vehicle_no');
        });
    }
};
