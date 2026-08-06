<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Slice 2 of the tenancy retrofit: the isolation column, everywhere.
 *
 * Adds tenant_id to the 62 tables that hold customer data, and backfills every
 * existing row to the default tenant created in slice 1. Still no enforcement —
 * the global scope and the guard test are slice 3. This slice only makes
 * isolation *possible*; nothing reads the column yet.
 *
 * WHY CHILD TABLES GET IT TOO
 *
 * event_budget_items is reachable through events, so in a normalised sense the
 * column is redundant. It is here anyway, deliberately:
 *
 *   - the global scope can then apply uniformly to every model, instead of some
 *     models being scoped directly and others only via a relation
 *   - queries that start from a child table — of which this codebase has many —
 *     are scoped without a join
 *   - it makes the (tenant_id, event_id, status) composite indexes possible,
 *     which is what the list views will need once they are paginated
 *   - defence in depth: a missed scope on the parent cannot leak the child
 *
 * WHY NULLABLE
 *
 * SQLite cannot add a NOT NULL column to a populated table without rebuilding
 * it, and rebuilding 62 tables in one migration is a bad trade for a constraint
 * the application layer already enforces (BelongsToTenant sets it on create).
 * A NULL here fails *closed*, not open: `where tenant_id = ?` never matches
 * NULL, so an unstamped row is invisible rather than visible to everyone.
 * Tighten to NOT NULL with the Postgres migration, where it is one statement.
 */
return new class extends Migration
{
    /**
     * Explicit, not discovered at runtime. A migration that enumerates its own
     * targets is reviewable; one that loops over sqlite_master is a promise.
     *
     * Excluded: framework tables (cache, sessions, jobs, migrations,
     * password_reset_tokens…) which hold no customer data, and the four spine
     * tables from slice 1 which already carry or define tenancy.
     */
    private const TABLES = [
        // aggregate roots
        'events', 'clients', 'contacts', 'deals', 'deal_activities', 'projects',
        'suppliers', 'venues', 'users', 'invoices', 'invoice_lines',
        'proposals', 'proposal_lines', 'service_items', 'requirements',
        'taxonomy_terms', 'registration_templates', 'registration_fields',
        'audit_logs', 'tasks',

        // event modules
        'event_accommodations', 'event_agenda_days', 'event_agenda_sessions',
        'event_approvals', 'approval_steps', 'event_attendees', 'event_booths',
        'event_briefs', 'event_budget_categories', 'event_budget_items',
        'event_budget_versions', 'event_catering_items', 'event_contracts',
        'event_contract_payments', 'contract_signatories', 'event_documents',
        'event_document_folders', 'event_exhibition_halls', 'event_exhibitors',
        'event_income_items', 'event_invoice_items', 'event_risks',
        'event_room_blocks', 'event_rooms', 'event_speakers',
        'event_sponsor_packages', 'event_sponsors', 'event_transport',
        'event_transport_passengers',

        // planning
        'plan_tracks', 'plan_items', 'plan_subtasks',

        // transport catalogues
        'transport_drivers', 'transport_vehicles', 'transport_service_types',
        'vehicle_types',

        // pivots — scoped too, so a join through them cannot widen a query
        'agenda_session_speaker', 'attendee_session', 'event_favorites',
        'event_supplier', 'event_team_members', 'plan_item_user',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->unsignedBigInteger('tenant_id')->nullable();
                $t->index('tenant_id', "{$table}_tenant_id_idx");
            });
        }

        $this->backfill();
    }

    /**
     * Every row that exists today belongs to the one customer this platform has
     * been running for. No foreign key is declared: 62 constraints pointing at
     * one table buys little, and makes the eventual Postgres move noisier than
     * it needs to be. The relationship is enforced by BelongsToTenant on write
     * and by the guard test on read.
     */
    private function backfill(): void
    {
        $tenantId = DB::table('tenants')->orderBy('id')->value('id');

        if (! $tenantId) {
            return;   // fresh database — nothing to attribute
        }

        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tenant_id')) {
                DB::table($table)->whereNull('tenant_id')->update(['tenant_id' => $tenantId]);
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'tenant_id')) {
                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($table) {
                $t->dropIndex("{$table}_tenant_id_idx");
                $t->dropColumn('tenant_id');
            });
        }
    }
};
