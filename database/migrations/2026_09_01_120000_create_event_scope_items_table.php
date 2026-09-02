<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The Delivery Scope register: what this event commits to deliver, who is
 * accountable for each part, and what "done" means.
 *
 * Two columns are deliberately absent.
 *
 * There is no status column. A deliverable's state is read from the module
 * that already owns the answer — the supplier pivot, the agenda's settled
 * sessions, an approval chain, or a linked task. Storing it here would make
 * this the second place a truth lives, and this codebase has repeatedly shown
 * what that costs: a Finance page that read "Billed 0" beside "Owed 350K", a
 * hub header that printed "100% Readiness" above "Readiness 17%". A scope
 * people are held to is the worst possible place for that.
 *
 * There is no due date either — offset_days holds T-minus, so moving the event
 * re-dates the whole scope and a scope can be copied to next year's event
 * without every date being wrong.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_scope_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Taxonomy-backed (scope_workstream), so the grouping is yours to
            // edit in Settings rather than baked into a class constant.
            $table->string('workstream', 40)->default('delivery');

            $table->string('title');
            $table->text('definition_of_done')->nullable();

            // The exclusions. Half of what a scope of work is for: "client
            // supplies the interpreter" is the line that ends the argument.
            $table->text('out_of_scope')->nullable();

            // One accountable person. Contributors are consulted/informed and
            // deliberately cannot be accountable — that is what one owner means.
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->json('contributor_ids')->nullable();

            // T-minus, in days from the event start. Negative is before.
            $table->integer('offset_days')->default(-14);

            // Where status is derived from. source_type is a key in
            // App\Support\ScopeStatus::SOURCES; source_id points at the row
            // when the source needs one (a task, an approval).
            $table->string('source_type', 32)->nullable();
            $table->unsignedBigInteger('source_id')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            // Every query filters on tenant_id first — the composite indexes
            // below lead with event_id, which does not serve that.
            $table->index('tenant_id');
            $table->index(['event_id', 'workstream']);
            $table->index(['event_id', 'owner_id']);
        });

        // Event::moduleEnabled() treats a stored enabled_modules list as
        // exhaustive, so a module added after an event was created is off for
        // it. Nobody chose to disable something that did not exist yet, so
        // append rather than leave every existing event without the tab.
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
