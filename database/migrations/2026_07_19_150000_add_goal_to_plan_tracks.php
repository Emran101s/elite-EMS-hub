<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A short goal/description for each Plan Studio track. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plan_tracks', function (Blueprint $table) {
            $table->string('goal')->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('plan_tracks', function (Blueprint $table) {
            $table->dropColumn('goal');
        });
    }
};
