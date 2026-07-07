<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('timezone')->default('UTC')->after('country');
            $table->json('enabled_modules')->nullable()->after('timezone'); // null = all modules (legacy events)
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['timezone', 'enabled_modules']);
        });
    }
};
