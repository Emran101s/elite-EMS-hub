<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            // When set, this line is budgeted under a specific venue/room section.
            $table->foreignId('room_id')->nullable()->after('event_id')->constrained('event_rooms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('room_id');
        });
    }
};
