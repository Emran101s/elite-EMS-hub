<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A room block is the deal struck with a hotel — "50 rooms at this rate".
 * Guests are named later, one `event_accommodations` row per room, until the
 * block is full. The rate lives here (internal); the rooming list never shows it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_room_blocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('supplier_id')->nullable()->constrained()->nullOnDelete();
            $table->string('hotel', 160);
            $table->string('room_type', 80)->nullable();
            $table->unsignedInteger('rooms_count')->default(1);
            $table->unsignedBigInteger('rate_cents')->default(0);
            $table->date('check_in')->nullable();
            $table->date('check_out')->nullable();
            $table->string('status', 20)->default('held');
            $table->string('confirmation_number', 60)->nullable();
            $table->date('cutoff_on')->nullable();     // hotel's release date for unnamed rooms
            $table->text('notes')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_room_blocks');
    }
};
