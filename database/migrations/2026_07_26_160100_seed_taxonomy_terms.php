<?php

use App\Support\Taxonomy;
use Illuminate\Database\Migrations\Migration;

/**
 * Fill the editable lists from the constants they replaced.
 *
 * Idempotent by firstOrCreate, so it is safe on a database that already has
 * them and safe to re-run. Nothing is deleted on the way down — a term someone
 * has since renamed or added is theirs, not the migration's.
 */
return new class extends Migration
{
    public function up(): void
    {
        Taxonomy::seed();
    }

    public function down(): void
    {
        // Deliberately empty: dropping the table is the migration above's job,
        // and removing terms here would take the user's own edits with them.
    }
};
