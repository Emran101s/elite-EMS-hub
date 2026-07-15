<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->string('vendor')->nullable()->after('supplier_id');
            $table->unsignedInteger('quantity')->default(1)->after('description');
            $table->unsignedInteger('unit_cents')->nullable()->after('quantity');
            $table->unsignedInteger('paid_cents')->default(0)->after('actual_cents');
        });
    }

    public function down(): void
    {
        Schema::table('event_budget_items', function (Blueprint $table) {
            $table->dropColumn(['vendor', 'quantity', 'unit_cents', 'paid_cents']);
        });
    }
};
