<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_exhibitors', function (Blueprint $table) {
            // Booth position on the floor plan canvas (null = unplaced / in the tray).
            $table->integer('pos_x')->nullable()->after('booth_size');
            $table->integer('pos_y')->nullable()->after('pos_x');
            $table->unsignedInteger('booth_w')->nullable()->after('pos_y');
            $table->unsignedInteger('booth_h')->nullable()->after('booth_w');
        });

        Schema::table('events', function (Blueprint $table) {
            // Non-exhibitor fixtures on the floor plan: [{id,type,label,x,y,w,h}].
            $table->json('exhibition_fixtures')->nullable()->after('exhibition_target_cents');
        });
    }

    public function down(): void
    {
        Schema::table('event_exhibitors', function (Blueprint $table) {
            $table->dropColumn(['pos_x', 'pos_y', 'booth_w', 'booth_h']);
        });
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('exhibition_fixtures');
        });
    }
};
