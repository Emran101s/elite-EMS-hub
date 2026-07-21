<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A session has many speakers, each in a role (keynote, panellist, moderator …).
 * Speakers come from the event's roster (event_speakers) so one person carries a
 * single title/organisation/photo everywhere and can be tracked across sessions.
 *
 * Backfills the legacy free-text `speaker` / `moderator` columns into the roster.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agenda_session_speaker', function (Blueprint $table) {
            $table->id();
            $table->foreignId('agenda_session_id')->constrained('event_agenda_sessions')->cascadeOnDelete();
            $table->foreignId('speaker_id')->constrained('event_speakers')->cascadeOnDelete();
            $table->string('role', 24)->default('panelist');
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->unique(['agenda_session_id', 'speaker_id', 'role']);
        });

        $this->backfill();
    }

    /** Turn each distinct free-text speaker/moderator name into a roster entry + pivot row. */
    private function backfill(): void
    {
        $sessions = DB::table('event_agenda_sessions')
            ->where(fn ($q) => $q->whereNotNull('speaker')->orWhereNotNull('moderator'))
            ->get(['id', 'event_id', 'speaker', 'moderator']);

        $roster = [];  // "eventId|name" => speaker id

        $resolve = function (int $eventId, string $name) use (&$roster): int {
            $key = $eventId.'|'.mb_strtolower($name);
            if (isset($roster[$key])) {
                return $roster[$key];
            }

            $existing = DB::table('event_speakers')->where('event_id', $eventId)
                ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->value('id');

            return $roster[$key] = $existing ?: DB::table('event_speakers')->insertGetId([
                'event_id' => $eventId, 'name' => $name, 'status' => 'invited',
                'is_keynote' => false, 'sort_order' => 0,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        };

        foreach ($sessions as $s) {
            $sort = 0;
            foreach ([['speaker', 'panelist'], ['moderator', 'moderator']] as [$column, $role]) {
                $name = trim((string) ($s->$column ?? ''));
                if ($name === '') {
                    continue;
                }
                DB::table('agenda_session_speaker')->insertOrIgnore([
                    'agenda_session_id' => $s->id,
                    'speaker_id' => $resolve((int) $s->event_id, $name),
                    'role' => $role,
                    'sort' => $sort++,
                    'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('agenda_session_speaker');
    }
};
