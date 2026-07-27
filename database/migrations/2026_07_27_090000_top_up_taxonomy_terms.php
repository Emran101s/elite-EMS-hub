<?php

use App\Support\Taxonomy;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Top up the editable lists.
 *
 * The first seed covered five lists. Agenda session types, venue types, room
 * categories and risk categories were hardcoded in the same way and belong
 * here too, so this fills them in. Seeding is per-term firstOrCreate, so an
 * install that already has them is untouched.
 *
 * `budget_category` goes: budget categories are already yours to edit under
 * Settings → Defaults & Templates, and per event on the Budget tab. Two places
 * to change the same thing is worse than one, and this list never shipped.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('taxonomy_terms')->where('taxonomy', 'budget_category')->delete();

        // The first seed wrote deal sources as machine keys (`repeat_client`),
        // but deals store the words themselves (`Repeat client`) — so those
        // rows never matched a record. Any seeded row whose key is no longer
        // one this list would produce is a leftover of that shape and goes.
        // Terms someone added (is_system = false) and terms they renamed (the
        // key is untouched by a rename) both survive.
        foreach (array_keys(Taxonomy::LISTS) as $taxonomy) {
            DB::table('taxonomy_terms')
                ->where('taxonomy', $taxonomy)
                ->where('is_system', true)
                ->whereNotIn('key', array_keys(Taxonomy::defaults($taxonomy)))
                ->delete();
        }

        Taxonomy::seed();
    }

    public function down(): void
    {
        // Nothing to undo: removing lists people have edited would lose their work.
    }
};
