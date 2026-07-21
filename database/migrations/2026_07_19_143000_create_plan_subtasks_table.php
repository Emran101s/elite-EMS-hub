<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Plan Studio — a Subtask is a rich checklist entry owned by a plan item:
 * its own title, done-state, owner and due date. Item progress rolls up from these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plan_subtasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_item_id')->constrained('plan_items')->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('due_on')->nullable();
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['plan_item_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_subtasks');
    }
};
