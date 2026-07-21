<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Studio — Tracks are the user-defined groups (swimlanes) an event's
 * plan items are organised into: Venue, Marketing, Production, and so on.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_tracks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('color', 9)->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_tracks');
    }
};
