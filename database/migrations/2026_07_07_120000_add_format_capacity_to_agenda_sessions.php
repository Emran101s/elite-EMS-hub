<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_agenda_sessions', function (Blueprint $table) {
            $table->string('format')->default('in_person')->after('type'); // in_person | hybrid | virtual
            $table->unsignedInteger('capacity')->nullable()->after('format'); // seat capacity, optional
        });
    }

    public function down(): void
    {
        Schema::table('event_agenda_sessions', function (Blueprint $table) {
            $table->dropColumn(['format', 'capacity']);
        });
    }
};
