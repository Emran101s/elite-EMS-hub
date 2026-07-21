<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Passengers on one run rarely share one flight — a van meeting three arrivals
 * carries three different airlines and landing times. Each seat therefore
 * records its own flight, not just the movement's.
 *
 * Times stay "HH:MM" strings: a landing time is a wall-clock fact printed for a
 * driver, not an instant to be shifted by a timezone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->string('airline', 80)->nullable()->after('name');
            $table->date('arrival_on')->nullable()->after('flight_no');
            $table->string('arrival_time', 5)->nullable()->after('arrival_on');
        });
    }

    public function down(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->dropColumn(['airline', 'arrival_on', 'arrival_time']);
        });
    }
};
