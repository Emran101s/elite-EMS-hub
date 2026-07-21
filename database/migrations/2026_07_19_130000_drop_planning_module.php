<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The Planning module was removed. This drops its tables (and the company-profile
 * default-phases column) so existing databases match the codebase. It will be
 * rebuilt from scratch under a new design.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('event_plan_item_user');
        Schema::dropIfExists('event_milestones');
        Schema::dropIfExists('event_plan_items');
        Schema::dropIfExists('event_plan_categories');

        if (Schema::hasColumn('company_profiles', 'default_plan_phases')) {
            Schema::table('company_profiles', function (Blueprint $table) {
                $table->dropColumn('default_plan_phases');
            });
        }
    }

    public function down(): void
    {
        // No reverse — the Planning module and its schema were retired.
    }
};
