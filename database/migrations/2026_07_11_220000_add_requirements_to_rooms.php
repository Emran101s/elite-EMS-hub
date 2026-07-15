<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            // Costed requirements per venue: [{id, name, cost_cents}].
            $table->json('requirements')->nullable()->after('cost_cents');
        });
    }

    public function down(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->dropColumn('requirements');
        });
    }
};
