<?php

use App\Support\Taxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adopt the values the data is actually using.
 *
 * The constants these lists came from had drifted from reality. Suppliers were
 * filed under `security`, `staffing`, `printing` and ten other categories that
 * Supplier::CATEGORIES never listed; a deal carried a source written by the
 * migration that moved proposal events into the pipeline. Seeding only from
 * the constants would leave every one of those records pointing at a term that
 * does not exist — invisible in Settings, uncounted, and unprotected by the
 * guard that stops you deleting something in use.
 *
 * So: for every list, any value present in the data with no term of its own
 * gets one. Added at the end of the list and marked non-system, because the
 * platform's code does not name them — they came from the data.
 *
 * This only ever adds. A value nothing uses is not invented, and nothing that
 * already has a term is touched.
 *
 * The same job also reruns the seed, which now covers all eighteen lists and
 * re-seeds session types in the shape sessions are actually stored in (snake
 * keys, since the agenda form saves through str()->snake()).
 */
return new class extends Migration
{
    public function up(): void
    {
        // Session types were seeded as words ("Gala Dinner") while every
        // session stores a key (gala_dinner), so those seeds matched nothing.
        DB::table('taxonomy_terms')
            ->where('taxonomy', 'session_type')
            ->where('is_system', true)
            ->whereNotIn('key', array_keys(Taxonomy::defaults('session_type')))
            ->delete();

        Taxonomy::seed();
        Taxonomy::adopt();
    }

    public function down(): void
    {
        // Nothing to undo: these terms describe records that exist.
    }
};
