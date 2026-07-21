<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tasks Studio — a task can link to a planning track (the phase it feeds) and
 * carry a start date so it renders on the timeline.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->foreignId('track_id')->nullable()->after('area')->constrained('plan_tracks')->nullOnDelete();
            $table->date('start_on')->nullable()->after('track_id');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('track_id');
            $table->dropColumn('start_on');
        });
    }
};
