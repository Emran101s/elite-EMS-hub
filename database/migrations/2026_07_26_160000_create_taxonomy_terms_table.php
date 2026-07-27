<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The lists the platform draws from — event types, budget categories, supplier
 * categories, deal sources, activity types.
 *
 * These were PHP constants, which meant adding an event type your company
 * actually runs required a developer. They live here now.
 *
 * The `key` is deliberately immutable once a term exists: thousands of records
 * store it. The label, colour, order and whether it is offered are what people
 * actually want to change, and all of those are safe.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('taxonomy_terms', function (Blueprint $table) {
            $table->id();
            $table->string('taxonomy');                  // App\Support\Taxonomy::LISTS
            $table->string('key');                       // stored on records — never edited
            $table->string('label');
            $table->string('color', 9)->nullable();
            $table->string('note')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->boolean('is_active')->default(true);
            // A term the platform's own code names. It can be renamed and
            // reordered but not deleted, or that code breaks.
            $table->boolean('is_system')->default(false);
            $table->timestamps();

            $table->unique(['taxonomy', 'key']);
            $table->index(['taxonomy', 'is_active', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('taxonomy_terms');
    }
};
