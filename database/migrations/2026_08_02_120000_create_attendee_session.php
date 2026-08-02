<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Who is coming to which session.
 *
 * An attendee's answers already record what they picked, but an answer is a
 * string on a person: it cannot tell a room how many chairs it needs, cannot
 * be counted against a session's capacity, and cannot produce the list the
 * person on the door actually holds.
 *
 * A row here is one seat taken. The unique key is the point — registering
 * twice, or importing the same sheet twice, must not book the same person into
 * the same session twice.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendee_session', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_attendee_id')->constrained('event_attendees')->cascadeOnDelete();
            $table->foreignId('event_agenda_session_id')->constrained('event_agenda_sessions')->cascadeOnDelete();
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamps();

            $table->unique(['event_attendee_id', 'event_agenda_session_id'], 'attendee_session_unique');
            $table->index('event_agenda_session_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendee_session');
    }
};
