<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A venue's own permanent inventory of halls/rooms — deliberately reversing
 * the decision in 2026_07_21_120000_enrich_venues.php to keep rooms per-event
 * only. That was right for what existed then (a flat venue directory with no
 * detail view); Venue Studio needs a venue to have spaces of its own that
 * persist across every event booked there, not a fresh set invented each
 * time. capacity_by_setup is keyed by EventRoom::SEATING_ARRANGEMENTS —
 * reused, not duplicated, so the two never drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('venue_spaces', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->foreignId('venue_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('type', 40)->default('breakout'); // main_hall | breakout | exhibition | registration | vip | catering | networking | loading_dock | speaker_prep
            $table->unsignedInteger('capacity')->nullable();
            $table->json('capacity_by_setup')->nullable();
            $table->decimal('width_m', 6, 2)->nullable();
            $table->decimal('length_m', 6, 2)->nullable();
            $table->string('floor_zone', 80)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['venue_id', 'position']);
            $table->index('tenant_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venue_spaces');
    }
};
