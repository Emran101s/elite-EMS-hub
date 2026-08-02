<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Question sets you build once and start events from.
 *
 * Phase 1 made a form editable per event, which immediately means rebuilding
 * the same conference form by hand every time you run a conference. A template
 * is that form, kept.
 *
 * The fields live as a list on the template rather than as rows of their own:
 * a template is written and read whole, it has no answers filed against it,
 * and nothing outside Settings ever points at one of its questions.
 *
 * Applying a template COPIES it. The event owns what it ends up with — the
 * same rule as the price list and the event's invoice items — so editing next
 * year's "Conference" never rewrites what last year's delegates were asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('note')->nullable();
            $table->json('fields')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('registration_templates');
    }
};
