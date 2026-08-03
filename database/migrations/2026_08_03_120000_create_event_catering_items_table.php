<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Food & Beverage: every occasion an event feeds its people at.
 *
 * Not a single number — a coffee break, a working lunch and a gala dinner at
 * an outside restaurant are three different commitments, on three different
 * days, each with its own headcount and rate. One row per occasion is what
 * lets a rate be quoted per person and a date be checked against the agenda,
 * instead of a single "catering" figure nobody can break down when asked.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_catering_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->string('type')->default('other');
            $table->date('occasion_date')->nullable();
            $table->string('venue_mode')->default('in_house');
            // In-house points at one of the event's own rooms; outside just
            // names the place — a restaurant is not a schedulable space.
            $table->foreignId('room_id')->nullable()->constrained('event_rooms')->nullOnDelete();
            $table->string('location')->nullable();
            $table->unsignedInteger('headcount')->nullable();
            $table->integer('cost_cents')->default(0);
            // Whether cost_cents is quoted per person (× headcount) or as one
            // flat total — a coffee break is priced per head, a hired hall for
            // a private dinner is usually a flat fee regardless of covers.
            $table->boolean('per_person')->default(false);
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('planned');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_catering_items');
    }
};
