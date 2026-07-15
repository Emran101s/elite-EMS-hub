<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->decimal('width_m', 6, 1)->nullable()->after('capacity');
            $table->decimal('length_m', 6, 1)->nullable()->after('width_m');
            $table->json('equipment')->nullable()->after('layout'); // {item: qty}
        });
    }

    public function down(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->dropColumn(['width_m', 'length_m', 'equipment']);
        });
    }
};
