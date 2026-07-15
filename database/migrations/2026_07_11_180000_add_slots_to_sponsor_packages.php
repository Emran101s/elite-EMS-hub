<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_sponsor_packages', function (Blueprint $table) {
            // Max number that can be sold (null = unlimited).
            $table->unsignedInteger('slots')->nullable()->after('price_cents');
        });
    }

    public function down(): void
    {
        Schema::table('event_sponsor_packages', function (Blueprint $table) {
            $table->dropColumn('slots');
        });
    }
};
