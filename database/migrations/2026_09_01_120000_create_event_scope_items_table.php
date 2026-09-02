<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The event's Scope of Work: what the client has asked us to deliver, written
 * out and kept current.
 *
 * It is authored here and nowhere else. The Event Brief renders it rather than
 * holding a second copy — a scope typed into two places is a scope that
 * disagrees with itself the first time one of them is revised, and this
 * platform has been corrected for exactly that more than once.
 *
 * Each line is a written statement, grouped by area. is_exclusion marks the
 * ones that say what is NOT included, which is half of what a scope of work is
 * for: "client supplies the interpreter" is the line that settles the argument
 * later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_scope_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Taxonomy-backed (scope_area), so the grouping is editable in
            // Settings rather than baked into a class constant.
            $table->string('area', 40)->default('general');

            $table->string('title');
            $table->text('body')->nullable();

            // A line that states what is not included.
            $table->boolean('is_exclusion')->default(false);

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index('tenant_id');
            $table->index(['event_id', 'area']);
        });

        // Event::moduleEnabled() treats a stored enabled_modules list as
        // exhaustive, so a module added after an event was created is off for
        // it. Nobody chose to disable something that did not exist yet.
        foreach (DB::table('events')->whereNotNull('enabled_modules')->get(['id', 'enabled_modules']) as $event) {
            $modules = json_decode($event->enabled_modules, true);

            if (! is_array($modules) || in_array('scope', $modules, true)) {
                continue;
            }

            $modules[] = 'scope';
            DB::table('events')->where('id', $event->id)->update(['enabled_modules' => json_encode($modules)]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_scope_items');
    }
};
