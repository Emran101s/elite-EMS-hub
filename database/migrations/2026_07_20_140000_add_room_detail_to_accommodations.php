<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hotels need two separate things about a room: how many people sleep in it
 * (single / double / twin) and what grade it is (Standard / Deluxe / Suite).
 * `room_type` already carried the grade, so occupancy joins it rather than
 * overloading one field.
 *
 * Times are stored as plain "HH:MM" strings — an arrival time is a wall-clock
 * fact the hotel reads, not an instant that should shift with a timezone.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_accommodations', function (Blueprint $table) {
            $table->string('occupancy', 20)->nullable()->after('room_type');
            $table->string('arrival_time', 5)->nullable()->after('check_in');
            $table->string('departure_time', 5)->nullable()->after('check_out');
            $table->foreignId('attendee_id')->nullable()->after('guest')
                ->constrained('event_attendees')->nullOnDelete();
        });

        Schema::table('event_room_blocks', function (Blueprint $table) {
            $table->string('occupancy', 20)->nullable()->after('room_type');
        });
    }

    public function down(): void
    {
        Schema::table('event_accommodations', function (Blueprint $table) {
            $table->dropForeign(['attendee_id']);
            $table->dropColumn(['occupancy', 'arrival_time', 'departure_time', 'attendee_id']);
        });
        Schema::table('event_room_blocks', function (Blueprint $table) {
            $table->dropColumn('occupancy');
        });
    }
};
