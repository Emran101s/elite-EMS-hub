<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One lifecycle, not two. `stage` is the event's stored life story
 * (draft → … → live → closed); the old `status` column mixed lifecycle with
 * health words (at_risk, behind) that EventHealthService computes properly.
 * Health is derived, never stored — so the column goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('status', 24)->default('planning');
        });
    }
};
