<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Which budget category each module's costs land in.
 *
 * The mapping was decided in code — every guest-facing module went to
 * "Attendee & Guest Services", every hall to "Venues" — so an event that
 * budgets transport separately from accommodation had no way to say so, and
 * the categories the desk is free to rename, add and reorder had one they
 * could not point anything at.
 *
 * Per event, because two events run by the same company routinely group their
 * costs differently. Unset means the module keeps its sensible default.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->json('module_budget_categories')->nullable()->after('budget_status');
        });
    }

    public function down(): void
    {
        Schema::table('events', fn (Blueprint $table) => $table->dropColumn('module_budget_categories'));
    }
};
