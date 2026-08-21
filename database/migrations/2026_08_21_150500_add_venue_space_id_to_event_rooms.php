<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * An optional pointer from a booking to the venue's own permanent space —
 * "this event's Main Hall is the venue's Main Hall." Nullable and additive
 * on purpose: an EventRoom's own layout/dimensions/requirements stay fully
 * independent per booking (the same physical hall is priced and laid out
 * differently for different clients), so linking it never syncs or
 * overwrites anything. Every existing row gets venue_space_id = null, which
 * is exactly today's behaviour — nothing about RoomLayoutBuilder or
 * Hub\VenueTab changes unless a coordinator opts a room into the link.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->foreignId('venue_space_id')->nullable()->after('event_id')
                ->constrained('venue_spaces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('venue_space_id');
        });
    }
};
