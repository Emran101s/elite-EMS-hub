<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The price list — what the company sells, and what one of each costs.
 *
 * Every invoice line was typed from nothing, so the same room rate was entered
 * a dozen different ways and nobody could answer "what do we charge for a
 * double room" without opening an old invoice.
 *
 * The unit is the interesting column. Accommodation is not sold "each": it is
 * sold per room per night, transport per vehicle per day, catering per person.
 * The unit names what ONE of the thing is, and ServiceItem::UNITS says how many
 * numbers it takes to count it — so a room-night asks for rooms AND nights and
 * multiplies, rather than making somebody do it in their head and type 36.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('service_items', function (Blueprint $table) {
            $table->id();

            // Optional but unique when present: a code is what a finance team
            // reconciles against, and two items sharing one is a bad afternoon.
            $table->string('code')->nullable()->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('detail')->nullable();

            $table->string('unit')->default('item');
            $table->bigInteger('unit_price_cents')->default(0);
            $table->string('currency', 3)->default('JOD');

            // Null means "whatever the document is set to"; a number overrides,
            // because some services are exempt and some are not.
            $table->float('tax_pct')->nullable();

            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();

            $table->index(['active', 'category']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('service_items');
    }
};
