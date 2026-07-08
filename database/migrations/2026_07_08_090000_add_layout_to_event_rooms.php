<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->json('layout')->nullable(); // placed seating elements
        });
    }

    public function down(): void
    {
        Schema::table('event_rooms', function (Blueprint $table) {
            $table->dropColumn('layout');
        });
    }
};
