<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Event-wide costed requirements (not tied to a venue): [{id, name, cost_cents}].
            $table->json('event_requirements')->nullable()->after('exhibition_fixtures');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('event_requirements');
        });
    }
};
