<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A registration form is a list of questions, not a set of columns.
 *
 * Every event asked exactly the same seven things, because those were the
 * columns on event_attendees. An event that needs a passport number, a
 * workshop track or an arrival flight had nowhere to put the answer, and the
 * desk went back to a spreadsheet — which is the thing the public form exists
 * to replace.
 *
 * The split that makes this safe: name, email, phone and ticket type stay real
 * columns, because they are what the badge prints, what check-in matches on
 * and what stops the same person registering twice. Everything else is an
 * answer, and answers are free.
 *
 * Per event, and owned by it. A form is copied from a template rather than
 * linked to one, so editing next year's "Conference" template never rewrites
 * what last year's delegates were actually asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('registration_fields', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Stable within the event: it is what an answer is filed under, so
            // renaming the question must never orphan the answers.
            $table->string('key');
            $table->string('label');
            $table->string('type')->default('text');

            $table->boolean('required')->default(false);

            // Choices for select / multiselect, and nothing for the rest.
            $table->json('options')->nullable();

            $table->string('help')->nullable();
            $table->string('placeholder')->nullable();

            // The core column this question fills, when it fills one. Null
            // means the answer lives in event_attendees.answers.
            $table->string('maps_to')->nullable();

            $table->unsignedInteger('position')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();

            $table->unique(['event_id', 'key']);
            $table->index(['event_id', 'position']);
        });

        Schema::table('event_attendees', function (Blueprint $table) {
            $table->json('answers')->nullable()->after('notes');
        });
    }

    public function down(): void
    {
        Schema::table('event_attendees', fn (Blueprint $table) => $table->dropColumn('answers'));
        Schema::dropIfExists('registration_fields');
    }
};
