<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What THIS event will be invoiced for, at THIS event's prices.
 *
 * The house price list is a starting point, not a rate card: a room negotiated
 * at 95 for one summit is 78 for the next, and the cost behind it moves too.
 * So the prices an event is actually billed at belong to the event, and the
 * house list is never rolled into one automatically — a stale copy of a price
 * nobody agreed is worse than no copy at all.
 *
 * Cost and sell both live here, prepared before anything is invoiced, which is
 * what makes the margin knowable before the work is done rather than after.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();

            // Where it came from, if it was pulled from the house list.
            // Provenance only: changing the house price never moves this one.
            $table->foreignId('service_item_id')->nullable()
                ->constrained('service_items')->nullOnDelete();

            $table->string('code')->nullable();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('detail')->nullable();

            $table->string('unit')->default('item');
            $table->bigInteger('cost_cents')->default(0);
            $table->bigInteger('sell_cents')->default(0);
            $table->string('currency', 3)->default('JOD');
            $table->float('tax_pct')->nullable();

            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            // A code is unique WITHIN an event: two events may both sell ACC-DBL
            // at different prices, and that is the whole point.
            $table->unique(['event_id', 'code']);
            $table->index(['event_id', 'active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_invoice_items');
    }
};
