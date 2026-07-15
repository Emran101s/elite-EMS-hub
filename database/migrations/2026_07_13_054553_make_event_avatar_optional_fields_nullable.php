<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('event_avatars', function (Blueprint $table) {
            $table->string('subtitle')->nullable()->change();
            $table->string('best_for')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_avatars', function (Blueprint $table) {
            $table->string('subtitle')->nullable(false)->change();
            $table->string('best_for')->nullable(false)->change();
        });
    }
};
