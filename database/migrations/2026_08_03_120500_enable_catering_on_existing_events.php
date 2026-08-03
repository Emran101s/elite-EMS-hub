<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A new module is invisible on every event created before it existed.
 *
 * `enabled_modules` is null (meaning "everything") only until somebody first
 * opens an event's Settings and the toggle list is captured as it stood that
 * day — from then on it is an explicit list, and a module added to the
 * platform afterwards is simply absent from it. Food & Beverage would
 * otherwise exist everywhere in code and nowhere in the product for any event
 * whose module list was already captured.
 *
 * Only touches rows that HAVE a captured list and don't already carry the key
 * — a null list already means "everything", and re-running this is a no-op.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('events')->whereNotNull('enabled_modules')->get() as $event) {
            $modules = json_decode($event->enabled_modules, true) ?: [];

            if (in_array('catering', $modules, true)) {
                continue;
            }

            DB::table('events')->where('id', $event->id)
                ->update(['enabled_modules' => json_encode([...$modules, 'catering'])]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('events')->whereNotNull('enabled_modules')->get() as $event) {
            $modules = array_values(array_diff(json_decode($event->enabled_modules, true) ?: [], ['catering']));
            DB::table('events')->where('id', $event->id)->update(['enabled_modules' => json_encode($modules)]);
        }
    }
};
