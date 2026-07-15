<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->boolean('flagged')->default(false)->after('payment_status');
        });
    }

    public function down(): void
    {
        Schema::table('event_budget_items', fn (Blueprint $table) => $table->dropColumn('flagged'));
    }
};
