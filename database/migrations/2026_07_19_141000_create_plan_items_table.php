<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Studio — a Plan Item is a deliverable that travels through the status
 * gates (To Do → In Progress → Need Approval → Approved → Done, or Cancelled).
 * It owns subtasks, carries clear dates, and records who approved it and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('track_id')->nullable()->constrained('plan_tracks')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status')->default('todo')->index();
            $table->string('priority')->default('medium');
            $table->date('start_on')->nullable();
            $table->date('due_on')->nullable();
            $table->unsignedTinyInteger('progress_override')->nullable(); // 0-100; null = roll up from subtasks
            $table->json('tags')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['event_id', 'track_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_items');
    }
};
