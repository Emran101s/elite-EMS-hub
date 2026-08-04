<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A recovery safety net for the two records where an accidental delete would
 * lose real financial data with nothing else to fall back on.
 *
 * Event, EventContract and EventAttendee were deliberately left out after
 * investigation: each already has its own tested, intentional hard-delete
 * behavior (Event's own archived_at already serves as its recovery path;
 * EventContract and EventAttendee deletes cascade/null out real foreign
 * keys on other tables that a soft delete would silently stop firing).
 * Soft-deleting them would need real cascade-soft-delete logic across many
 * relationships, not just this column — out of scope for this pass.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['invoices', 'event_budget_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->softDeletes());
        }
    }

    public function down(): void
    {
        foreach (['invoices', 'event_budget_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropSoftDeletes());
        }
    }
};
