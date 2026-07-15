<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            // Events-management company fee, % of the base subtotal (default 15%).
            $table->decimal('management_fee_pct', 5, 2)->default(15)->after('currency');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('management_fee_pct'));
    }
};
