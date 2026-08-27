<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
 * On Postgres this is done with a raw ALTER ... TYPE ... USING rather than
 * Blueprint::change(): the fluent change() left these integer columns
 * untouched on Postgres (the migration ran without error but the type never
 * moved), so a fractional cost hit an integer column and Postgres rejected
 * "25000.0". The explicit cast is unambiguous. SQLite is typeless, so its
 * columns accept the decimals regardless and the fluent change() is fine
 * there (and on MySQL).
 */
return new class extends Migration
{
    /** @var array<string,string[]> table => money columns to widen */
    private array $columns = [
        'event_transport' => ['cost_cents'],
        'event_invoice_items' => ['cost_cents', 'sell_cents'],
        'invoice_lines' => ['unit_cents'],
    ];

    public function up(): void
    {
        $this->retype(fn (string $col) => "numeric(15,1)", fn (Blueprint $t, string $col) => $t->decimal($col, 15, 1)->default(0)->change());
    }

    public function down(): void
    {
        $this->retype(fn (string $col) => 'bigint', fn (Blueprint $t, string $col) => $t->bigInteger($col)->default(0)->change());
    }

    /**
     * @param  callable(string):string  $pgType   Postgres target type for a column
     * @param  callable(Blueprint, string):void  $fluent  the Blueprint change for other drivers
     */
    private function retype(callable $pgType, callable $fluent): void
    {
        $pgsql = DB::connection()->getDriverName() === 'pgsql';

        foreach ($this->columns as $table => $cols) {
            if ($pgsql) {
                foreach ($cols as $col) {
                    $type = $pgType($col);
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$col} TYPE {$type} USING {$col}::{$type}");
                    DB::statement("ALTER TABLE {$table} ALTER COLUMN {$col} SET DEFAULT 0");
                }

                continue;
            }

            Schema::table($table, function (Blueprint $t) use ($cols, $fluent) {
                foreach ($cols as $col) {
                    $fluent($t, $col);
                }
            });
        }
    }
};
