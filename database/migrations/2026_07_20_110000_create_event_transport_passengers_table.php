<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The manifest for a movement: who is actually in the vehicle. A van with seven
 * seats gets up to seven named passengers, the same way a room block gets named
 * guests — the driver's list, not a headcount.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_transport_passengers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transport_id')->constrained('event_transport')->cascadeOnDelete();
            $table->string('name', 120);
            $table->string('phone', 40)->nullable();
            $table->string('email', 160)->nullable();
            $table->string('flight_no', 40)->nullable();      // the passenger's own flight
            $table->string('pickup_point', 160)->nullable();  // differs per passenger on hotel runs
            $table->string('notes', 200)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['transport_id', 'position']);
        });

        Schema::table('event_transport', function (Blueprint $table) {
            $table->string('flight_no', 40)->nullable()->after('depart_at');   // flight this run meets
            $table->dateTime('arrive_at')->nullable()->after('flight_no');
        });
    }

    public function down(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->dropColumn(['flight_no', 'arrive_at']);
        });
        Schema::dropIfExists('event_transport_passengers');
    }
};
