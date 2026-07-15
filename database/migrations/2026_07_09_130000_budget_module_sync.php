<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            // Linked (synced) lines carry the source module record they mirror.
            $table->string('source_type')->nullable()->after('event_id'); // accommodation|transport|speaker|room
            $table->unsignedBigInteger('source_id')->nullable()->after('source_type');
            $table->index(['event_id', 'source_type', 'source_id']);
        });

        Schema::table('event_rooms', function (Blueprint $table) {
            $table->unsignedBigInteger('cost_cents')->default(0)->after('capacity'); // venue hire cost
        });
    }

    public function down(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->dropIndex(['event_id', 'source_type', 'source_id']);
            $table->dropColumn(['source_type', 'source_id']);
        });
        Schema::table('event_rooms', fn (Blueprint $table) => $table->dropColumn('cost_cents'));
    }
};
