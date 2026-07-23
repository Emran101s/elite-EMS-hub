<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Link a transfer guest back to the attendee record it was pulled from, so
 * "Pull attendees" is idempotent and registration stays the source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->foreignId('attendee_id')->nullable()->after('event_id')
                ->constrained('event_attendees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_transport_passengers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('attendee_id');
        });
    }
};
