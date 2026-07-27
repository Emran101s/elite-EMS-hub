<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Sub-categories.
 *
 * A flat list stops being useful the moment a category earns detail:
 * Production is not one thing, it is staging, lighting, rigging and sound.
 * One level of nesting covers that without turning a settings screen into a
 * tree editor nobody can reason about — a term may have a parent, a parent
 * may not itself have one.
 *
 * Records still store the term's own key, so nesting a term changes how it is
 * grouped and never what is written against it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('taxonomy_terms', function (Blueprint $table) {
            // Deleting a parent promotes its children rather than taking them
            // with it: they are still real values sitting on real records.
            $table->foreignId('parent_id')->nullable()->after('taxonomy')
                ->constrained('taxonomy_terms')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('taxonomy_terms', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
        });
    }
};
