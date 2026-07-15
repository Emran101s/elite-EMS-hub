<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedBigInteger('client_target_cents')->nullable()->after('budget_cents');
            $table->unsignedBigInteger('sponsorship_target_cents')->nullable()->after('client_target_cents');
            $table->unsignedBigInteger('exhibition_target_cents')->nullable()->after('sponsorship_target_cents');
        });

        Schema::table('event_sponsors', function (Blueprint $table) {
            $table->unsignedBigInteger('paid_cents')->default(0)->after('amount_cents');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn(['client_target_cents', 'sponsorship_target_cents', 'exhibition_target_cents']);
        });
        Schema::table('event_sponsors', function (Blueprint $table) {
            $table->dropColumn('paid_cents');
        });
    }
};
