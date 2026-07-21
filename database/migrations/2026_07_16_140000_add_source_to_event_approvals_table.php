<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Links an approval back to the task that raised it, so moving a card to
 * "Needs approval" opens a real approval, and deciding it there settles the
 * card. Same source_type/source_id shape as tasks and budget items.
 *
 * Also retires the `paused` task status — "blocked" already says the work
 * isn't moving, and says why.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_approvals', function (Blueprint $table) {
            $table->string('source_type', 32)->nullable()->index();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->unique(['event_id', 'source_type', 'source_id'], 'event_approvals_source_unique');
        });

        // (The old task-status backfill lived here; the tasks table was later
        //  rebuilt from scratch, so this data migration no longer applies.)
    }

    public function down(): void
    {
        Schema::table('event_approvals', function (Blueprint $table) {
            $table->dropUnique('event_approvals_source_unique');
            $table->dropColumn(['source_type', 'source_id']);
        });
    }
};
