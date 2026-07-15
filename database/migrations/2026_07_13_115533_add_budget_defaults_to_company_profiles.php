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
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->json('default_budget_categories')->nullable()->after('default_timezone');
            $table->decimal('default_management_fee_pct', 5, 2)->default(15)->after('default_budget_categories');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('company_profiles', function (Blueprint $table) {
            $table->dropColumn(['default_budget_categories', 'default_management_fee_pct']);
        });
    }
};
