<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An accommodation row becomes a rooming-list line: one named guest in one room
 * of a block. Existing standalone rows keep working — `block_id` stays nullable.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_accommodations', function (Blueprint $table) {
            $table->foreignId('block_id')->nullable()->after('event_id')
                ->constrained('event_room_blocks')->cascadeOnDelete();
            $table->string('guest_email', 160)->nullable()->after('guest');
            $table->string('guest_phone', 40)->nullable()->after('guest_email');
            $table->string('sharing_with', 120)->nullable()->after('guest_phone');
            $table->string('arrival_note', 120)->nullable()->after('check_in');   // flight / ETA
            $table->string('departure_note', 120)->nullable()->after('check_out');
            $table->unsignedInteger('position')->default(0)->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('event_accommodations', function (Blueprint $table) {
            $table->dropForeign(['block_id']);
            $table->dropColumn(['block_id', 'guest_email', 'guest_phone', 'sharing_with',
                'arrival_note', 'departure_note', 'position']);
        });
    }
};
