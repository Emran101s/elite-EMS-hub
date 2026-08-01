<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a service comes from, which is not the same question as what it is.
 *
 * A price list organised only by category can't answer the question the desk
 * actually asks when it prices a job: what is the hotel providing, what are we
 * bringing in from outside, and what are we renting. Those are three different
 * suppliers, three different lead times and three different margins, and they
 * were all one flat list.
 *
 * The section is a taxonomy key (see Taxonomy::LISTS['service_section']), so
 * the sections themselves are renamed and reordered in Settings like every
 * other list — the keys stored here stay put.
 *
 * The backfill maps the categories the seeded list already uses. It is a real
 * signal rather than a guess: accommodation and catering are what a hotel
 * provides, staging and exhibition are brought in. Anything else is left
 * unsectioned, where the list shows it plainly rather than filing it wrongly.
 */
return new class extends Migration
{
    private const BY_CATEGORY = [
        'Accommodation' => 'hotel',
        'Catering' => 'hotel',
        'Transportation' => 'transport',
        'AV & Production' => 'production',
        'Exhibition' => 'production',
        'Documentation' => 'production',
        'Crew' => 'people',
        'Management' => 'fees',
    ];

    public function up(): void
    {
        foreach (['service_items', 'event_invoice_items'] as $table) {
            Schema::table($table, function (Blueprint $t) {
                $t->string('section')->nullable()->after('category')->index();
            });
        }

        foreach (self::BY_CATEGORY as $category => $section) {
            DB::table('service_items')->where('category', $category)
                ->whereNull('section')->update(['section' => $section]);
        }
    }

    public function down(): void
    {
        foreach (['service_items', 'event_invoice_items'] as $table) {
            Schema::table($table, fn (Blueprint $t) => $t->dropColumn('section'));
        }
    }
};
