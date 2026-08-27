<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Transport movement cost, event-priced items, and invoice lines were all
 * stored as whole integer cents — 127.116 had nowhere to put its third
 * decimal and got rounded to 127.12 before it ever reached the database.
 * Widening to decimal(15,1) adds one more fractional digit to the "cents"
 * value itself (tenths of a cent), which is exactly one more decimal place
 * of the real currency amount once divided by 100 — 127.116 stores as
 * 12711.6. Existing whole-cent values (e.g. 12712) are already valid
 * decimal(15,1) values, so nothing already saved changes.
 *
 * SUPERSEDED: money is now stored as whole integer cents (the model casts are
 * `integer`, the writers do `(int) round(...)`). On Postgres this decimal
 * `->change()` silently no-ops anyway — the columns stay integer — which is now
 * exactly what the code wants, so this migration is a harmless no-op there and a
 * widen-to-decimal that accepts integers on SQLite. Kept, not reverted, because
 * it has already run on existing databases. Do not reintroduce a `decimal:*`
 * cast on these columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->decimal('cost_cents', 15, 1)->unsigned()->default(0)->change();
        });

        Schema::table('event_invoice_items', function (Blueprint $table) {
            $table->decimal('cost_cents', 15, 1)->default(0)->change();
            $table->decimal('sell_cents', 15, 1)->default(0)->change();
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->decimal('unit_cents', 15, 1)->default(0)->change();
        });
    }

    public function down(): void
    {
        Schema::table('event_transport', function (Blueprint $table) {
            $table->unsignedInteger('cost_cents')->default(0)->change();
        });

        Schema::table('event_invoice_items', function (Blueprint $table) {
            $table->bigInteger('cost_cents')->default(0)->change();
            $table->bigInteger('sell_cents')->default(0)->change();
        });

        Schema::table('invoice_lines', function (Blueprint $table) {
            $table->bigInteger('unit_cents')->default(0)->change();
        });
    }
};
