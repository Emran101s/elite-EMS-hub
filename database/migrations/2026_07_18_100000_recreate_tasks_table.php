<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A brand-new, self-contained task board. No plan mirror, no cross-module
 * sync — a task is just a task, owned by an event, moved by hand. Rebuilt
 * from zero at the owner's request.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('tasks');

        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('status', 20)->default('todo')->index();
            $table->string('priority', 12)->default('normal');
            $table->string('area', 32)->nullable();          // event department (Venue, Marketing …)
            $table->date('due_on')->nullable();
            $table->json('checklist')->nullable();           // [{text, done}]
            $table->unsignedInteger('sort')->default(0);     // manual order within a lane
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
