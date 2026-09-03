<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Removes the Scope of Work module — asked for directly: "remove the scope
 * module and delete it, I will not use it, I will build something else
 * later."
 *
 * The create migration (2026_09_01_120000) already merged to main and may
 * have run in another environment, so it stays rather than being deleted or
 * rewritten — migrations are an append-only log, and a fresh environment
 * still needs to be able to run every file in order. This is the reversing
 * half of it: dropping the table, and undoing the one side effect the create
 * migration had beyond its own table — it appended 'scope' into every
 * existing event's enabled_modules, and that key would otherwise sit there
 * forever as dead data pointing at a module that no longer exists.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('events')->whereNotNull('enabled_modules')->get(['id', 'enabled_modules']) as $event) {
            $modules = json_decode($event->enabled_modules, true);

            if (! is_array($modules) || ! in_array('scope', $modules, true)) {
                continue;
            }

            DB::table('events')->where('id', $event->id)->update([
                'enabled_modules' => json_encode(array_values(array_diff($modules, ['scope']))),
            ]);
        }

        Schema::dropIfExists('event_scope_items');
    }

    public function down(): void
    {
        // Deliberately not reversible: recreating the table here would
        // recreate a feature that was removed on purpose, not restore data
        // that still exists. Re-run the original create migration if the
        // module is ever rebuilt.
    }
};
