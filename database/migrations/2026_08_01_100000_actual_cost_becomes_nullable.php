<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "We have not costed this yet" and "this cost nothing" are different facts.
 *
 * actual_cents was NOT NULL DEFAULT 0, so one number had to carry both, and
 * every reader resolved the ambiguity the same way: `actual_cents ?: estimated`
 * — which quietly makes the second fact impossible to record. A venue comped by
 * a sponsor, entered as 0, went on reporting the estimate as its cost forever,
 * and the saving it represented never appeared anywhere.
 *
 * NULL now means not costed yet; 0 means costed, at nothing.
 *
 * The backfill is faithful rather than clever: under the old rule a stored 0
 * behaved exactly as "not costed", so that is what every existing 0 becomes.
 * Nothing that was true before this migration is false after it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->bigInteger('actual_cents')->nullable()->default(null)->change();
        });

        DB::table('event_budget_items')->where('actual_cents', 0)->update(['actual_cents' => null]);
    }

    public function down(): void
    {
        DB::table('event_budget_items')->whereNull('actual_cents')->update(['actual_cents' => 0]);

        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->bigInteger('actual_cents')->default(0)->nullable(false)->change();
        });
    }
};
