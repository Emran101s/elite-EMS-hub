<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Check-in and registration must not share a secret.
 *
 * Badges print a QR that opens the door. Registration shares a public form
 * URL. When those were the same token, rotating a leaked registration link
 * quietly invalidated every badge already printed — the opposite of what the
 * UI promised ("only the URL changes").
 *
 * Backfill with fresh tokens (not a copy of registration_token) so rotating
 * registration never also rotates check-in for events that already exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('checkin_token', 32)->nullable()->unique()->after('registration_token');
        });

        DB::table('events')->orderBy('id')->chunkById(100, function ($rows) {
            foreach ($rows as $event) {
                DB::table('events')->where('id', $event->id)->update([
                    'checkin_token' => Str::lower(Str::random(24)),
                ]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('checkin_token');
        });
    }
};
